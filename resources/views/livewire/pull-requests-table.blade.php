<div
    @if($refreshInterval > 0) wire:poll.{{ $refreshInterval }}s="poll" @endif
    x-data="{ isLoading: false }"
    x-on:manual-refresh-start.window="isLoading = true"
    x-on:manual-refresh-end.window="isLoading = false"
>
    @php
        $count = $this->filteredPullRequests->count();
        $shortTime = function ($date) {
            $carbon = \Carbon\Carbon::parse($date);
            $diff = (int) $carbon->diffInMinutes(now());
            if ($diff < 60) return $diff . 'm';
            $hours = (int) $carbon->diffInHours(now());
            if ($hours < 24) return $hours . 'h';
            $days = (int) $carbon->diffInDays(now());
            if ($days < 30) return $days . 'd';
            $months = (int) $carbon->diffInMonths(now());
            return $months . 'mo';
        };
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
        $showAuthorColumn = !$isMyPrs || !$this->isFilteringBySelf();
    @endphp

    {{-- Skeleton shown during manual refresh --}}
    <template x-if="isLoading">
        <div>
            @include('livewire.pull-requests-table-placeholder', ['type' => $type])
        </div>
    </template>

    {{-- Actual content hidden during manual refresh --}}
    <div x-show="!isLoading">
    {{-- Heading with count --}}
    @php
        $title = $isMyPrs ? 'Pull Requests' : 'Review Requests';
        $icon = $isMyPrs ? 'check-circle' : 'eye';
        $iconColor = $isMyPrs ? 'text-green-400' : 'text-yellow-400';
    @endphp
    <div class="mb-4 flex items-center gap-2">
        <flux:icon :name="$icon" :class="$iconColor" />
        <flux:heading size="lg">{{ $title }}</flux:heading>
        <flux:badge size="sm" color="zinc">{{ $count }}</flux:badge>
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
                        <th class="px-4 py-3 font-medium text-center" title="Unresolved Comments">Comments</th>
                        <th class="px-4 py-3 font-medium">PR</th>
                        <th class="px-4 py-3 font-medium">Title</th>
                        <th class="px-4 py-3 font-medium">Branch</th>
                        <th class="px-4 py-3 font-medium">Target</th>
                        @if ($showAuthorColumn)
                            <th class="px-4 py-3 font-medium">Author</th>
                        @endif
                        <th class="px-4 py-3 font-medium">Reviewers</th>
                        <th class="px-4 py-3 font-medium text-right">Created</th>
                        <th class="px-4 py-3 font-medium text-right">Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($this->filteredPullRequests as $pr)
                        @php
                            $decision = $pr['reviewDecision'] ?? null;
                            $createdAt = \Carbon\Carbon::parse($pr['createdAt']);
                            $daysOld = (int) $createdAt->diffInDays(now());
                            $isStale = $daysOld >= 7;
                            $isVeryStale = $daysOld >= 14;
                        @endphp
                        <tr wire:key="pr-{{ $pr['number'] }}-{{ $pr['repository']['name'] }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors {{ $isVeryStale ? 'bg-red-50/50 dark:bg-red-950/20' : ($isStale ? 'bg-amber-50/50 dark:bg-amber-950/20' : '') }}">
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
                                <a href="{{ $pr['url'] }}" target="_blank" class="hover:underline">
                                    <span class="text-zinc-400">{{ $pr['repository']['name'] }}</span>
                                    <span class="text-zinc-500">#{{ $pr['number'] }}</span>
                                </a>
                            </td>

                            {{-- Title --}}
                            <td class="px-4 py-3 max-w-xs">
                                <a href="{{ $pr['url'] }}" target="_blank" class="text-accent hover:underline font-medium block truncate" title="{{ $pr['title'] }}">
                                    {{ $pr['title'] }}
                                </a>
                            </td>

                            {{-- Branch --}}
                            <td class="px-4 py-3 font-mono text-xs max-w-[200px]">
                                <div class="flex items-center gap-1 group" x-data="{ copied: false }">
                                    <span class="text-zinc-600 dark:text-zinc-300 block truncate" title="{{ $pr['headRefName'] }}">{{ $pr['headRefName'] }}</span>
                                    <button
                                        type="button"
                                        class="opacity-0 group-hover:opacity-100 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition-opacity shrink-0"
                                        title="Copy branch name"
                                        x-on:click="
                                            navigator.clipboard.writeText('{{ $pr['headRefName'] }}');
                                            copied = true;
                                            setTimeout(() => copied = false, 2000);
                                        "
                                    >
                                        <svg x-show="!copied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                        <svg x-show="copied" x-cloak class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </button>
                                </div>
                            </td>

                            {{-- Target --}}
                            <td class="px-4 py-3 font-mono text-xs max-w-[150px]">
                                <span class="text-zinc-500 block truncate" title="{{ $pr['baseRefName'] ?? 'main' }}">{{ $pr['baseRefName'] ?? 'main' }}</span>
                            </td>

                            {{-- Author --}}
                            @if ($showAuthorColumn)
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <img src="https://github.com/{{ $pr['author']['login'] ?? 'ghost' }}.png?size=32" alt="{{ $pr['author']['login'] ?? 'unknown' }}" class="size-5 rounded-full" />
                                        <span class="text-zinc-600 dark:text-zinc-300">{{ $pr['author']['login'] ?? 'unknown' }}</span>
                                    </div>
                                </td>
                            @endif

                            {{-- Reviewers --}}
                            <td class="px-4 py-3">
                                @php
                                    $ringColors = [
                                        'APPROVED' => 'ring-green-500',
                                        'CHANGES_REQUESTED' => 'ring-red-500',
                                        'COMMENTED' => 'ring-blue-500',
                                        'PENDING' => 'ring-zinc-400',
                                        'DISMISSED' => 'ring-zinc-500',
                                    ];
                                @endphp
                                <div class="flex flex-wrap gap-x-2 gap-y-1">
                                    @foreach ($pr['reviews'] ?? [] as $review)
                                        @php
                                            $reviewState = $review['state'] ?? 'PENDING';
                                            $avatarUrl = $review['author']['avatarUrl'] ?? null;
                                            $login = $review['author']['login'] ?? 'unknown';
                                        @endphp
                                        <span class="inline-flex items-center gap-1 text-xs whitespace-nowrap {{ $stateColors[$reviewState] ?? 'text-zinc-400' }}" title="{{ $login }}: {{ $reviewState }}">
                                            @if ($avatarUrl)
                                                <img src="{{ $avatarUrl }}" alt="{{ $login }}" class="size-5 rounded-full ring-2 {{ $ringColors[$reviewState] ?? 'ring-zinc-400' }}" />
                                            @endif
                                            <span>{{ $login }}</span>
                                        </span>
                                    @endforeach
                                </div>
                            </td>

                            {{-- Created --}}
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <span class="{{ $isVeryStale ? 'text-red-500' : ($isStale ? 'text-amber-500' : 'text-zinc-500') }}" title="{{ $createdAt->format('M j, Y') }}">
                                    {{ $shortTime($pr['createdAt']) }}
                                </span>
                            </td>

                            {{-- Updated --}}
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <span class="text-zinc-500" title="{{ \Carbon\Carbon::parse($pr['updatedAt'])->format('M j, Y g:ia') }}">
                                    {{ $shortTime($pr['updatedAt']) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    </div>
</div>
