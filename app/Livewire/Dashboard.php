<?php

namespace App\Livewire;

use App\Services\GitHubService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

class Dashboard extends Component
{
    public string $token = '';

    public string $organizations = '';

    #[Url]
    public bool $hideApprovedToMaster = false;

    #[Url]
    public bool $hideApprovedToOther = false;

    #[Url]
    public bool $hideDrafts = false;

    #[Url]
    public int $refreshInterval = 180;

    /** @var array<int, string> */
    #[Url]
    public array $selectedAuthors = [];

    /** @var array<int, string> */
    #[Url]
    public array $selectedReviewers = [];

    public bool $configured = false;

    public ?string $currentUser = null;

    /** @var Collection<string, Collection<int, array{name: string, nameWithOwner: string}>> */
    public Collection $repositories;

    /** @var Collection<int, array{login: string, avatarUrl: string}> */
    public Collection $members;

    /** @var array<int, string> */
    #[Url]
    public array $selectedRepositories = [];

    public string $search = '';

    public function mount(): void
    {
        $this->repositories = collect();
        $this->members = collect();
    }

    public function configure(string $token, string $organizations = ''): void
    {
        $this->token = $token;
        $this->organizations = $organizations;
        $this->configured = true;
        $this->loadInitialData();
    }

    protected function loadInitialData(): void
    {
        if ($this->token === '') {
            $this->repositories = collect();
            $this->members = collect();
            $this->currentUser = null;

            return;
        }

        try {
            $github = new GitHubService($this->token, $this->organizations);
            $this->currentUser = $github->getCurrentUser();
            $this->repositories = $github->getAccessibleRepositories();
            $this->members = $github->getOrganizationMembers();

            // Default to current user if no authors/reviewers selected
            if ($this->currentUser !== null) {
                if (empty($this->selectedAuthors)) {
                    $this->selectedAuthors = [$this->currentUser];
                }
                if (empty($this->selectedReviewers)) {
                    $this->selectedReviewers = [$this->currentUser];
                }
            }
        } catch (\Exception) {
            $this->repositories = collect();
            $this->members = collect();
            $this->currentUser = null;
        }
    }

    public function refresh(): void
    {
        $this->dispatch('manual-refresh-tables');
    }

    public function render(): View
    {
        return view('livewire.dashboard');
    }
}
