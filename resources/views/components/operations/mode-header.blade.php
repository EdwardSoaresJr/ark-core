@props([
    'mode' => 'review',
    'actionLabel' => null,
    'actionUrl' => null,
    'actionClass' => null,
])

@php
    $badgeLabel = match ($mode) {
        'builder', 'edit' => 'Editing',
        default => 'Viewing',
    };
    $actionClass = $actionClass ?? match ($mode) {
        'builder', 'edit' => 'ops-review-action ops-review-action--review shrink-0',
        default => 'ops-review-action ops-review-action--edit shrink-0',
    };
@endphp

<div @class(['ops-mode-header', 'ops-mode-header--'.$mode])>
    <div class="ops-mode-header-row">
        <p class="ops-mode-header-badge">{{ $badgeLabel }}</p>

        @if (filled($actionUrl) && filled($actionLabel))
            <a href="{{ $actionUrl }}" class="{{ $actionClass }}">{{ $actionLabel }}</a>
        @endif

        @isset($actions)
            {{ $actions }}
        @endisset
    </div>
</div>
