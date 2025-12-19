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

    public bool $configured = false;

    /** @var Collection<string, Collection<int, array{name: string, nameWithOwner: string}>> */
    public Collection $repositories;

    /** @var array<int, string> */
    #[Url]
    public array $selectedRepositories = [];

    public function mount(): void
    {
        $this->repositories = collect();
    }

    public function configure(string $token, string $organizations = ''): void
    {
        $this->token = $token;
        $this->organizations = $organizations;
        $this->configured = true;
        $this->loadRepositories();
    }

    protected function loadRepositories(): void
    {
        if ($this->token === '') {
            $this->repositories = collect();

            return;
        }

        try {
            $github = new GitHubService($this->token, $this->organizations);
            $this->repositories = $github->getAccessibleRepositories();
        } catch (\Exception) {
            $this->repositories = collect();
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
