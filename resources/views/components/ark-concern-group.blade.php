@props(['eyebrow' => null, 'title' => null])

<section {{ $attributes->merge(['class' => 'border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900']) }}>
    @if ($eyebrow || $title || isset($actions))
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                @if ($eyebrow)
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-500">{{ $eyebrow }}</p>
                @endif

                @if ($title)
                    <h2 class="mt-1 text-base font-semibold text-slate-950 dark:text-slate-100">{{ $title }}</h2>
                @endif
            </div>

            @if (isset($actions))
                <div class="shrink-0">
                    {{ $actions }}
                </div>
            @endif
        </div>
    @endif

    <div @class(['mt-4' => $eyebrow || $title || isset($actions)])>
        {{ $slot }}
    </div>
</section>
