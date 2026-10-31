@props(['href' => null, 'icon', 'label', 'primary' => false])

{{-- Without an href this renders a button; pass its behavior as attributes, for example x-on:click. --}}
@php
    $tag = $href === null ? 'button' : 'a';
    $target = $href === null ? 'type="button"' : 'href="'.e($href).'"';
@endphp

@if ($primary)
    <{{ $tag }} {!! $target !!} {{ $attributes->class(['btn btn-primary']) }}>
        <x-tabler.icon :name="$icon" />
        {{ $label }}
    </{{ $tag }}>
@else
    <{{ $tag }} {!! $target !!} {{ $attributes->class(['btn d-none d-sm-inline-flex']) }}>
        <x-tabler.icon :name="$icon" />
        {{ $label }}
    </{{ $tag }}>
    <{{ $tag }} {!! $target !!} {{ $attributes->class(['btn btn-icon d-sm-none']) }} title="{{ $label }}" aria-label="{{ $label }}" data-bs-toggle="tooltip">
        <x-tabler.icon :name="$icon" />
    </{{ $tag }}>
@endif
