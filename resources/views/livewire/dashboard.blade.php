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
            <div class="flex items-center gap-3">
                <flux:radio.group wire:model.live="refreshInterval" variant="segmented" size="sm">
                    <flux:radio value="0" label="Off" />
                    <flux:radio value="3" label="1m" />
                    <flux:radio value="180" label="3m" />
                    <flux:radio value="300" label="5m" />
                    <flux:radio value="600" label="10m" />
                </flux:radio.group>
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
                <flux:switch wire:model.live="hideApprovedToMaster" label="Hide Approved (to master)" />
                <flux:switch wire:model.live="hideApprovedToOther" label="Hide Approved (to other)" />
                <flux:switch wire:model.live="hideDrafts" label="Hide Drafts" />
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
