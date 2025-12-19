<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
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

    public function configure(string $token, string $organizations = ''): void
    {
        $this->token = $token;
        $this->organizations = $organizations;
        $this->configured = true;
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
