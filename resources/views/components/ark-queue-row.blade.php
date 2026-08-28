@props(['href' => null, 'tone' => 'default'])

@php
    $toneClass = match ($tone) {
        'intake' => 'border-dashed border-slate-300 bg-slate-50/70 hover:bg-white dark:border-slate-700 dark:bg-slate-900/60 dark:hover:bg-slate-900',
        'blocked' => 'border-amber-200 bg-white hover:bg-amber-50/40 dark:border-amber-900/70 dark:bg-slate-900 dark:hover:bg-amber-950/20',
        default => 'border-slate-200 bg-white hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:hover:bg-slate-800/70',
    };
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'grid min-h-16 border px-2 py-1.5 transition '.$toneClass]) }}>
    {{ $slot }}
</a>
