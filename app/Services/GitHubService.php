<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Smpita\TypeAs\TypeAs;

class GitHubService
{
    protected const GRAPHQL_ENDPOINT = 'https://api.github.com/graphql';

    protected const REST_ENDPOINT = 'https://api.github.com';

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
     * Clear the PR cache to force a fresh fetch.
     *
     * @param  array<int, string>  $repositories
     */
    public function clearCache(array $repositories = []): void
    {
        $reposKey = ! empty($repositories) ? implode(',', $repositories) : 'all';
        $cacheKey = 'github_prs_'.md5($this->token.$this->organizations.$reposKey);
        Cache::forget($cacheKey);

        // Also clear the "all repos" cache when specific repos are cleared
        if (! empty($repositories)) {
            $allCacheKey = 'github_prs_'.md5($this->token.$this->organizations.'all');
            Cache::forget($allCacheKey);
        }
    }

    /**
     * Get all open PRs, optionally filtered by authors and repositories.
     *
     * @param  array<int, string>  $authors
     * @param  array<int, string>  $repositories
     * @return Collection<int, array<string, mixed>>
     */
    public function getPullRequestsByAuthors(array $authors = [], array $repositories = []): Collection
    {
        $prs = $this->fetchAllOpenPullRequests($repositories);

        // Filter by authors if specified
        if (! empty($authors)) {
            $prs = $prs->filter(function (array $pr) use ($authors): bool {
                $authorLogin = TypeAs::string($pr['author']['login'] ?? '');

                return in_array($authorLogin, $authors, true);
            });
        }

        return $this->sortPullRequests($prs);
    }

    /**
     * Get all open PRs where specific users are requested as reviewers.
     *
     * @param  array<int, string>  $reviewers
     * @param  array<int, string>  $repositories
     * @return Collection<int, array<string, mixed>>
     */
    public function getPullRequestsForReviewers(array $reviewers = [], array $repositories = []): Collection
    {
        $prs = $this->fetchAllOpenPullRequests($repositories);

        // Filter by requested reviewers if specified
        if (! empty($reviewers)) {
            $prs = $prs->filter(function (array $pr) use ($reviewers): bool {
                $requestedReviewers = collect($pr['reviewRequests'] ?? [])
                    ->pluck('login')
                    ->filter()
                    ->toArray();

                // Check if any of the selected reviewers are requested
                return ! empty(array_intersect($reviewers, $requestedReviewers));
            });
        }

        return $this->sortPullRequests($prs);
    }

    /**
     * Fetch all open pull requests, optionally filtered by repositories.
     *
     * @param  array<int, string>  $repositories  Repository names in "owner/repo" format
     * @return Collection<int, array<string, mixed>>
     */
    protected function fetchAllOpenPullRequests(array $repositories = []): Collection
    {
        $organizations = $this->getOrganizations();
        $reposKey = ! empty($repositories) ? implode(',', $repositories) : 'all';
        $cacheKey = 'github_prs_'.md5($this->token.$this->organizations.$reposKey);

        return Cache::remember($cacheKey, now()->addMinute(), function () use ($organizations, $repositories): Collection {
            // If specific repositories are selected, search those directly (much faster)
            if (! empty($repositories)) {
                return $this->fetchPullRequestsForRepositories($repositories);
            }

            if (empty($organizations)) {
                return $this->fetchViewerPullRequests();
            }

            // For single org, fetch directly; for multiple, fetch concurrently
            if (count($organizations) === 1) {
                return $this->fetchOrganizationPullRequests($organizations[0]);
            }

            // Fetch multiple organizations concurrently using Http::pool
            $allPrs = collect();
            $orgResults = $this->fetchOrganizationsPullRequestsConcurrently($organizations);

            foreach ($orgResults as $prs) {
                $allPrs = $allPrs->merge($prs);
            }

            return $allPrs->unique('url')->values();
        });
    }

    /**
     * Fetch PRs for specific repositories (much faster than org-wide search).
     *
     * @param  array<int, string>  $repositories  Repository names in "owner/repo" format
     * @return Collection<int, array<string, mixed>>
     */
    protected function fetchPullRequestsForRepositories(array $repositories): Collection
    {
        // Build search query with repo: filters (max ~256 chars per query, so batch if needed)
        $repoFilters = collect($repositories)
            ->map(fn (string $repo): string => "repo:{$repo}")
            ->implode(' ');

        $searchQuery = "is:pr is:open {$repoFilters}";

        return $this->executeSearchQuery($searchQuery);
    }

    /**
     * Execute a search query and return transformed PRs.
     */
    protected function executeSearchQuery(string $searchQuery): Collection
    {
        $query = $this->getSearchQuery();
        $allPrs = [];
        $cursor = null;

        do {
            $response = $this->graphql($query, ['searchQuery' => $searchQuery, 'cursor' => $cursor]);
            $data = $response['data']['search'] ?? [];
            $nodes = $data['nodes'] ?? [];
            $pageInfo = $data['pageInfo'] ?? [];

            foreach ($nodes as $pr) {
                if (! empty($pr)) {
                    $allPrs[] = $this->transformPullRequest($pr);
                }
            }

            $cursor = $pageInfo['endCursor'] ?? null;
            $hasNextPage = $pageInfo['hasNextPage'] ?? false;
        } while ($hasNextPage && $cursor);

        return collect($allPrs);
    }

    /**
     * Fetch PRs for multiple organizations concurrently.
     *
     * @param  array<int, string>  $organizations
     * @return array<int, Collection<int, array<string, mixed>>>
     */
    protected function fetchOrganizationsPullRequestsConcurrently(array $organizations): array
    {
        $results = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($organizations) {
            $query = $this->getSearchQuery();

            foreach ($organizations as $org) {
                $searchQuery = "is:pr is:open org:{$org}";
                $pool->as($org)
                    ->withToken($this->token)
                    ->withHeaders([
                        'Accept' => 'application/vnd.github+json',
                        'X-GitHub-Api-Version' => '2022-11-28',
                    ])
                    ->post(self::GRAPHQL_ENDPOINT, [
                        'query' => $query,
                        'variables' => ['searchQuery' => $searchQuery, 'cursor' => null],
                    ]);
            }
        });

        $allResults = [];

        foreach ($organizations as $org) {
            $response = $results[$org] ?? null;
            if ($response === null || $response->failed()) {
                $allResults[] = collect();

                continue;
            }

            $data = $response->json();
            $nodes = $data['data']['search']['nodes'] ?? [];
            $prs = collect($nodes)
                ->filter()
                ->map(fn (array $pr) => $this->transformPullRequest($pr))
                ->values();

            $allResults[] = $prs;
        }

        return $allResults;
    }

    /**
     * Get the search query template for fetching PRs.
     */
    protected function getSearchQuery(): string
    {
        $query = <<<'GRAPHQL'
        query($searchQuery: String!, $cursor: String) {
          search(query: $searchQuery, type: ISSUE, first: 100, after: $cursor) {
            pageInfo {
              hasNextPage
              endCursor
            }
            nodes {
              ... on PullRequest {
                ...PullRequestFields
              }
            }
          }
        }
        GRAPHQL;

        return $query.$this->getPullRequestFragment();
    }

    /**
     * Fetch PRs for viewer (current user) when no org is specified.
     * Includes both PRs authored by the user and PRs where they're a requested reviewer.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function fetchViewerPullRequests(): Collection
    {
        // Fetch PRs authored by the viewer
        $authoredPrs = $this->executeSearchQuery('is:pr is:open author:@me');

        // Fetch PRs where the viewer is a requested reviewer
        $reviewRequestedPrs = $this->executeSearchQuery('is:pr is:open review-requested:@me');

        // Merge and deduplicate by URL
        return $authoredPrs->merge($reviewRequestedPrs)->unique('url')->values();
    }

    /**
     * Fetch all open PRs for an organization using search API.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function fetchOrganizationPullRequests(string $org): Collection
    {
        $query = <<<'GRAPHQL'
        query($searchQuery: String!, $cursor: String) {
          search(query: $searchQuery, type: ISSUE, first: 100, after: $cursor) {
            pageInfo {
              hasNextPage
              endCursor
            }
            nodes {
              ... on PullRequest {
                ...PullRequestFields
              }
            }
          }
        }
        GRAPHQL;

        $query .= $this->getPullRequestFragment();

        $allPrs = [];
        $cursor = null;
        $searchQuery = "is:pr is:open org:{$org}";

        do {
            $response = $this->graphql($query, ['searchQuery' => $searchQuery, 'cursor' => $cursor]);
            $data = $response['data']['search'] ?? [];
            $nodes = $data['nodes'] ?? [];
            $pageInfo = $data['pageInfo'] ?? [];

            foreach ($nodes as $pr) {
                if (! empty($pr)) {
                    $allPrs[] = $this->transformPullRequest($pr);
                }
            }

            $cursor = $pageInfo['endCursor'] ?? null;
            $hasNextPage = $pageInfo['hasNextPage'] ?? false;
        } while ($hasNextPage && $cursor);

        return collect($allPrs);
    }

    /**
     * Get the GraphQL fragment for pull request fields.
     */
    protected function getPullRequestFragment(): string
    {
        return <<<'GRAPHQL'

        fragment PullRequestFields on PullRequest {
          number
          title
          url
          createdAt
          updatedAt
          state
          isDraft
          headRefName
          baseRefName
          mergeable
          reviewDecision
          author {
            login
          }
          repository {
            name
            nameWithOwner
            owner {
              login
            }
          }
          labels(first: 10) {
            nodes {
              name
              color
            }
          }
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
              author {
                login
                avatarUrl(size: 32)
              }
              state
              submittedAt
            }
          }
          reviewRequests(first: 20) {
            nodes {
              requestedReviewer {
                ... on User {
                  login
                  avatarUrl(size: 32)
                }
                ... on Team {
                  name
                }
              }
            }
          }
          reviewThreads(first: 100) {
            nodes {
              isResolved
            }
          }
        }
        GRAPHQL;
    }

    /**
     * Transform a GraphQL PR response into our standard format.
     *
     * @param  array<string, mixed>  $pr
     * @param  array<string, mixed>|null  $repoOverride
     * @return array<string, mixed>
     */
    protected function transformPullRequest(array $pr, ?array $repoOverride = null): array
    {
        // Get reviews (latest per reviewer)
        $reviewNodes = $pr['reviews']['nodes'] ?? [];
        $reviewsByAuthor = collect($reviewNodes)
            ->groupBy(fn (array $review): string => $review['author']['login'] ?? 'unknown')
            ->map(fn (Collection $reviews): array => $reviews
                ->sortByDesc(fn (array $r): string => $r['submittedAt'] ?? '')
                ->first()
            );

        // Get pending review requests
        $reviewersWhoReviewed = $reviewsByAuthor->keys()->toArray();
        $reviewRequestNodes = $pr['reviewRequests']['nodes'] ?? [];

        $pendingReviewers = collect($reviewRequestNodes)
            ->map(function (array $request): ?array {
                $reviewer = $request['requestedReviewer'] ?? [];
                $login = $reviewer['login'] ?? $reviewer['name'] ?? null;
                if ($login === null) {
                    return null;
                }

                return [
                    'login' => $login,
                    'avatarUrl' => $reviewer['avatarUrl'] ?? null,
                ];
            })
            ->filter()
            ->reject(fn (array $reviewer): bool => in_array($reviewer['login'], $reviewersWhoReviewed, true))
            ->map(fn (array $reviewer): array => [
                'author' => [
                    'login' => $reviewer['login'],
                    'avatarUrl' => $reviewer['avatarUrl'],
                ],
                'state' => 'PENDING',
                'submittedAt' => null,
            ])
            ->values();

        $allReviews = $reviewsByAuthor->values()->merge($pendingReviewers)->values()->toArray();

        // Get review requests as simple array of logins with avatars
        $reviewRequests = collect($reviewRequestNodes)
            ->map(function (array $request): ?array {
                $reviewer = $request['requestedReviewer'] ?? [];
                $login = $reviewer['login'] ?? $reviewer['name'] ?? null;
                if ($login === null) {
                    return null;
                }

                return [
                    'login' => $login,
                    'avatarUrl' => $reviewer['avatarUrl'] ?? null,
                ];
            })
            ->filter()
            ->values()
            ->toArray();

        // Count unresolved threads
        $reviewThreadNodes = $pr['reviewThreads']['nodes'] ?? [];
        $unresolvedCount = collect($reviewThreadNodes)
            ->filter(fn (array $thread): bool => ! ($thread['isResolved'] ?? true))
            ->count();

        // Get CI status
        $commits = $pr['commits']['nodes'] ?? [];
        $lastCommit = $commits[0] ?? [];
        $ciStatus = $lastCommit['commit']['statusCheckRollup']['state'] ?? null;

        // Get labels
        $labels = collect($pr['labels']['nodes'] ?? [])
            ->map(fn (array $label): array => [
                'name' => $label['name'] ?? '',
                'color' => $label['color'] ?? '',
            ])
            ->toArray();

        // Use repo override if provided (for org queries)
        $repository = $repoOverride ?? $pr['repository'] ?? [];

        return [
            'number' => $pr['number'],
            'title' => $pr['title'],
            'url' => $pr['url'],
            'createdAt' => $pr['createdAt'],
            'updatedAt' => $pr['updatedAt'],
            'state' => $pr['state'],
            'isDraft' => $pr['isDraft'] ?? false,
            'headRefName' => $pr['headRefName'],
            'baseRefName' => $pr['baseRefName'],
            'mergeable' => $pr['mergeable'],
            'reviewDecision' => $pr['reviewDecision'],
            'isApproved' => ($pr['reviewDecision'] ?? '') === 'APPROVED',
            'ciStatus' => $ciStatus,
            'author' => $pr['author'] ?? ['login' => 'unknown'],
            'repository' => [
                'name' => $repository['name'] ?? '',
                'nameWithOwner' => $repository['nameWithOwner'] ?? '',
                'owner' => $repository['owner'] ?? [],
            ],
            'labels' => $labels,
            'reviews' => $allReviews,
            'reviewRequests' => $reviewRequests,
            'unresolvedCount' => $unresolvedCount,
        ];
    }

    /**
     * Sort pull requests by approval status, unresolved comments, and update time.
     *
     * @param  Collection<int, array<string, mixed>>  $prs
     * @return Collection<int, array<string, mixed>>
     */
    protected function sortPullRequests(Collection $prs): Collection
    {
        return $prs
            ->sortBy([
                ['isApproved', 'asc'],
                ['unresolvedCount', 'desc'],
                ['updatedAt', 'desc'],
            ])
            ->values();
    }

    /**
     * Get accessible repositories grouped by owner.
     *
     * @return Collection<string, Collection<int, array{name: string, nameWithOwner: string}>>
     */
    public function getAccessibleRepositories(): Collection
    {
        $organizations = $this->getOrganizations();

        if (empty($organizations)) {
            return $this->getViewerRepositories();
        }

        $allRepos = collect();

        foreach ($organizations as $org) {
            $repos = $this->getOrganizationRepositories($org);
            $allRepos[$org] = $repos;
        }

        return $allRepos->filter(fn (Collection $repos) => $repos->isNotEmpty());
    }

    /**
     * Get viewer's repositories.
     *
     * @return Collection<string, Collection<int, array{name: string, nameWithOwner: string}>>
     */
    protected function getViewerRepositories(): Collection
    {
        $query = <<<'GRAPHQL'
        query($cursor: String) {
          viewer {
            repositories(first: 100, after: $cursor, ownerAffiliations: [OWNER, ORGANIZATION_MEMBER, COLLABORATOR]) {
              pageInfo {
                hasNextPage
                endCursor
              }
              nodes {
                name
                nameWithOwner
                owner {
                  login
                }
              }
            }
          }
        }
        GRAPHQL;

        $allRepos = [];
        $cursor = null;

        do {
            $response = $this->graphql($query, ['cursor' => $cursor]);
            $data = $response['data']['viewer']['repositories'] ?? [];
            $nodes = $data['nodes'] ?? [];
            $pageInfo = $data['pageInfo'] ?? [];

            foreach ($nodes as $repo) {
                $owner = $repo['owner']['login'] ?? 'unknown';
                if (! isset($allRepos[$owner])) {
                    $allRepos[$owner] = [];
                }
                $allRepos[$owner][] = [
                    'name' => $repo['name'],
                    'nameWithOwner' => $repo['nameWithOwner'],
                ];
            }

            $cursor = $pageInfo['endCursor'] ?? null;
            $hasNextPage = $pageInfo['hasNextPage'] ?? false;
        } while ($hasNextPage && $cursor);

        return collect($allRepos)->map(fn (array $repos) => collect($repos));
    }

    /**
     * Get repositories for an organization.
     *
     * @return Collection<int, array{name: string, nameWithOwner: string}>
     */
    protected function getOrganizationRepositories(string $org): Collection
    {
        $query = <<<'GRAPHQL'
        query($org: String!, $cursor: String) {
          organization(login: $org) {
            repositories(first: 100, after: $cursor) {
              pageInfo {
                hasNextPage
                endCursor
              }
              nodes {
                name
                nameWithOwner
              }
            }
          }
        }
        GRAPHQL;

        $allRepos = [];
        $cursor = null;

        do {
            $response = $this->graphql($query, ['org' => $org, 'cursor' => $cursor]);
            $data = $response['data']['organization']['repositories'] ?? [];
            $nodes = $data['nodes'] ?? [];
            $pageInfo = $data['pageInfo'] ?? [];

            foreach ($nodes as $repo) {
                $allRepos[] = [
                    'name' => $repo['name'],
                    'nameWithOwner' => $repo['nameWithOwner'],
                ];
            }

            $cursor = $pageInfo['endCursor'] ?? null;
            $hasNextPage = $pageInfo['hasNextPage'] ?? false;
        } while ($hasNextPage && $cursor);

        return collect($allRepos);
    }

    /**
     * Get the current GitHub username.
     */
    public function getCurrentUser(): ?string
    {
        try {
            $response = $this->graphql('query { viewer { login } }');

            return $response['data']['viewer']['login'] ?? null;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Get organization members.
     *
     * @return Collection<int, array{login: string, avatarUrl: string}>
     */
    public function getOrganizationMembers(): Collection
    {
        $organizations = $this->getOrganizations();

        if (empty($organizations)) {
            $currentUser = $this->getCurrentUser();

            return $currentUser !== null
                ? collect([['login' => $currentUser, 'avatarUrl' => "https://github.com/{$currentUser}.png?size=32"]])
                : collect();
        }

        $allMembers = collect();

        foreach ($organizations as $org) {
            $members = $this->getMembersForOrganization($org);
            $allMembers = $allMembers->merge($members);
        }

        return $allMembers
            ->unique('login')
            ->sortBy('login')
            ->values();
    }

    /**
     * Get members for a specific organization.
     *
     * @return Collection<int, array{login: string, avatarUrl: string}>
     */
    protected function getMembersForOrganization(string $org): Collection
    {
        $query = <<<'GRAPHQL'
        query($org: String!, $cursor: String) {
          organization(login: $org) {
            membersWithRole(first: 100, after: $cursor) {
              pageInfo {
                hasNextPage
                endCursor
              }
              nodes {
                login
                avatarUrl(size: 32)
              }
            }
          }
        }
        GRAPHQL;

        $allMembers = [];
        $cursor = null;

        do {
            try {
                $response = $this->graphql($query, ['org' => $org, 'cursor' => $cursor]);
                $data = $response['data']['organization']['membersWithRole'] ?? [];
                $nodes = $data['nodes'] ?? [];
                $pageInfo = $data['pageInfo'] ?? [];

                foreach ($nodes as $member) {
                    $allMembers[] = [
                        'login' => $member['login'],
                        'avatarUrl' => $member['avatarUrl'],
                    ];
                }

                $cursor = $pageInfo['endCursor'] ?? null;
                $hasNextPage = $pageInfo['hasNextPage'] ?? false;
            } catch (\Exception) {
                break;
            }
        } while ($hasNextPage && $cursor);

        return collect($allMembers);
    }

    /**
     * Execute a GraphQL query against GitHub's API.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    protected function graphql(string $query, array $variables = []): array
    {
        if ($this->token === null || $this->token === '') {
            throw new RuntimeException('GitHub token not configured. Click the settings icon to add your token.');
        }

        $response = Http::withToken($this->token)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->post(self::GRAPHQL_ENDPOINT, [
                'query' => $query,
                'variables' => $variables,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('GitHub API request failed: '.$response->body());
        }

        $data = $response->json();

        if (isset($data['errors'])) {
            $errorMessage = collect($data['errors'])
                ->pluck('message')
                ->implode(', ');
            throw new RuntimeException('GraphQL error: '.$errorMessage);
        }

        return $data;
    }
}
