<div class="min-h-screen">
    <div class="max-w-full mx-auto px-4 py-8">
        {{-- Header Skeleton --}}
        <header class="mb-6 flex items-center justify-between">
            <div>
                <div class="h-8 w-64 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                <div class="h-4 w-40 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse mt-2"></div>
            </div>
            <div class="flex items-center gap-3">
                <div class="h-9 w-24 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                <div class="h-9 w-36 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                <div class="h-9 w-20 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                <div class="h-9 w-9 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
            </div>
        </header>

        {{-- Filters Skeleton --}}
        <div class="mb-6 flex flex-wrap items-center gap-6">
            <div class="h-5 w-16 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
            <div class="h-6 w-48 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
            <div class="h-6 w-44 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
            <div class="h-6 w-28 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
        </div>

        {{-- Tables Skeleton --}}
        <div class="space-y-8">
            {{-- My PRs Skeleton --}}
            <section>
                <div class="mb-4 flex items-center gap-2">
                    <div class="h-6 w-6 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                    <div class="h-6 w-40 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                    <div class="h-5 w-8 bg-zinc-200 dark:bg-zinc-700 rounded-full animate-pulse"></div>
                </div>
                <div class="rounded-lg border overflow-hidden">
                    <div class="h-12 bg-zinc-100 dark:bg-zinc-800"></div>
                    @for ($i = 0; $i < 3; $i++)
                        <div class="h-16 border-t flex items-center px-4 gap-4">
                            <div class="h-5 w-20 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                            <div class="h-5 w-8 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                            <div class="h-5 w-8 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                            <div class="h-5 w-8 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                            <div class="h-5 w-32 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                            <div class="h-5 flex-1 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                            <div class="h-5 w-24 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                        </div>
                    @endfor
                </div>
            </section>

            {{-- Review Requests Skeleton --}}
            <section>
                <div class="mb-4 flex items-center gap-2">
                    <div class="h-6 w-6 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                    <div class="h-6 w-36 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                    <div class="h-5 w-8 bg-zinc-200 dark:bg-zinc-700 rounded-full animate-pulse"></div>
                </div>
                <div class="rounded-lg border overflow-hidden">
                    <div class="h-12 bg-zinc-100 dark:bg-zinc-800"></div>
                    @for ($i = 0; $i < 3; $i++)
                        <div class="h-16 border-t flex items-center px-4 gap-4">
                            <div class="h-5 w-20 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                            <div class="h-5 w-8 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                            <div class="h-5 w-8 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                            <div class="h-5 w-8 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                            <div class="h-5 w-32 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                            <div class="h-5 flex-1 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                            <div class="h-5 w-24 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                        </div>
                    @endfor
                </div>
            </section>
        </div>
    </div>
</div>
