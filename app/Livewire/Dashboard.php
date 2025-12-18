<?php

namespace App\Livewire;

use App\Services\GitHubService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Smpita\TypeAs\TypeAs;

class Dashboard extends Component
{
    /** @var Collection<int, array<string, mixed>> */
    public Collection $myPrs;

    /** @var Collection<int, array<string, mixed>> */
    public Collection $reviewRequests;

    public ?string $currentUser = null;

    public ?string $error = null;

    public bool $loading = true;

    public ?string $lastUpdatedAt = null;

    #[Url]
    public bool $hideApprovedToMaster = false;

    #[Url]
    public bool $hideApprovedToOther = false;

    #[Url]
    public bool $hideDrafts = false;

    #[Url]
    public int $refreshInterval = 180;

    public function mount(GitHubService $github): void
    {
        /** @var Collection<int, array<string, mixed>> $emptyCollection */
        $emptyCollection = collect();
        $this->myPrs = $emptyCollection;
        $this->reviewRequests = $emptyCollection;
        $this->loadPullRequests($github);
    }

    public function refresh(): void
    {
        $this->loading = true;
        $this->error = null;
        $this->loadPullRequests(app(GitHubService::class));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function filteredMyPrs(): Collection
    {
        return $this->applyFilters($this->myPrs);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function filteredReviewRequests(): Collection
    {
        return $this->applyFilters($this->reviewRequests);
    }

    #[Computed]
    public function lastUpdatedAgo(): string
    {
        if ($this->lastUpdatedAt === null) {
            return 'never';
        }

        return Carbon::parse($this->lastUpdatedAt)->diffForHumans();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $prs
     * @return Collection<int, array<string, mixed>>
     */
    protected function applyFilters(Collection $prs): Collection
    {
        /** @var Collection<int, array<string, mixed>> $filtered */
        $filtered = $prs
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
            ->values();

        return $filtered;
    }

    protected function loadPullRequests(GitHubService $github): void
    {
        try {
            $this->currentUser = $github->getCurrentUser();
            $this->myPrs = $github->getMyPullRequests();
            $this->reviewRequests = $github->getReviewRequests();
            $this->lastUpdatedAt = now()->toIso8601String();
            $this->loading = false;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->loading = false;
        }
    }

    public function render(): View
    {
        return view('livewire.dashboard');
    }
}
