@props(['href' => null, 'icon', 'label', 'primary' => false])

{{--
    Without an href this renders a button; pass its behavior as attributes, for example x-on:click.
    In the top bar the primary action stays in view (icon only on phones); secondary actions move into the
    overflow menu on narrow screens (tabler.js).
--}}
@php
    $tag = $href === null ? 'button' : 'a';
    $target = $href === null ? 'type="button"' : 'href="'.e($href).'"';
@endphp

<{{ $tag }} {!! $target !!} {{ $attributes->class(['btn', 'btn-primary' => $primary]) }} data-ark-page-action="{{ $primary ? 'primary' : 'secondary' }}" aria-label="{{ $label }}">
    <x-tabler.icon :name="$icon" />
    <span @class(['ark-page-action__label', 'd-none d-sm-inline' => $primary])>{{ $label }}</span>
</{{ $tag }}>
