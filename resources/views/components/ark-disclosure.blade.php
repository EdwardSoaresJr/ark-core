@props(['title', 'subtitle' => null, 'open' => false])

<div {{ $attributes->merge(['class' => trim('hs-accordion border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900 '.($open ? 'active' : ''))]) }}>
    <button type="button" class="hs-accordion-toggle flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm font-semibold text-slate-800 hover:text-slate-950 dark:text-slate-300 dark:hover:text-slate-100">
        <span class="min-w-0">
            <span class="block truncate">{{ $title }}</span>
            @if ($subtitle)
                <span class="block truncate text-xs font-medium text-slate-500 dark:text-slate-500">{{ $subtitle }}</span>
            @endif
        </span>
        <span class="text-xs font-semibold text-slate-400">Toggle</span>
    </button>

    <div class="hs-accordion-content {{ $open ? '' : 'hidden' }} w-full overflow-hidden transition-[height] duration-150">
        <div class="border-t border-slate-100 px-3 py-3 text-sm text-slate-600 dark:border-slate-800 dark:text-slate-400">
            {{ $slot }}
        </div>
    </div>
</div>
