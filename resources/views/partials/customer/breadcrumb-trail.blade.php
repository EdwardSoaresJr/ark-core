@php
    /** @var list<array{label: string, href?: string}> $breadcrumb */
@endphp

<nav class="text-sm text-slate-500" aria-label="Breadcrumb">
    @foreach ($breadcrumb as $index => $item)
        @if ($index > 0)
            <span class="mx-1.5 text-slate-400">/</span>
        @endif

        @if (filled($item['href'] ?? null))
            <a href="{{ $item['href'] }}" class="text-[#0099cc] no-underline hover:text-[#0088b8]">{{ $item['label'] }}</a>
        @else
            <span class="font-medium text-slate-700">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
