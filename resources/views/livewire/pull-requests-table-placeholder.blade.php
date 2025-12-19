<div>
    <div class="mb-4 flex items-center gap-2">
        <div class="size-6 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
        <div class="h-6 w-40 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
        <div class="h-5 w-8 bg-zinc-200 dark:bg-zinc-700 rounded-full animate-pulse"></div>
    </div>
    <div class="rounded-lg border overflow-hidden" wire:key="pull-requests-table-placeholder-{{ $type }}">
        <div class="h-12 bg-zinc-100 dark:bg-zinc-800"></div>
        @for ($i = 0; $i < 3; $i++)
            <div class="h-16 border-t flex items-center px-4 gap-4">
                <div class="h-5 w-20 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                <div class="h-5 w-8 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                <div class="h-5 w-8 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                <div class="h-5 w-8 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                <div class="h-5 w-32 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                <div class="h-5 flex-1 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                <div class="h-5 w-12 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                <div class="h-5 w-12 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
            </div>
        @endfor
    </div>
</div>
