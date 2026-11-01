@props([
    'text',
    'label' => 'Help',
])

{{-- Tabler's form-help with a popover; tabindex lets a tap or Tab focus it, which opens the popover. --}}
<span
    {{ $attributes->class(['form-help']) }}
    tabindex="0"
    role="button"
    aria-label="{{ $label }}"
    data-bs-toggle="popover"
    data-bs-trigger="hover focus"
    data-bs-placement="top"
    data-bs-content="{{ $text }}"
>?</span>
