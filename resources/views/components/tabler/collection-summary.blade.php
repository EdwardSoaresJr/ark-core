@props([
    'title',
    'paginator' => null,
    'totalLabel' => null,
    'clearUrl' => null,
    'hasFilters' => false,
])

@php
    $total = $paginator?->total();
    $from = $paginator?->firstItem();
    $to = $paginator?->lastItem();
    $summary = null;
    if ($paginator !== null && $total !== null && $total > 0 && $from !== null && $to !== null) {
        $summary = 'Showing '.$from.'–'.$to.' of '.$total;
    } elseif ($totalLabel !== null) {
        $summary = $totalLabel;
    } elseif ($total !== null) {
        $summary = $total.' total';
    }
@endphp

<div {{ $attributes->class(['d-flex flex-wrap align-items-center gap-2 mb-2 mb-md-3']) }} data-collection-summary>
    <h3 class="mb-0">{{ $title }}</h3>
    <div class="ms-auto d-flex flex-wrap align-items-center gap-2">
        @if ($summary !== null)
            <span class="text-secondary small">{{ $summary }}</span>
        @endif
        @if ($hasFilters && $clearUrl)
            <a href="{{ $clearUrl }}" class="btn btn-sm btn-ghost-secondary">Clear filters</a>
        @endif
        {{ $slot }}
    </div>
</div>
