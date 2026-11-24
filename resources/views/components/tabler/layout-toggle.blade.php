@props([
    'mode' => 'auto',
    'ariaLabel' => 'Layout',
])

@php
    $listActive = $mode === 'list';
    $cardsActive = $mode === 'cards';
@endphp

<div
    {{ $attributes->class(['nav nav-segmented justify-content-stretch ark-collection-layout-toggle']) }}
    role="group"
    aria-label="{{ $ariaLabel }}"
    data-collection-layout-toggle
>
    <button
        type="button"
        class="nav-link flex-fill{{ $listActive ? ' active' : '' }}"
        data-collection-layout-option="list"
        aria-pressed="{{ $listActive ? 'true' : 'false' }}"
        @if ($listActive) aria-current="true" @endif
    >List</button>
    <button
        type="button"
        class="nav-link flex-fill{{ $cardsActive ? ' active' : '' }}"
        data-collection-layout-option="cards"
        aria-pressed="{{ $cardsActive ? 'true' : 'false' }}"
        @if ($cardsActive) aria-current="true" @endif
    >Cards</button>
</div>
