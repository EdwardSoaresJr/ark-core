@props([
    'label',
    'href',
    'active' => false,
    'ariaSort' => null,
    'sortKey' => null,
])

<th
    scope="col"
    @if ($ariaSort) aria-sort="{{ $ariaSort }}" @endif
    {{ $attributes }}
>
    <a
        href="{{ $href }}"
        @class(['btn btn-ghost-secondary btn-sm px-1', 'fw-bold' => $active])
        @if ($sortKey) data-collection-sort="{{ $sortKey }}" @endif
    >{{ $label }}</a>
</th>
