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

    protected function loadData(): void
    {
        if ($this->token === '') {
            return;
        }

        try {
            $github = new GitHubService($this->token, $this->organizations);
            $this->currentUser = $github->getCurrentUser();

            if ($this->type === 'my-prs') {
                $this->pullRequests = $github->getMyPullRequests();
            } else {
                $this->pullRequests = $github->getReviewRequests();
            }

            $this->error = null;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        }
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
