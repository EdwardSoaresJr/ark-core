@props([
    'text' => null,
    'label' => 'Help',
    'title' => null,
    'items' => null,
])

@php
    $hasItems = is_array($items) && count($items) > 0;
@endphp

{{-- Alpine, not a Bootstrap popover: worksheet continuity replaces these regions, and Tabler only binds popovers on page load. --}}
<span
    {{ $attributes->class(['position-relative d-inline-flex align-items-center']) }}
    x-data="{ helpOpen: false }"
    @mouseenter="helpOpen = true"
    @mouseleave="helpOpen = false"
    @focusin="helpOpen = true"
    @focusout="helpOpen = false"
    @keydown.escape="helpOpen = false"
    data-help-tip
>
    <span class="form-help" tabindex="0" role="button" aria-label="{{ $label }}">
        @if (isset($trigger) && $trigger->isNotEmpty())
            {{ $trigger }}
        @else
            <span aria-hidden="true">?</span>
        @endif
    </span>
    <span
        class="dropdown-menu top-100 start-0 mt-1 p-2 small text-wrap fw-normal text-body"
        :class="{ show: helpOpen }"
        style="width: 16rem"
        role="tooltip"
    >
        @if ($hasItems)
            @if (filled($title))
                <span class="d-block subheader mb-1">{{ $title }}</span>
            @endif
            <span class="d-block">
                @foreach ($items as $item)
                    <span class="d-block mb-1">
                        <span class="fw-bold">{{ $item['label'] }}</span>
                        <span class="d-block text-secondary">{{ $item['detail'] }}</span>
                    </span>
                @endforeach
            </span>
        @elseif ($text !== null)
            {{ $text }}
        @else
            {{ $slot }}
        @endif
    </span>
</span>
