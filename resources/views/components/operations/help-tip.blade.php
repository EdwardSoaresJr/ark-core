@props([
    'text' => null,
    'label' => 'Help',
    'title' => null,
    'items' => null,
])

@php
    $hasItems = is_array($items) && count($items) > 0;
@endphp

<span {{ $attributes->class(['ops-help-tip', 'ops-help-tip--rich' => $hasItems]) }} tabindex="0" role="button" aria-label="{{ $label }}">
    <span class="ops-help-tip__trigger" aria-hidden="true">?</span>
    <span
        @class([
            'ops-help-tip__content',
            'ops-help-tip__content--list' => $hasItems,
        ])
        role="tooltip"
    >
        @if ($hasItems)
            @if (filled($title))
                <p class="ops-help-tip__title">{{ $title }}</p>
            @endif
            <ul class="ops-help-tip__list">
                @foreach ($items as $item)
                    <li class="ops-help-tip__item">
                        <span class="ops-help-tip__term">{{ $item['label'] }}</span>
                        <span class="ops-help-tip__detail">{{ $item['detail'] }}</span>
                    </li>
                @endforeach
            </ul>
        @elseif ($text !== null)
            {{ $text }}
        @else
            {{ $slot }}
        @endif
    </span>
</span>
