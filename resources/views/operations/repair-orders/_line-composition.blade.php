@php
    use App\Ark\Operations\RepairOrders\RepairOrderLineItemPresentation;

    $lineTitle = $displayDescription ?? $line->description;
    $partState = $line->procurementState();
    $showActions = $showActions ?? false;
    $showProcurement = $showProcurement ?? true;
    $lineGrid = $lineGrid ?? 'worksheet';
    $isTerminal = $isTerminal ?? true;
    $lineMoneyDash = '-';
    $partStateOptions = $partStateOptions ?? [];
    $estimateVersion = $estimateVersion ?? null;
    $lineEditUrl = $lineEditUrl ?? route('operations.repair-orders.show', ['repairOrder' => $repairOrder, 'editing_line' => $line->id]);
    $procurementTone = $line->type->isPart()
        ? RepairOrderLineItemPresentation::procurementChipTone($partState)
        : null;
    $linePresentationMode = $linePresentationMode ?? (
        ($showActions && ! $isTerminal && $lineGrid !== 'review') ? 'edit' : 'view'
    );
    $lineAccentColor = RepairOrderLineItemPresentation::procurementRowAccentTablerColor($procurementTone);
@endphp

<div
    id="line-{{ $line->id }}"
    data-continuity-region="line"
    data-line-type="{{ $line->type->value }}"
    @if ($procurementTone) data-procurement-tone="{{ $procurementTone }}" @endif
    @class([
        'list-group-item scroll-mt-24',
        'list-group-item-action' => $showActions && ! $isTerminal && $lineGrid !== 'review',
    ])
    @if ($showActions && ! $isTerminal && $lineGrid !== 'review')
        @click="
            if ($event.target.closest('[data-line-card-ignore], a, button, select, input, textarea, summary, label')) {
                return;
            }

            const editLink = $el.querySelector('[data-line-edit-trigger]');

            if (editLink && typeof editLine === 'function') {
                editLine({ preventDefault() {}, currentTarget: editLink });
            }
        "
        @keydown.enter.prevent="
            const editLink = $el.querySelector('[data-line-edit-trigger]');
            if (editLink && typeof editLine === 'function') {
                editLine({ preventDefault() {}, currentTarget: editLink });
            }
        "
        @keydown.space.prevent="
            const editLink = $el.querySelector('[data-line-edit-trigger]');
            if (editLink && typeof editLine === 'function') {
                editLine({ preventDefault() {}, currentTarget: editLink });
            }
        "
    @endif
>
    @if ($lineAccentColor)
        <div class="card-status-start bg-{{ $lineAccentColor }}"></div>
    @endif
    @include('operations.repair-orders.partials.repair-order-line-card', [
        'line' => $line,
        'lineTitle' => $lineTitle,
        'repairOrder' => $repairOrder,
        'totals' => $totals,
        'partState' => $partState,
        'showActions' => $showActions,
        'showProcurement' => $showProcurement,
        'lineGrid' => $lineGrid,
        'isTerminal' => $isTerminal,
        'lineMoneyDash' => $lineMoneyDash,
        'partStateOptions' => $partStateOptions,
        'estimateVersion' => $estimateVersion,
        'lineEditUrl' => $lineEditUrl,
        'linePresentationMode' => $linePresentationMode,
    ])
</div>
