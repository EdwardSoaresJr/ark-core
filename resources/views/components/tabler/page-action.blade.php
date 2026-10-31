@props(['href', 'icon', 'label', 'primary' => false])

@if ($primary)
    <a href="{{ $href }}" class="btn btn-primary">
        <x-tabler.icon :name="$icon" />
        {{ $label }}
    </a>
@else
    <a href="{{ $href }}" class="btn d-none d-sm-inline-flex">
        <x-tabler.icon :name="$icon" />
        {{ $label }}
    </a>
    <a href="{{ $href }}" class="btn btn-icon d-sm-none" title="{{ $label }}" aria-label="{{ $label }}" data-bs-toggle="tooltip">
        <x-tabler.icon :name="$icon" />
    </a>
@endif
