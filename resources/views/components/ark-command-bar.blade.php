@props(['eyebrow' => null, 'title' => null, 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between']) }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-500">{{ $eyebrow }}</p>
        @endif

        @if ($title)
            <h1 class="truncate text-lg font-semibold text-slate-950 dark:text-slate-100">{{ $title }}</h1>
        @endif

        @if ($description)
            <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">{{ $description }}</p>
        @endif
    </div>

    @if (isset($actions))
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endif
</div>
