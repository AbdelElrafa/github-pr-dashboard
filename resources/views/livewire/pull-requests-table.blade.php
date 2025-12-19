<div
    @if($refreshInterval > 0) wire:poll.{{ $refreshInterval }}s="poll" @endif
    x-data="{ isLoading: false }"
    x-on:manual-refresh-start.window="isLoading = true"
    x-on:manual-refresh-end.window="isLoading = false"
>
    @php
        $stateColors = [
            'APPROVED' => 'text-green-400',
            'CHANGES_REQUESTED' => 'text-red-400',
            'COMMENTED' => 'text-blue-400',
            'PENDING' => 'text-zinc-400',
            'DISMISSED' => 'text-zinc-500',
        ];
        $stateIcons = [
            'APPROVED' => '✓',
            'CHANGES_REQUESTED' => '✗',
            'COMMENTED' => '💬',
            'PENDING' => '○',
            'DISMISSED' => '—',
        ];
        $ciColors = [
            'SUCCESS' => 'text-green-400',
            'FAILURE' => 'text-red-400',
            'ERROR' => 'text-red-400',
            'PENDING' => 'text-yellow-400',
            'EXPECTED' => 'text-yellow-400',
        ];
        $ciIcons = [
            'SUCCESS' => '✓',
            'FAILURE' => '✗',
            'ERROR' => '✗',
            'PENDING' => '◐',
            'EXPECTED' => '◐',
        ];
        $isMyPrs = $type === 'my-prs';
        $title = $isMyPrs ? 'My Pull Requests' : 'Review Requests';
        $icon = $isMyPrs ? 'check-circle' : 'eye';
        $iconColor = $isMyPrs ? 'text-green-400' : 'text-yellow-400';
    @endphp

    {{-- Skeleton shown during manual refresh --}}
    <template x-if="isLoading">
        <div>
            @include('livewire.pull-requests-table-placeholder', ['type' => $type])
        </div>
    </template>

    {{-- Actual content hidden during manual refresh --}}
    <div x-show="!isLoading">
        <div class="mb-4 flex items-center gap-2">
            <flux:icon :name="$icon" :class="$iconColor" />
            <flux:heading size="lg">{{ $title }}</flux:heading>
            <flux:badge size="sm">{{ $this->filteredPullRequests->count() }}</flux:badge>
        </div>

    @if ($error)
        <flux:callout variant="danger" icon="x-circle" class="mb-4">
            <flux:callout.heading>Error loading pull requests</flux:callout.heading>
            <flux:callout.text>{{ $error }}</flux:callout.text>
        </flux:callout>
    @endif

    @if ($this->filteredPullRequests->isEmpty())
        <div class="p-8 text-center rounded-lg border border-dashed">
            <flux:text>No {{ $isMyPrs ? 'pull requests' : 'review requests' }} match your filters</flux:text>
        </div>
    @else
        <div class="overflow-x-auto rounded-lg border" wire:key="pull-requests-table-{{ $type }}-{{ Str::random(10) }}">
            <table class="w-full text-sm">
                <thead class="text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium text-center" title="CI/CD Status">CI</th>
                        <th class="px-4 py-3 font-medium text-center" title="Merge Conflicts">Merge</th>
                        <th class="px-4 py-3 font-medium text-center">Comments</th>
                        <th class="px-4 py-3 font-medium">PR</th>
                        @if (!$isMyPrs)
                            <th class="px-4 py-3 font-medium">Author</th>
                        @endif
                        <th class="px-4 py-3 font-medium">Title</th>
                        <th class="px-4 py-3 font-medium">Branch</th>
                        <th class="px-4 py-3 font-medium">Target</th>
                        <th class="px-4 py-3 font-medium">Reviewers</th>
                        <th class="px-4 py-3 font-medium text-right">Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($this->filteredPullRequests as $pr)
                        @php $decision = $pr['reviewDecision'] ?? null; @endphp
                        <tr wire:key="pr-{{ $pr['number'] }}-{{ $pr['repository']['name'] }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors">
                            {{-- Status --}}
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($pr['isDraft'])
                                    <flux:badge size="sm" color="zinc">Draft</flux:badge>
                                @elseif ($decision === 'APPROVED')
                                    <flux:badge size="sm" color="green">Approved</flux:badge>
                                @elseif ($decision === 'CHANGES_REQUESTED')
                                    <flux:badge size="sm" color="red">Changes</flux:badge>
                                @elseif ($decision === 'REVIEW_REQUIRED')
                                    <flux:badge size="sm" color="yellow">Review Required</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc">Pending</flux:badge>
                                @endif
                            </td>

                            {{-- CI Status --}}
                            <td class="px-4 py-3 text-center">
                                @if ($pr['ciStatus'])
                                    <span class="{{ $ciColors[$pr['ciStatus']] ?? 'text-zinc-500' }}" title="{{ $pr['ciStatus'] }}">
                                        {{ $ciIcons[$pr['ciStatus']] ?? '?' }}
                                    </span>
                                @else
                                    <span class="text-zinc-600">—</span>
                                @endif
                            </td>

                            {{-- Merge Status --}}
                            <td class="px-4 py-3 text-center">
                                @if ($pr['mergeable'] === 'CONFLICTING')
                                    <span class="text-red-400" title="Has merge conflicts">⚠</span>
                                @elseif ($pr['mergeable'] === 'MERGEABLE')
                                    <span class="text-green-400" title="Ready to merge">✓</span>
                                @else
                                    <span class="text-zinc-600" title="{{ $pr['mergeable'] ?? 'Unknown' }}">—</span>
                                @endif
                            </td>

                            {{-- Unresolved Comments --}}
                            <td class="px-4 py-3 text-center">
                                @if (($pr['unresolvedCount'] ?? 0) > 0)
                                    <span class="text-orange-400 font-medium">{{ $pr['unresolvedCount'] }}</span>
                                @else
                                    <span class="text-zinc-600">—</span>
                                @endif
                            </td>

                            {{-- PR --}}
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="text-zinc-400">{{ $pr['repository']['name'] }}</span>
                                <span class="text-zinc-500">#{{ $pr['number'] }}</span>
                            </td>

                            {{-- Author (only for review requests) --}}
                            @if (!$isMyPrs)
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="text-zinc-600 dark:text-zinc-300">{{ $pr['author']['login'] ?? 'unknown' }}</span>
                                </td>
                            @endif

                            {{-- Title --}}
                            <td class="px-4 py-3 max-w-xs">
                                <a href="{{ $pr['url'] }}" target="_blank" class="text-accent hover:underline font-medium block truncate" title="{{ $pr['title'] }}">
                                    {{ $pr['title'] }}
                                </a>
                            </td>

                            {{-- Branch --}}
                            <td class="px-4 py-3 font-mono text-xs max-w-[200px]">
                                <span class="text-zinc-600 dark:text-zinc-300 block truncate" title="{{ $pr['headRefName'] }}">{{ $pr['headRefName'] }}</span>
                            </td>

                            {{-- Target --}}
                            <td class="px-4 py-3 font-mono text-xs max-w-[150px]">
                                <span class="text-zinc-500 block truncate" title="{{ $pr['baseRefName'] ?? 'main' }}">{{ $pr['baseRefName'] ?? 'main' }}</span>
                            </td>

                            {{-- Reviewers --}}
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-x-2 gap-y-1">
                                    @foreach ($pr['reviews'] ?? [] as $review)
                                        @php $reviewState = $review['state'] ?? 'PENDING'; @endphp
                                        <span class="inline-flex items-center gap-1 text-xs whitespace-nowrap {{ $stateColors[$reviewState] ?? 'text-zinc-400' }}" title="{{ $reviewState }}">
                                            <span>{{ $stateIcons[$reviewState] ?? '?' }}</span>
                                            <span>{{ $review['author']['login'] ?? 'unknown' }}</span>
                                        </span>
                                    @endforeach
                                </div>
                            </td>

                            {{-- Updated --}}
                            <td class="px-4 py-3 text-right text-zinc-500 whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($pr['updatedAt'])->diffForHumans() }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    </div>
</div>
