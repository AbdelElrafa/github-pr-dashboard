<?php

namespace App\Livewire;

use App\Services\GitHubService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Smpita\TypeAs\TypeAs;

#[Lazy]
class PullRequestsTable extends Component
{
    #[Reactive]
    public string $token = '';

    #[Reactive]
    public string $organizations = '';

    #[Reactive]
    public bool $hideApprovedToMaster = false;

    #[Reactive]
    public bool $hideApprovedToOther = false;

    #[Reactive]
    public bool $hideDrafts = false;

    #[Reactive]
    public int $refreshInterval = 0;

    /** @var array<int, string> */
    #[Reactive]
    public array $selectedRepositories = [];

    /** @var array<int, string> */
    #[Reactive]
    public array $selectedAuthors = [];

    /** @var array<int, string> */
    #[Reactive]
    public array $selectedReviewers = [];

    #[Reactive]
    public string $search = '';

    public string $type = 'my-prs';

    /** @var Collection<int, array<string, mixed>> */
    public Collection $pullRequests;

    public ?string $error = null;

    public ?string $currentUser = null;

    public function mount(): void
    {
        /** @var Collection<int, array<string, mixed>> $emptyCollection */
        $emptyCollection = collect();
        $this->pullRequests = $emptyCollection;

        $this->loadData();
    }

    #[On('manual-refresh-tables')]
    public function manualRefresh(): void
    {
        // Clear cache to force fresh data on manual refresh
        if ($this->token !== '') {
            $github = new GitHubService($this->token, $this->organizations);
            $github->clearCache($this->selectedRepositories);
        }

        $this->loadData();
        $this->js("window.dispatchEvent(new CustomEvent('manual-refresh-end'))");
    }

    public function poll(): void
    {
        $this->loadData();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getFilteredPullRequestsProperty(): Collection
    {
        /** @var Collection<int, array<string, mixed>> $filtered */
        $filtered = $this->pullRequests
            ->when(count($this->selectedRepositories) > 0, function (Collection $prs): Collection {
                return $prs->filter(function (array $pr): bool {
                    $repository = TypeAs::array($pr['repository'] ?? [], []);
                    $nameWithOwner = TypeAs::string($repository['nameWithOwner'] ?? '');

                    // Also check owner/name format
                    if ($nameWithOwner === '') {
                        $owner = TypeAs::array($repository['owner'] ?? [], []);
                        $ownerLogin = TypeAs::string($owner['login'] ?? '');
                        $repoName = TypeAs::string($repository['name'] ?? '');
                        $nameWithOwner = "{$ownerLogin}/{$repoName}";
                    }

                    return in_array($nameWithOwner, $this->selectedRepositories, true);
                });
            })
            ->when($this->hideApprovedToMaster, function (Collection $prs): Collection {
                return $prs->reject(function (array $pr): bool {
                    $isApproved = TypeAs::bool($pr['isApproved'] ?? false);
                    $baseRefName = TypeAs::string($pr['baseRefName'] ?? '');

                    return $isApproved && in_array($baseRefName, ['master', 'main'], true);
                });
            })
            ->when($this->hideApprovedToOther, function (Collection $prs): Collection {
                return $prs->reject(function (array $pr): bool {
                    $isApproved = TypeAs::bool($pr['isApproved'] ?? false);
                    $baseRefName = TypeAs::string($pr['baseRefName'] ?? '');

                    return $isApproved && ! in_array($baseRefName, ['master', 'main'], true);
                });
            })
            ->when($this->hideDrafts, function (Collection $prs): Collection {
                return $prs->reject(function (array $pr): bool {
                    return TypeAs::bool($pr['isDraft'] ?? false);
                });
            })
            ->when($this->search !== '', function (Collection $prs): Collection {
                $search = strtolower($this->search);

                return $prs->filter(function (array $pr) use ($search): bool {
                    $title = strtolower(TypeAs::string($pr['title'] ?? ''));
                    $branch = strtolower(TypeAs::string($pr['headRefName'] ?? ''));
                    $target = strtolower(TypeAs::string($pr['baseRefName'] ?? ''));
                    $number = (string) ($pr['number'] ?? '');
                    $author = strtolower(TypeAs::string($pr['author']['login'] ?? ''));
                    $repo = strtolower(TypeAs::string($pr['repository']['name'] ?? ''));

                    return str_contains($title, $search)
                        || str_contains($branch, $search)
                        || str_contains($target, $search)
                        || str_contains($number, $search)
                        || str_contains($author, $search)
                        || str_contains($repo, $search);
                });
            })
            ->values();

        return $filtered;
    }

    protected function loadData(): void
    {
        if ($this->token === '') {
            return;
        }

        try {
            $github = new GitHubService($this->token, $this->organizations);
            $this->currentUser = $github->getCurrentUser();

            if ($this->type === 'my-prs') {
                $this->pullRequests = $github->getPullRequestsByAuthors($this->selectedAuthors, $this->selectedRepositories);
            } else {
                $this->pullRequests = $github->getPullRequestsForReviewers($this->selectedReviewers, $this->selectedRepositories);
            }

            $this->error = null;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        }
    }

    /**
     * Check if we're filtering by only the current user (to hide author column).
     */
    public function isFilteringBySelf(): bool
    {
        if ($this->type === 'my-prs') {
            return count($this->selectedAuthors) === 1
                && ($this->selectedAuthors[0] === '@me' || $this->selectedAuthors[0] === $this->currentUser);
        }

        return count($this->selectedReviewers) === 1
            && ($this->selectedReviewers[0] === '@me' || $this->selectedReviewers[0] === $this->currentUser);
    }

    public function placeholder(): View
    {
        return view('livewire.pull-requests-table-placeholder', [
            'type' => $this->type,
        ]);
    }

    public function render(): View
    {
        return view('livewire.pull-requests-table');
    }
}
