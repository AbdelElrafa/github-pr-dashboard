<div
    class="min-h-screen"
    x-data="{
        settingsToken: localStorage.getItem('github_token') || '',
        settingsOrganizations: localStorage.getItem('github_organizations') || '',
        init() {
            $wire.configure(this.settingsToken, this.settingsOrganizations);
        }
    }"
>
    <div class="max-w-full mx-auto px-4 py-8">
        <header class="mb-6 flex items-center justify-between">
            <div>
                <flux:heading size="xl">GitHub PR Assistant</flux:heading>
            </div>
            <div class="flex items-end gap-3">
                <div class="flex flex-col items-start gap-1">
                    <flux:text size="xs" class="text-zinc-500">Auto-refresh</flux:text>
                    <flux:radio.group wire:model.live="refreshInterval" variant="segmented" size="sm">
                        <flux:radio value="0" label="Off" />
                        <flux:radio value="3" label="1m" />
                        <flux:radio value="180" label="3m" />
                        <flux:radio value="300" label="5m" />
                        <flux:radio value="600" label="10m" />
                    </flux:radio.group>
                </div>
                <flux:button
                    wire:click="refresh"
                    x-on:click="$dispatch('manual-refresh-start')"
                    size="sm"
                    icon="arrow-path"
                />
                <flux:dropdown align="end" x-data="{ open: false }">
                    <flux:button icon="cog-6-tooth" variant="ghost" size="sm" />
                    <flux:menu class="w-80">
                        <div class="p-4 space-y-4">
                            <div>
                                <flux:heading size="sm">GitHub Settings</flux:heading>
                            </div>

                            <flux:input
                                type="password"
                                label="GitHub Token"
                                x-model="settingsToken"
                                placeholder="ghp_xxxxxxxxxxxx"
                                size="sm"
                            />

                            <flux:input
                                label="Organizations"
                                x-model="settingsOrganizations"
                                placeholder="org1, org2 (optional)"
                                size="sm"
                            />

                            <div class="flex gap-2 pt-2">
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    x-on:click="
                                        localStorage.removeItem('github_token');
                                        localStorage.removeItem('github_organizations');
                                        settingsToken = '';
                                        settingsOrganizations = '';
                                    "
                                >
                                    Clear
                                </flux:button>
                                <flux:spacer />
                                <flux:button
                                    variant="primary"
                                    size="sm"
                                    x-on:click="
                                        localStorage.setItem('github_token', settingsToken);
                                        localStorage.setItem('github_organizations', settingsOrganizations);
                                        $wire.configure(settingsToken, settingsOrganizations);
                                    "
                                >
                                    Save
                                </flux:button>
                            </div>
                        </div>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </header>

        {{-- Setup Prompt (only show when no token configured) --}}
        @if ($configured && !$token)
            <flux:callout icon="exclamation-triangle" variant="warning" class="mb-6">
                <flux:callout.heading>GitHub Token Required</flux:callout.heading>
                <flux:callout.text>
                    Click the <flux:icon name="cog-6-tooth" variant="micro" class="inline" /> settings icon to add your GitHub token.
                    Run <code class="font-mono text-sm">gh auth token</code> to get your token.
                </flux:callout.text>
            </flux:callout>
        @endif

        {{-- Filters --}}
        @if ($token)
            <div class="mb-6 flex flex-wrap items-center gap-6">
                <flux:text size="sm" class="font-medium">Filters:</flux:text>

                {{-- Repository Filter (Searchable Pillbox) --}}
                @if ($repositories->isNotEmpty())
                    @php
                        $allRepos = $repositories->flatMap(fn($repos, $org) => $repos->map(fn($repo) => [
                            'name' => $repo['name'],
                            'nameWithOwner' => $repo['nameWithOwner'],
                            'org' => $org,
                        ]))->values()->toArray();
                    @endphp
                    <div
                        x-data="{
                            search: '',
                            open: false,
                            repos: {{ Js::from($allRepos) }},
                            get selected() {
                                return $wire.selectedRepositories || [];
                            },
                            get filtered() {
                                if (!this.search) return this.repos;
                                const s = this.search.toLowerCase();
                                return this.repos.filter(r => r.name.toLowerCase().includes(s) || r.org.toLowerCase().includes(s));
                            },
                            toggle(nameWithOwner) {
                                const current = [...this.selected];
                                const idx = current.indexOf(nameWithOwner);
                                if (idx === -1) {
                                    current.push(nameWithOwner);
                                } else {
                                    current.splice(idx, 1);
                                }
                                $wire.set('selectedRepositories', current);
                                this.search = '';
                            },
                            remove(nameWithOwner) {
                                const current = [...this.selected];
                                const idx = current.indexOf(nameWithOwner);
                                if (idx !== -1) {
                                    current.splice(idx, 1);
                                    $wire.set('selectedRepositories', current);
                                }
                            },
                            isSelected(nameWithOwner) {
                                return this.selected.includes(nameWithOwner);
                            },
                            getRepoName(nameWithOwner) {
                                const repo = this.repos.find(r => r.nameWithOwner === nameWithOwner);
                                return repo ? repo.name : nameWithOwner;
                            }
                        }"
                        class="relative flex-1 max-w-xl"
                        x-on:click.outside="open = false"
                    >
                        {{-- Pills and Search Input --}}
                        <div
                            class="flex flex-wrap items-center gap-1.5 px-2 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 min-h-[38px] cursor-text"
                            x-on:click="$refs.searchInput.focus(); open = true"
                        >
                            {{-- Selected Pills --}}
                            <template x-for="repo in selected" :key="repo">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-zinc-100 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300">
                                    <span x-text="getRepoName(repo)"></span>
                                    <button
                                        type="button"
                                        x-on:click.stop="remove(repo)"
                                        class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
                                    >
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </span>
                            </template>

                            {{-- Search Input --}}
                            <input
                                x-ref="searchInput"
                                x-model="search"
                                x-on:focus="open = true"
                                x-on:keydown.escape="open = false; search = ''"
                                type="text"
                                placeholder="All repositories - Click to filter..."
                                class="flex-1 min-w-[120px] bg-transparent border-0 p-0 text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-0"
                            />
                        </div>

                        {{-- Dropdown --}}
                        <div
                            x-show="open"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute z-50 mt-1 w-full max-h-60 overflow-y-auto rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-lg"
                        >
                            <template x-if="filtered.length === 0">
                                <div class="px-3 py-2 text-sm text-zinc-500">No repositories found</div>
                            </template>
                            <template x-for="repo in filtered" :key="repo.nameWithOwner">
                                <button
                                    type="button"
                                    x-on:click="toggle(repo.nameWithOwner)"
                                    class="w-full flex items-center gap-2 px-3 py-2 text-sm text-left hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                    :class="{ 'bg-zinc-50 dark:bg-zinc-700/50': isSelected(repo.nameWithOwner) }"
                                >
                                    <svg
                                        x-show="isSelected(repo.nameWithOwner)"
                                        class="w-4 h-4 text-accent shrink-0"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span x-show="!isSelected(repo.nameWithOwner)" class="w-4 shrink-0"></span>
                                    <span class="text-zinc-500 dark:text-zinc-400" x-text="repo.org + '/'"></span>
                                    <span class="text-zinc-900 dark:text-zinc-100" x-text="repo.name"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                @endif

                <flux:dropdown>
                    <flux:button variant="subtle" size="sm" icon:trailing="chevron-down">
                        @php
                            $activeFilters = collect([
                                $hideApprovedToMaster ? 'Approved → main' : null,
                                $hideApprovedToOther ? 'Approved → other' : null,
                                $hideDrafts ? 'Drafts' : null,
                            ])->filter()->count();
                        @endphp
                        @if ($activeFilters === 0)
                            Hide
                        @else
                            Hide ({{ $activeFilters }})
                        @endif
                    </flux:button>
                    <flux:menu>
                        <flux:menu.checkbox wire:model.live="hideApprovedToMaster">Approved → main</flux:menu.checkbox>
                        <flux:menu.checkbox wire:model.live="hideApprovedToOther">Approved → other</flux:menu.checkbox>
                        <flux:menu.checkbox wire:model.live="hideDrafts">Drafts</flux:menu.checkbox>
                    </flux:menu>
                </flux:dropdown>
            </div>

            {{-- PR Tables --}}
            <div class="space-y-8">
                <livewire:pull-requests-table
                    type="my-prs"
                    :token="$token"
                    :organizations="$organizations"
                    :hide-approved-to-master="$hideApprovedToMaster"
                    :hide-approved-to-other="$hideApprovedToOther"
                    :hide-drafts="$hideDrafts"
                    :refresh-interval="$refreshInterval"
                    :selected-repositories="$selectedRepositories"
                    wire:key="my-prs"
                />

                <livewire:pull-requests-table
                    type="review-requests"
                    :token="$token"
                    :organizations="$organizations"
                    :hide-approved-to-master="$hideApprovedToMaster"
                    :hide-approved-to-other="$hideApprovedToOther"
                    :hide-drafts="$hideDrafts"
                    :refresh-interval="$refreshInterval"
                    :selected-repositories="$selectedRepositories"
                    wire:key="review-requests"
                />
            </div>
        @endif
    </div>

    {{-- Theme Toggle (Bottom Left) --}}
    <div class="fixed bottom-4 left-4" x-data>
        <flux:dropdown align="start" position="top">
            <flux:button variant="filled" square aria-label="Toggle theme">
                <flux:icon.sun x-show="$flux.appearance === 'light'" variant="mini" />
                <flux:icon.moon x-show="$flux.appearance === 'dark'" variant="mini" />
                <flux:icon.moon x-show="$flux.appearance === 'system' && $flux.dark" variant="mini" />
                <flux:icon.sun x-show="$flux.appearance === 'system' && ! $flux.dark" variant="mini" />
            </flux:button>
            <flux:menu>
                <flux:menu.item icon="sun" x-on:click="$flux.appearance = 'light'">Light</flux:menu.item>
                <flux:menu.item icon="moon" x-on:click="$flux.appearance = 'dark'">Dark</flux:menu.item>
                <flux:menu.item icon="computer-desktop" x-on:click="$flux.appearance = 'system'">System</flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </div>
</div>
