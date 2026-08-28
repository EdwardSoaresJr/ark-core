@php
    use App\Ark\Runtime\Authorization\ArkCapability;

    $mode = $mode ?? 'edit';
    $isTerminal = $isTerminal ?? false;
    $normalizedMode = $mode === 'review' ? 'review' : 'edit';
    $reviewUrl = route('operations.repair-orders.show', $repairOrder);
    $editUrl = route('operations.repair-orders.show', $repairOrder);
    $canManage = auth()->user()?->can(ArkCapability::RepairOrdersManage->value) ?? false;
    $canToggle = ! $isTerminal && (
        $normalizedMode === 'edit' || $canManage
    );
    $modeLabel = $normalizedMode === 'edit' ? 'Editing' : 'Viewing';
    $modeActionClass = $normalizedMode === 'edit' ? 'ops-review-action--edit' : 'ops-review-action--review';
    $headerContext = $headerContext ?? false;
    if ($headerContext) {
        $modeActionClass = 'ops-ro-mode-control__trigger--header';
    }
@endphp

<div
    @class([
        'ops-ro-mode-control',
        'ops-ro-mode-control--'.$normalizedMode,
        'ops-ro-mode-control--header' => $headerContext,
    ])
    @if ($canToggle && ($registerModeShortcut ?? true))
        data-ro-mode-control
    @endif
    @if ($canToggle)
        x-data="arkRoModeControl({
            mode: @js($normalizedMode),
            reviewUrl: @js($reviewUrl),
            editUrl: @js($editUrl),
            canToggle: @js($canToggle),
        })"
        @keydown.escape.window="confirmOpen && cancel()"
    @endif
>
    @if ($canToggle)
        <button
            type="button"
            @class([
                'ops-ro-mode-control__trigger shrink-0',
                $headerContext ? $modeActionClass : 'ops-review-action '.$modeActionClass,
            ])
            title="Toggle Mode (V)"
            aria-label="Toggle Mode (V)"
            @click="toggle()"
        >
            <span x-text="modeLabel()">{{ $modeLabel }}</span>
        </button>

        <div
            x-show="confirmOpen"
            x-cloak
            class="ops-ro-mode-confirm"
            role="dialog"
            aria-modal="true"
            aria-labelledby="ro-mode-confirm-title-{{ $repairOrder->repair_order_id }}"
        >
            <button type="button" class="ops-ro-mode-confirm__backdrop" aria-label="Close" @click="cancel()"></button>

            <div class="ops-ro-mode-confirm__dialog">
                <p id="ro-mode-confirm-title-{{ $repairOrder->repair_order_id }}" class="ops-ro-mode-confirm__title">
                    Unsaved changes detected.
                </p>

                <div class="ops-ro-mode-confirm__actions">
                    <button
                        type="button"
                        class="ops-ro-mode-confirm__action ops-ro-mode-confirm__action--primary"
                        :disabled="saving"
                        @click="saveAndSwitch()"
                    >
                        <span x-show="!saving">Save & Switch</span>
                        <span x-show="saving" x-cloak>Saving…</span>
                    </button>
                    <button
                        type="button"
                        class="ops-ro-mode-confirm__action"
                        :disabled="saving"
                        @click="discardAndSwitch()"
                    >
                        Discard & Switch
                    </button>
                    <button
                        type="button"
                        class="ops-ro-mode-confirm__action"
                        :disabled="saving"
                        @click="cancel()"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @else
        <span @class([
            'ops-ro-mode-control__label shrink-0',
            $headerContext ? 'ops-ro-mode-control__trigger--header' : 'ops-review-action '.$modeActionClass,
        ])>
            {{ $modeLabel }}
        </span>
    @endif
</div>
