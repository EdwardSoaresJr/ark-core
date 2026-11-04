@php
    /** @var list<array{label: string, href?: string}> $breadcrumb */
@endphp

<nav class="text-sm text-slate-500" aria-label="Breadcrumb">
    @foreach ($breadcrumb as $index => $item)
        @if ($index > 0)
            <span class="mx-1.5 text-slate-400">/</span>
        @endif

        @if (filled($item['href'] ?? null))
            <a href="{{ $item['href'] }}" class="text-[color:var(--public-link)] no-underline hover:text-[color:var(--public-link-hover)]">{{ $item['label'] }}</a>
        @else
            <span class="font-medium text-slate-700">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
