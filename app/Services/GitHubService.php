<?php

namespace App\Services;

use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Smpita\TypeAs\TypeAs;

class GitHubService
{
    protected const GH_BINARY = '/opt/homebrew/bin/gh';

    protected ?string $token;

    protected string $organizations;

    public function __construct(?string $token = null, string $organizations = '')
    {
        $this->token = $token ?? TypeAs::nullableString(config('services.github.token'));
        $this->organizations = $organizations !== '' ? $organizations : TypeAs::string(config('services.github.organizations'), '');
    }

    /**
     * Get the list of organizations to filter by.
     *
     * @return array<int, string>
     */
    protected function getOrganizations(): array
    {
        if ($this->organizations === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $this->organizations))));
    }

    /**
     * Get PRs authored by the current user with review details.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getMyPullRequests(int $limit = 50): Collection
    {
        $prs = $this->searchPullRequests('author', $limit);

        return $this->enrichAndSortPullRequests($prs);
    }

    /**
     * Get PRs where the current user is requested as a reviewer with review details.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getReviewRequests(int $limit = 50): Collection
    {
        $prs = $this->searchPullRequests('review-requested', $limit);

        return $this->enrichAndSortPullRequests($prs);
    }

    /**
     * Search for PRs with basic info.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function searchPullRequests(string $filter, int $limit): Collection
    {
        $organizations = $this->getOrganizations();

        // If no organizations specified, search all
        if ($organizations === []) {
            return $this->searchPullRequestsForOrg($filter, $limit, null);
        }

        // If single org, search directly
        if (count($organizations) === 1) {
            return $this->searchPullRequestsForOrg($filter, $limit, $organizations[0]);
        }

        // Multiple orgs: search each concurrently and combine
        $tasks = [];
        foreach ($organizations as $org) {
            $tasks[$org] = fn () => $this->searchPullRequestsForOrg($filter, $limit, $org);
        }

        /** @var array<string, Collection<int, array<string, mixed>>> $results */
        $results = Concurrency::run($tasks);

        /** @var Collection<int, array<string, mixed>> $combined */
        $combined = collect($results)
            ->flatten(1)
            ->unique(fn (array $pr): string => TypeAs::string($pr['url'] ?? ''))
            ->values();

        return $combined;
    }

    /**
     * Search PRs for a specific organization.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function searchPullRequestsForOrg(string $filter, int $limit, ?string $organization): Collection
    {
        $command = [
            'gh', 'search', 'prs',
            "--{$filter}", '@me',
            '--state', 'open',
        ];

        if ($organization !== null) {
            $command = array_merge($command, ['--owner', $organization]);
        }

        $command = array_merge($command, [
            '--limit', (string) $limit,
            '--json', 'number,title,url,repository,createdAt,updatedAt,state,labels,isDraft,author',
        ]);

        $result = $this->runGhCommand($command);

        /** @var array<int, array<string, mixed>> $decoded */
        $decoded = TypeAs::array(json_decode($result, true), []);

        return collect($decoded)->values();
    }

    /**
     * Enrich PRs with review details and sort by unresolved comments.
     *
     * @param  Collection<int, array<string, mixed>>  $prs
     * @return Collection<int, array<string, mixed>>
     */
    protected function enrichAndSortPullRequests(Collection $prs): Collection
    {
        if ($prs->isEmpty()) {
            return $prs;
        }

        // Build tasks for concurrent execution
        $tasks = [];
        foreach ($prs as $index => $pr) {
            $repo = $this->getRepoName($pr);
            $number = TypeAs::int($pr['number'] ?? 0);
            $tasks[$index] = fn () => $this->getPullRequestDetails($repo, $number);
        }

        /** @var array<int, array{reviewDecision: ?string, reviews: array<int, array<string, mixed>>, unresolvedCount: int, headRefName: ?string, baseRefName: ?string, mergeable: ?string, ciStatus: ?string}> $details */
        $details = Concurrency::run($tasks);

        // Merge details back into PRs
        /** @var Collection<int, array<string, mixed>> $enriched */
        $enriched = $prs
            ->map(function (array $pr, int $index) use ($details): array {
                $prDetails = $details[$index];

                return array_merge($pr, [
                    'reviewDecision' => $prDetails['reviewDecision'],
                    'reviews' => $prDetails['reviews'],
                    'unresolvedCount' => $prDetails['unresolvedCount'],
                    'isApproved' => $prDetails['reviewDecision'] === 'APPROVED',
                    'headRefName' => $prDetails['headRefName'],
                    'baseRefName' => $prDetails['baseRefName'],
                    'mergeable' => $prDetails['mergeable'],
                    'ciStatus' => $prDetails['ciStatus'],
                ]);
            })
            ->sortBy([
                ['isApproved', 'asc'],
                ['unresolvedCount', 'desc'],
                ['updatedAt', 'desc'],
            ])
            ->values();

        return $enriched;
    }

    /**
     * Get the repository name from a PR array.
     *
     * @param  array<string, mixed>  $pr
     */
    protected function getRepoName(array $pr): string
    {
        $repository = TypeAs::array($pr['repository'] ?? [], []);

        if (isset($repository['nameWithOwner'])) {
            return TypeAs::string($repository['nameWithOwner']);
        }

        $owner = TypeAs::array($repository['owner'] ?? [], []);
        $ownerLogin = TypeAs::string($owner['login'] ?? '');
        $repoName = TypeAs::string($repository['name'] ?? '');

        return "{$ownerLogin}/{$repoName}";
    }

    /**
     * Get detailed PR info including reviews and unresolved comments.
     *
     * @return array{reviewDecision: ?string, reviews: array<int, array<string, mixed>>, unresolvedCount: int, headRefName: ?string, baseRefName: ?string, mergeable: ?string, ciStatus: ?string}
     */
    protected function getPullRequestDetails(string $repo, int $number): array
    {
        $query = <<<'GRAPHQL'
        query($owner: String!, $repo: String!, $number: Int!) {
          repository(owner: $owner, name: $repo) {
            pullRequest(number: $number) {
              headRefName
              baseRefName
              reviewDecision
              mergeable
              commits(last: 1) {
                nodes {
                  commit {
                    statusCheckRollup {
                      state
                    }
                  }
                }
              }
              reviews(last: 20) {
                nodes {
                  author { login }
                  state
                  submittedAt
                }
              }
              reviewRequests(first: 20) {
                nodes {
                  requestedReviewer {
                    ... on User { login }
                    ... on Team { name }
                  }
                }
              }
              reviewThreads(first: 100) {
                nodes {
                  isResolved
                }
              }
            }
          }
        }
        GRAPHQL;

        [$owner, $repoName] = explode('/', $repo);

        $result = $this->runGhCommand([
            'gh', 'api', 'graphql',
            '-f', "query={$query}",
            '-F', "owner={$owner}",
            '-F', "repo={$repoName}",
            '-F', "number={$number}",
        ]);

        $data = TypeAs::array(json_decode($result, true), []);
        $dataData = TypeAs::array($data['data'] ?? [], []);
        $repository = TypeAs::array($dataData['repository'] ?? [], []);
        $pr = TypeAs::array($repository['pullRequest'] ?? [], []);

        // Get submitted reviews (latest per reviewer)
        $reviewsData = TypeAs::array($pr['reviews'] ?? [], []);
        $reviewNodes = TypeAs::array($reviewsData['nodes'] ?? [], []);
        /** @var Collection<string, array<string, mixed>> $reviewsByAuthor */
        $reviewsByAuthor = collect($reviewNodes)
            ->groupBy(function (mixed $review): string {
                $reviewArray = TypeAs::array($review, []);
                $author = TypeAs::array($reviewArray['author'] ?? [], []);

                return TypeAs::string($author['login'] ?? 'unknown');
            })
            ->map(function (Collection $userReviews): array {
                /** @var array<string, mixed> $latest */
                $latest = $userReviews->sortByDesc(function (mixed $review): string {
                    $reviewArray = TypeAs::array($review, []);

                    return TypeAs::string($reviewArray['submittedAt'] ?? '');
                })->first();

                return $latest;
            });

        // Get pending review requests (users who haven't reviewed yet)
        $reviewersWhoReviewed = $reviewsByAuthor->keys()->toArray();
        $reviewRequestsData = TypeAs::array($pr['reviewRequests'] ?? [], []);
        $reviewRequestNodes = TypeAs::array($reviewRequestsData['nodes'] ?? [], []);

        /** @var Collection<int, array<string, mixed>> $pendingReviewers */
        $pendingReviewers = collect($reviewRequestNodes)
            ->map(function (mixed $request): ?string {
                $requestArray = TypeAs::array($request, []);
                $reviewer = TypeAs::array($requestArray['requestedReviewer'] ?? [], []);

                return TypeAs::nullableString($reviewer['login'] ?? $reviewer['name'] ?? null);
            })
            ->filter()
            ->reject(fn (?string $login): bool => in_array($login, $reviewersWhoReviewed, true))
            ->map(fn (string $login): array => [
                'author' => ['login' => $login],
                'state' => 'PENDING',
                'submittedAt' => null,
            ])
            ->values();

        // Merge reviews and pending reviewers
        /** @var array<int, array<string, mixed>> $allReviews */
        $allReviews = $reviewsByAuthor->values()->merge($pendingReviewers)->values()->toArray();

        $reviewThreadsData = TypeAs::array($pr['reviewThreads'] ?? [], []);
        $reviewThreadNodes = TypeAs::array($reviewThreadsData['nodes'] ?? [], []);
        $unresolvedCount = collect($reviewThreadNodes)
            ->filter(function (mixed $thread): bool {
                $threadArray = TypeAs::array($thread, []);

                return ! TypeAs::bool($threadArray['isResolved'] ?? true);
            })
            ->count();

        // Get CI status from the last commit
        $commits = TypeAs::array($pr['commits'] ?? [], []);
        $commitNodes = TypeAs::array($commits['nodes'] ?? [], []);
        $lastCommit = TypeAs::array($commitNodes[0] ?? [], []);
        $commit = TypeAs::array($lastCommit['commit'] ?? [], []);
        $statusCheckRollup = TypeAs::array($commit['statusCheckRollup'] ?? [], []);
        $ciStatus = TypeAs::nullableString($statusCheckRollup['state'] ?? null);

        return [
            'reviewDecision' => TypeAs::nullableString($pr['reviewDecision'] ?? null),
            'reviews' => $allReviews,
            'unresolvedCount' => $unresolvedCount,
            'headRefName' => TypeAs::nullableString($pr['headRefName'] ?? null),
            'baseRefName' => TypeAs::nullableString($pr['baseRefName'] ?? null),
            'mergeable' => TypeAs::nullableString($pr['mergeable'] ?? null),
            'ciStatus' => $ciStatus,
        ];
    }

    /**
     * Get the current GitHub username.
     */
    public function getCurrentUser(): ?string
    {
        try {
            $result = $this->runGhCommand(['gh', 'api', 'user', '--jq', '.login']);

            return trim($result);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Run a gh CLI command with proper environment.
     *
     * @param  array<int, string>  $command
     */
    protected function runGhCommand(array $command): string
    {
        if ($this->token === null || $this->token === '') {
            throw new RuntimeException('GitHub token not configured. Click the settings icon to add your token.');
        }

        // Replace 'gh' with full path for environments where PATH doesn't include Homebrew
        if ($command[0] === 'gh') {
            $command[0] = self::GH_BINARY;
        }

        $result = Process::env([
            'GH_TOKEN' => $this->token,
        ])->run($command);

        if ($result->failed()) {
            throw new ProcessFailedException($result);
        }

        return $result->output();
    }
}
