@props([
    'id' => null,
    'title',
    'description' => null,
    'count' => null,
    'backLabel' => 'Back to Work',
    'backUrl' => null,
    'showBack' => true,
    'tone' => null,
])

@php
    $backUrl = $backUrl ?? route('operations.index');
@endphp

<div
    @class([
        'ops-queue-page-header',
        'ops-board-shell',
        filled($tone) ? 'ops-queue-page-header--'.$tone : null,
    ])
>
    <div class="ops-queue-page-header__main">
        <div class="min-w-0 flex-1">
            <h2
                @if ($id) id="{{ $id }}" @endif
                class="ops-queue-page-header__title"
            >
                {{ $title }}
                @if ($count !== null && (int) $count > 0)
                    <x-operations.pressure-count :count="$count" inline />
                @endif
            </h2>

            @if (filled($description))
                <p class="ops-queue-page-header__description">{{ $description }}</p>
            @endif
        </div>

        <div class="ops-queue-page-header__actions">
            @if ($showBack)
                <a href="{{ $backUrl }}" class="ops-page-link">{{ $backLabel }}</a>
            @endif

            {{ $actions ?? '' }}
        </div>
    </div>
</div>
