<div
    class="min-h-screen bg-gray-900 text-gray-100"
    x-data="{
        interval: $wire.entangle('refreshInterval'),
        lastUpdatedAt: $wire.entangle('lastUpdatedAt'),
        lastUpdatedAgo: 'never'
    }"
    x-init="
        let refreshTimer = null;

        const updateAgo = () => { lastUpdatedAgo = lastUpdatedAt ? moment(lastUpdatedAt).fromNow() : 'never'; };
        updateAgo();
        setInterval(updateAgo, 1000);

        $watch('interval', (value) => {
            if (refreshTimer) clearInterval(refreshTimer);
            if (value > 0) {
                refreshTimer = setInterval(() => $wire.refresh(), value * 1000);
            }
        });
        if (interval > 0) {
            refreshTimer = setInterval(() => $wire.refresh(), interval * 1000);
        }
    "
>
    <div class="max-w-full mx-auto px-4 py-8">
        <header class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-white">GitHub PR Assistant</h1>
                @if ($currentUser)
                    <p class="text-gray-400 mt-1">Signed in as <span class="text-blue-400">{{ $currentUser }}</span></p>
                @endif
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm text-gray-500">
                    Updated <span x-text="lastUpdatedAgo"></span>
                </span>
                <select
                    wire:model.live="refreshInterval"
                    class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-300 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="0">Auto-refresh: Off</option>
                    <option value="60">Every 1 min</option>
                    <option value="180">Every 3 min</option>
                    <option value="300">Every 5 min</option>
                    <option value="600">Every 10 min</option>
                </select>
                <button
                    wire:click="refresh"
                    wire:loading.attr="disabled"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-800 rounded-lg font-medium transition-colors"
                >
                    <span wire:loading.remove wire:target="refresh">Refresh</span>
                    <span wire:loading wire:target="refresh">Loading...</span>
                </button>
            </div>
        </header>

        {{-- Filters --}}
        <div class="mb-6 flex flex-wrap items-center gap-4 p-4 bg-gray-800 rounded-lg border border-gray-700">
            <span class="text-sm text-gray-400 font-medium">Filters:</span>
            <label class="flex items-center gap-2 cursor-pointer">
                <input
                    type="checkbox"
                    wire:model.live="hideApprovedToMaster"
                    class="w-4 h-4 rounded border-gray-600 bg-gray-700 text-blue-500 focus:ring-blue-500 focus:ring-offset-gray-800"
                >
                <span class="text-sm text-gray-300">Hide Approved (to master)</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
                <input
                    type="checkbox"
                    wire:model.live="hideApprovedToOther"
                    class="w-4 h-4 rounded border-gray-600 bg-gray-700 text-blue-500 focus:ring-blue-500 focus:ring-offset-gray-800"
                >
                <span class="text-sm text-gray-300">Hide Approved (to other)</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
                <input
                    type="checkbox"
                    wire:model.live="hideDrafts"
                    class="w-4 h-4 rounded border-gray-600 bg-gray-700 text-blue-500 focus:ring-blue-500 focus:ring-offset-gray-800"
                >
                <span class="text-sm text-gray-300">Hide Drafts</span>
            </label>
        </div>

        @if ($error)
            <div class="mb-6 p-4 bg-red-900/50 border border-red-700 rounded-lg text-red-200">
                <p class="font-medium">Error fetching pull requests</p>
                <p class="text-sm mt-1">{{ $error }}</p>
                <p class="text-sm mt-2">Make sure you're authenticated with <code class="bg-gray-800 px-1 rounded">gh auth login</code></p>
            </div>
        @endif

        @php
            $decisionColors = [
                'APPROVED' => 'bg-green-900/50 text-green-300',
                'CHANGES_REQUESTED' => 'bg-red-900/50 text-red-300',
                'REVIEW_REQUIRED' => 'bg-yellow-900/50 text-yellow-300',
            ];
            $decisionLabels = [
                'APPROVED' => 'Approved',
                'CHANGES_REQUESTED' => 'Changes',
                'REVIEW_REQUIRED' => 'Review Required',
            ];
            $stateColors = [
                'APPROVED' => 'text-green-400',
                'CHANGES_REQUESTED' => 'text-red-400',
                'COMMENTED' => 'text-blue-400',
                'PENDING' => 'text-gray-400',
                'DISMISSED' => 'text-gray-500',
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
        @endphp

        <div class="space-y-8">
            {{-- My PRs --}}
            <section>
                <h2 class="text-xl font-semibold mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    My Pull Requests
                    <span class="text-sm text-gray-500">({{ $this->filteredMyPrs->count() }})</span>
                </h2>

                @if ($this->filteredMyPrs->isEmpty())
                    <div class="p-8 text-center text-gray-500 bg-gray-800/50 rounded-lg border border-gray-700 border-dashed">
                        <p>No pull requests match your filters</p>
                    </div>
                @else
                    <div class="overflow-x-auto rounded-lg border border-gray-700">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-800 text-gray-400 text-left">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Status</th>
                                    <th class="px-4 py-3 font-medium text-center" title="CI/CD Status">CI</th>
                                    <th class="px-4 py-3 font-medium text-center" title="Merge Conflicts">Merge</th>
                                    <th class="px-4 py-3 font-medium text-center">Comments</th>
                                    <th class="px-4 py-3 font-medium">PR</th>
                                    <th class="px-4 py-3 font-medium">Title</th>
                                    <th class="px-4 py-3 font-medium">Branch</th>
                                    <th class="px-4 py-3 font-medium">Target</th>
                                    <th class="px-4 py-3 font-medium">Reviewers</th>
                                    <th class="px-4 py-3 font-medium text-right">Updated</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                @foreach ($this->filteredMyPrs as $pr)
                                    @php $decision = $pr['reviewDecision'] ?? null; @endphp
                                    <tr wire:key="my-pr-{{ $pr['number'] }}-{{ $pr['repository']['name'] }}" class="bg-gray-800/50 hover:bg-gray-800 transition-colors">
                                        {{-- Status --}}
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @if ($pr['isDraft'])
                                                <span class="px-2 py-0.5 text-xs bg-gray-700 text-gray-300 rounded">Draft</span>
                                            @elseif ($decision)
                                                <span class="px-2 py-0.5 text-xs rounded {{ $decisionColors[$decision] ?? 'bg-gray-700 text-gray-300' }}">
                                                    {{ $decisionLabels[$decision] ?? $decision }}
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 text-xs bg-gray-700 text-gray-300 rounded">Pending</span>
                                            @endif
                                        </td>

                                        {{-- CI Status --}}
                                        <td class="px-4 py-3 text-center">
                                            @if ($pr['ciStatus'])
                                                <span class="{{ $ciColors[$pr['ciStatus']] ?? 'text-gray-500' }}" title="{{ $pr['ciStatus'] }}">
                                                    {{ $ciIcons[$pr['ciStatus']] ?? '?' }}
                                                </span>
                                            @else
                                                <span class="text-gray-600">—</span>
                                            @endif
                                        </td>

                                        {{-- Merge Status --}}
                                        <td class="px-4 py-3 text-center">
                                            @if ($pr['mergeable'] === 'CONFLICTING')
                                                <span class="text-red-400" title="Has merge conflicts">⚠</span>
                                            @elseif ($pr['mergeable'] === 'MERGEABLE')
                                                <span class="text-green-400" title="Ready to merge">✓</span>
                                            @else
                                                <span class="text-gray-600" title="{{ $pr['mergeable'] ?? 'Unknown' }}">—</span>
                                            @endif
                                        </td>

                                        {{-- Unresolved Comments --}}
                                        <td class="px-4 py-3 text-center">
                                            @if (($pr['unresolvedCount'] ?? 0) > 0)
                                                <span class="text-orange-400 font-medium">{{ $pr['unresolvedCount'] }}</span>
                                            @else
                                                <span class="text-gray-600">—</span>
                                            @endif
                                        </td>

                                        {{-- PR --}}
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="text-gray-400">{{ $pr['repository']['name'] }}</span>
                                            <span class="text-gray-500">#{{ $pr['number'] }}</span>
                                        </td>

                                        {{-- Title --}}
                                        <td class="px-4 py-3 max-w-xs">
                                            <a href="{{ $pr['url'] }}" target="_blank" class="text-blue-400 hover:text-blue-300 font-medium block truncate" title="{{ $pr['title'] }}">
                                                {{ $pr['title'] }}
                                            </a>
                                        </td>

                                        {{-- Branch --}}
                                        <td class="px-4 py-3 font-mono text-xs max-w-[200px]">
                                            <span class="text-gray-300 block truncate" title="{{ $pr['headRefName'] }}">{{ $pr['headRefName'] }}</span>
                                        </td>

                                        {{-- Target --}}
                                        <td class="px-4 py-3 font-mono text-xs max-w-[150px]">
                                            <span class="text-gray-500 block truncate" title="{{ $pr['baseRefName'] ?? 'main' }}">{{ $pr['baseRefName'] ?? 'main' }}</span>
                                        </td>

                                        {{-- Reviewers --}}
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap gap-x-2 gap-y-1">
                                                @foreach ($pr['reviews'] ?? [] as $review)
                                                    @php $reviewState = $review['state'] ?? 'PENDING'; @endphp
                                                    <span class="inline-flex items-center gap-1 text-xs whitespace-nowrap {{ $stateColors[$reviewState] ?? 'text-gray-400' }}" title="{{ $reviewState }}">
                                                        <span>{{ $stateIcons[$reviewState] ?? '?' }}</span>
                                                        <span>{{ $review['author']['login'] ?? 'unknown' }}</span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        </td>

                                        {{-- Updated --}}
                                        <td class="px-4 py-3 text-right text-gray-500 whitespace-nowrap">
                                            {{ \Carbon\Carbon::parse($pr['updatedAt'])->diffForHumans() }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Review Requests --}}
            <section>
                <h2 class="text-xl font-semibold mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Review Requests
                    <span class="text-sm text-gray-500">({{ $this->filteredReviewRequests->count() }})</span>
                </h2>

                @if ($this->filteredReviewRequests->isEmpty())
                    <div class="p-8 text-center text-gray-500 bg-gray-800/50 rounded-lg border border-gray-700 border-dashed">
                        <p>No review requests match your filters</p>
                    </div>
                @else
                    <div class="overflow-x-auto rounded-lg border border-gray-700">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-800 text-gray-400 text-left">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Status</th>
                                    <th class="px-4 py-3 font-medium text-center" title="CI/CD Status">CI</th>
                                    <th class="px-4 py-3 font-medium text-center" title="Merge Conflicts">Merge</th>
                                    <th class="px-4 py-3 font-medium text-center">Comments</th>
                                    <th class="px-4 py-3 font-medium">PR</th>
                                    <th class="px-4 py-3 font-medium">Author</th>
                                    <th class="px-4 py-3 font-medium">Title</th>
                                    <th class="px-4 py-3 font-medium">Branch</th>
                                    <th class="px-4 py-3 font-medium">Target</th>
                                    <th class="px-4 py-3 font-medium">Reviewers</th>
                                    <th class="px-4 py-3 font-medium text-right">Updated</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                @foreach ($this->filteredReviewRequests as $pr)
                                    @php $decision = $pr['reviewDecision'] ?? null; @endphp
                                    <tr wire:key="review-pr-{{ $pr['number'] }}-{{ $pr['repository']['name'] }}" class="bg-gray-800/50 hover:bg-gray-800 transition-colors">
                                        {{-- Status --}}
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @if ($pr['isDraft'])
                                                <span class="px-2 py-0.5 text-xs bg-gray-700 text-gray-300 rounded">Draft</span>
                                            @elseif ($decision)
                                                <span class="px-2 py-0.5 text-xs rounded {{ $decisionColors[$decision] ?? 'bg-gray-700 text-gray-300' }}">
                                                    {{ $decisionLabels[$decision] ?? $decision }}
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 text-xs bg-gray-700 text-gray-300 rounded">Pending</span>
                                            @endif
                                        </td>

                                        {{-- CI Status --}}
                                        <td class="px-4 py-3 text-center">
                                            @if ($pr['ciStatus'])
                                                <span class="{{ $ciColors[$pr['ciStatus']] ?? 'text-gray-500' }}" title="{{ $pr['ciStatus'] }}">
                                                    {{ $ciIcons[$pr['ciStatus']] ?? '?' }}
                                                </span>
                                            @else
                                                <span class="text-gray-600">—</span>
                                            @endif
                                        </td>

                                        {{-- Merge Status --}}
                                        <td class="px-4 py-3 text-center">
                                            @if ($pr['mergeable'] === 'CONFLICTING')
                                                <span class="text-red-400" title="Has merge conflicts">⚠</span>
                                            @elseif ($pr['mergeable'] === 'MERGEABLE')
                                                <span class="text-green-400" title="Ready to merge">✓</span>
                                            @else
                                                <span class="text-gray-600" title="{{ $pr['mergeable'] ?? 'Unknown' }}">—</span>
                                            @endif
                                        </td>

                                        {{-- Unresolved Comments --}}
                                        <td class="px-4 py-3 text-center">
                                            @if (($pr['unresolvedCount'] ?? 0) > 0)
                                                <span class="text-orange-400 font-medium">{{ $pr['unresolvedCount'] }}</span>
                                            @else
                                                <span class="text-gray-600">—</span>
                                            @endif
                                        </td>

                                        {{-- PR --}}
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="text-gray-400">{{ $pr['repository']['name'] }}</span>
                                            <span class="text-gray-500">#{{ $pr['number'] }}</span>
                                        </td>

                                        {{-- Author --}}
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="text-gray-300">{{ $pr['author']['login'] ?? 'unknown' }}</span>
                                        </td>

                                        {{-- Title --}}
                                        <td class="px-4 py-3 max-w-xs">
                                            <a href="{{ $pr['url'] }}" target="_blank" class="text-blue-400 hover:text-blue-300 font-medium block truncate" title="{{ $pr['title'] }}">
                                                {{ $pr['title'] }}
                                            </a>
                                        </td>

                                        {{-- Branch --}}
                                        <td class="px-4 py-3 font-mono text-xs max-w-[200px]">
                                            <span class="text-gray-300 block truncate" title="{{ $pr['headRefName'] }}">{{ $pr['headRefName'] }}</span>
                                        </td>

                                        {{-- Target --}}
                                        <td class="px-4 py-3 font-mono text-xs max-w-[150px]">
                                            <span class="text-gray-500 block truncate" title="{{ $pr['baseRefName'] ?? 'main' }}">{{ $pr['baseRefName'] ?? 'main' }}</span>
                                        </td>

                                        {{-- Reviewers --}}
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap gap-x-2 gap-y-1">
                                                @foreach ($pr['reviews'] ?? [] as $review)
                                                    @php $reviewState = $review['state'] ?? 'PENDING'; @endphp
                                                    <span class="inline-flex items-center gap-1 text-xs whitespace-nowrap {{ $stateColors[$reviewState] ?? 'text-gray-400' }}" title="{{ $reviewState }}">
                                                        <span>{{ $stateIcons[$reviewState] ?? '?' }}</span>
                                                        <span>{{ $review['author']['login'] ?? 'unknown' }}</span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        </td>

                                        {{-- Updated --}}
                                        <td class="px-4 py-3 text-right text-gray-500 whitespace-nowrap">
                                            {{ \Carbon\Carbon::parse($pr['updatedAt'])->diffForHumans() }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>
