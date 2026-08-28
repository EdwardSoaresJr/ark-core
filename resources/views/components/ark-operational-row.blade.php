@props(['as' => 'div', 'href' => null])

@php
    $tag = $href ? 'a' : $as;
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'block border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm transition hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:hover:bg-slate-800/70']) }}>
    {{ $slot }}
</{{ $tag }}>
