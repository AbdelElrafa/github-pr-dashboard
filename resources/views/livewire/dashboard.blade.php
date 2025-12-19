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
                        <flux:radio value="60" label="1m" />
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
            <div class="mb-6 p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                <div class="flex items-center gap-6">
                    {{-- Search Input --}}
                    <div class="flex-1">
                        <flux:input
                            wire:model.live.debounce.250ms="search"
                            wire:target="search"
                            placeholder="Search PRs..."
                            icon="magnifying-glass"
                            clearable
                            :loading="false"
                        />
                    </div>

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
                        class="flex-1 max-w-xl shrink-0"
                        x-on:click.outside="open = false"
                    >
                        {{-- Pills and Search Input --}}
                        <div
                            class="flex flex-wrap items-center gap-1.5 px-3 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 min-h-[40px] cursor-text"
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
            </div>

            {{-- PR Tables --}}
            <div class="space-y-8">
                {{-- Pull Requests Section --}}
                <div class="relative">
                    {{-- Author Filter (positioned to align with heading inside table) --}}
                    @if ($members->isNotEmpty())
                        <div class="absolute right-0 top-0 z-10">
                            @php $allMembers = $members->toArray(); @endphp
                            <div
                                x-data="{
                                    search: '',
                                    open: false,
                                    members: {{ Js::from($allMembers) }},
                                    get selected() {
                                        return $wire.selectedAuthors || [];
                                    },
                                    get filtered() {
                                        if (!this.search) return this.members;
                                        const s = this.search.toLowerCase();
                                        return this.members.filter(m => m.login.toLowerCase().includes(s));
                                    },
                                    toggle(login) {
                                        const current = [...this.selected];
                                        const idx = current.indexOf(login);
                                        if (idx === -1) {
                                            current.push(login);
                                        } else {
                                            current.splice(idx, 1);
                                        }
                                        $wire.set('selectedAuthors', current);
                                        this.search = '';
                                    },
                                    remove(login) {
                                        const current = [...this.selected];
                                        const idx = current.indexOf(login);
                                        if (idx !== -1) {
                                            current.splice(idx, 1);
                                            $wire.set('selectedAuthors', current);
                                        }
                                    },
                                    isSelected(login) {
                                        return this.selected.includes(login);
                                    },
                                    getAvatarUrl(login) {
                                        const member = this.members.find(m => m.login === login);
                                        return member ? member.avatarUrl : null;
                                    }
                                }"
                                class="relative flex-1 max-w-sm"
                                x-on:click.outside="open = false"
                            >
                                <div
                                    class="flex flex-wrap items-center gap-1.5 px-2 py-1 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 min-h-[32px] cursor-text"
                                    x-on:click="$refs.authorSearchInput.focus(); open = true"
                                >
                                    <template x-for="login in selected" :key="login">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-zinc-100 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300">
                                            <img x-show="getAvatarUrl(login)" :src="getAvatarUrl(login)" class="w-4 h-4 rounded-full" />
                                            <span x-text="login"></span>
                                            <button type="button" x-on:click.stop="remove(login)" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>
                                    <input
                                        x-ref="authorSearchInput"
                                        x-model="search"
                                        x-on:focus="open = true"
                                        x-on:keydown.escape="open = false; search = ''"
                                        type="text"
                                        placeholder="Filter by author..."
                                        class="flex-1 min-w-[100px] bg-transparent border-0 p-0 text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-0"
                                    />
                                </div>
                                <div
                                    x-show="open"
                                    x-transition
                                    class="absolute right-0 z-50 mt-1 w-64 max-h-60 overflow-y-auto rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-lg"
                                >
                                    <template x-if="filtered.length === 0">
                                        <div class="px-3 py-2 text-sm text-zinc-500">No members found</div>
                                    </template>
                                    <template x-for="member in filtered" :key="member.login">
                                        <button
                                            type="button"
                                            x-on:click="toggle(member.login)"
                                            class="w-full flex items-center gap-2 px-3 py-2 text-sm text-left hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                            :class="{ 'bg-zinc-50 dark:bg-zinc-700/50': isSelected(member.login) }"
                                        >
                                            <svg x-show="isSelected(member.login)" class="w-4 h-4 text-accent shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span x-show="!isSelected(member.login)" class="w-4 shrink-0"></span>
                                            <img :src="member.avatarUrl" class="w-5 h-5 rounded-full" x-show="member.avatarUrl" />
                                            <span class="text-zinc-900 dark:text-zinc-100" x-text="member.login"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    @endif

                    <livewire:pull-requests-table
                        type="my-prs"
                        :token="$token"
                        :organizations="$organizations"
                        :hide-approved-to-master="$hideApprovedToMaster"
                        :hide-approved-to-other="$hideApprovedToOther"
                        :hide-drafts="$hideDrafts"
                        :refresh-interval="$refreshInterval"
                        :selected-repositories="$selectedRepositories"
                        :selected-authors="$selectedAuthors"
                        :selected-reviewers="$selectedReviewers"
                        :search="$search"
                        wire:key="my-prs-{{ implode(',', $selectedAuthors) }}"
                    />
                </div>

                {{-- Review Requests Section --}}
                <div class="relative">
                    {{-- Reviewer Filter (positioned to align with heading inside table) --}}
                    @if ($members->isNotEmpty())
                        <div class="absolute right-0 top-0 z-10">
                            @php $allMembers = $members->toArray(); @endphp
                            <div
                                x-data="{
                                    search: '',
                                    open: false,
                                    members: {{ Js::from($allMembers) }},
                                    get selected() {
                                        return $wire.selectedReviewers || [];
                                    },
                                    get filtered() {
                                        if (!this.search) return this.members;
                                        const s = this.search.toLowerCase();
                                        return this.members.filter(m => m.login.toLowerCase().includes(s));
                                    },
                                    toggle(login) {
                                        const current = [...this.selected];
                                        const idx = current.indexOf(login);
                                        if (idx === -1) {
                                            current.push(login);
                                        } else {
                                            current.splice(idx, 1);
                                        }
                                        $wire.set('selectedReviewers', current);
                                        this.search = '';
                                    },
                                    remove(login) {
                                        const current = [...this.selected];
                                        const idx = current.indexOf(login);
                                        if (idx !== -1) {
                                            current.splice(idx, 1);
                                            $wire.set('selectedReviewers', current);
                                        }
                                    },
                                    isSelected(login) {
                                        return this.selected.includes(login);
                                    },
                                    getAvatarUrl(login) {
                                        const member = this.members.find(m => m.login === login);
                                        return member ? member.avatarUrl : null;
                                    }
                                }"
                                class="relative flex-1 max-w-sm"
                                x-on:click.outside="open = false"
                            >
                                <div
                                    class="flex flex-wrap items-center gap-1.5 px-2 py-1 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 min-h-[32px] cursor-text"
                                    x-on:click="$refs.reviewerSearchInput.focus(); open = true"
                                >
                                    <template x-for="login in selected" :key="login">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-zinc-100 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300">
                                            <img x-show="getAvatarUrl(login)" :src="getAvatarUrl(login)" class="w-4 h-4 rounded-full" />
                                            <span x-text="login"></span>
                                            <button type="button" x-on:click.stop="remove(login)" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>
                                    <input
                                        x-ref="reviewerSearchInput"
                                        x-model="search"
                                        x-on:focus="open = true"
                                        x-on:keydown.escape="open = false; search = ''"
                                        type="text"
                                        placeholder="Filter by reviewer..."
                                        class="flex-1 min-w-[100px] bg-transparent border-0 p-0 text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-0"
                                    />
                                </div>
                                <div
                                    x-show="open"
                                    x-transition
                                    class="absolute right-0 z-50 mt-1 w-64 max-h-60 overflow-y-auto rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-lg"
                                >
                                    <template x-if="filtered.length === 0">
                                        <div class="px-3 py-2 text-sm text-zinc-500">No members found</div>
                                    </template>
                                    <template x-for="member in filtered" :key="member.login">
                                        <button
                                            type="button"
                                            x-on:click="toggle(member.login)"
                                            class="w-full flex items-center gap-2 px-3 py-2 text-sm text-left hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                            :class="{ 'bg-zinc-50 dark:bg-zinc-700/50': isSelected(member.login) }"
                                        >
                                            <svg x-show="isSelected(member.login)" class="w-4 h-4 text-accent shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span x-show="!isSelected(member.login)" class="w-4 shrink-0"></span>
                                            <img :src="member.avatarUrl" class="w-5 h-5 rounded-full" x-show="member.avatarUrl" />
                                            <span class="text-zinc-900 dark:text-zinc-100" x-text="member.login"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    @endif

                    <livewire:pull-requests-table
                        type="review-requests"
                        :token="$token"
                        :organizations="$organizations"
                        :hide-approved-to-master="$hideApprovedToMaster"
                        :hide-approved-to-other="$hideApprovedToOther"
                        :hide-drafts="$hideDrafts"
                        :refresh-interval="$refreshInterval"
                        :selected-repositories="$selectedRepositories"
                        :selected-authors="$selectedAuthors"
                        :selected-reviewers="$selectedReviewers"
                        :search="$search"
                        wire:key="review-requests-{{ implode(',', $selectedReviewers) }}"
                    />
                </div>
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
