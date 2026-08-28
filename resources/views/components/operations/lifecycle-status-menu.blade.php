@props([
    'repairOrder' => null,
    'repairOrderId' => null,
    'label',
    'tone' => 'neutral',
    'statusMoves' => [],
    'confirmBaseUrl' => null,
    'align' => 'right',
])

@php
    $repairOrderKey = $repairOrder ?? $repairOrderId;
    abort_if($repairOrderKey === null, 500, 'lifecycle-status-menu requires repairOrder or repairOrderId.');
    $confirmBaseUrl ??= route('operations.repair-orders.show', $repairOrderKey);
    $statusMoves = is_array($statusMoves) ? $statusMoves : [];
@endphp

@if ($statusMoves !== [])
    <div
        class="ops-comms-menu ops-job-card__status-menu"
        x-data="arkFloatingCommsMenu({ align: @js($align), minWidth: 176, flipThreshold: 240 })"
        x-ref="menuRoot"
        {{ $attributes }}
    >
        <button
            type="button"
            x-ref="menuTrigger"
            @class([
                'ops-job-card__chip',
                'ops-job-card__chip--button',
                'ops-job-card__chip--' . $tone,
            ])
            @click.stop="toggleMenu()"
            :aria-expanded="menuOpen"
            aria-haspopup="menu"
            title="Change status"
        >
            <span class="ops-job-card__chip-label">{{ $label }}</span>
            <span class="ops-job-card__chip-caret" aria-hidden="true">▾</span>
        </button>

        <template x-teleport="body">
            <div
                x-show="menuOpen"
                x-cloak
                x-ref="menuPanel"
                :style="menuStyle"
                class="ops-comms-menu__panel ops-comms-menu__panel--floating ops-job-card__status-panel"
                role="menu"
                @click.stop
                @wheel.stop
            >
                <p class="ops-job-card__menu-heading" role="presentation">Move to</p>
                @foreach ($statusMoves as $move)
                    @if ($move['disabled'] ?? false)
                        <button
                            type="button"
                            role="menuitem"
                            disabled
                            class="ops-comms-menu__item ops-job-card__menu-move-button ops-job-card__menu-move-button--disabled"
                            title="{{ $move['blockedReason'] ?? 'Not available' }}"
                        >{{ $move['label'] }}</button>
                    @elseif ($move['needsRoConfirmation'] ?? false)
                        <a
                            href="{{ $confirmBaseUrl }}?lifecycle={{ urlencode($move['value']) }}"
                            role="menuitem"
                            class="ops-comms-menu__item ops-job-card__menu-move-button"
                        >{{ $move['label'] }}</a>
                    @else
                        <form
                            method="POST"
                            action="{{ route('operations.repair-orders.lifecycle.update', $repairOrderKey) }}"
                            class="ops-job-card__menu-move"
                        >
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ $move['value'] }}">
                            <button type="submit" role="menuitem" class="ops-comms-menu__item ops-job-card__menu-move-button">
                                {{ $move['label'] }}
                            </button>
                        </form>
                    @endif
                @endforeach
            </div>
        </template>
    </div>
@else
    <span @class([
        'ops-job-card__chip',
        'ops-job-card__chip--' . $tone,
    ]) {{ $attributes }}>
        <span class="ops-job-card__chip-label">{{ $label }}</span>
    </span>
@endif
