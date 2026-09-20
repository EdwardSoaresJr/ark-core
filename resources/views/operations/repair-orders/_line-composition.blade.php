@php
    use App\Ark\Operations\RepairOrders\RepairOrderLineItemPresentation;

    $lineTitle = $displayDescription ?? $line->description;
    $partState = $line->procurementState();
    $showActions = $showActions ?? false;
    $showProcurement = $showProcurement ?? true;
    $lineGrid = $lineGrid ?? 'worksheet';
    $isTerminal = $isTerminal ?? true;
    $lineGridClass = 'md:grid-cols-[minmax(0,1fr)_52px_78px_64px_64px_64px_88px]';
    $lineMoneyDash = '—';
    $partStateOptions = $partStateOptions ?? [];
    $estimateVersion = $estimateVersion ?? null;
    $lineEditUrl = $lineEditUrl ?? route('operations.repair-orders.show', ['repairOrder' => $repairOrder, 'editing_line' => $line->id]);
    $procurementTone = $line->type->isPart()
        ? RepairOrderLineItemPresentation::procurementChipTone($partState)
        : null;
    $linePresentationMode = $linePresentationMode ?? (
        ($showActions && ! $isTerminal && $lineGrid !== 'review') ? 'edit' : 'view'
    );
@endphp

<div
    id="line-{{ $line->id }}"
    @class([
        'ops-line-card ops-line-row group scroll-mt-24 bg-white',
        'ops-line-card--interactive' => $showActions && ! $isTerminal && $lineGrid !== 'review',
        $procurementTone ? 'ops-line-card--procurement-'.$procurementTone : null,
    ])
    @if ($showActions && ! $isTerminal && $lineGrid !== 'review')
        role="button"
        tabindex="0"
        title="Edit line"
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
    @include('operations.repair-orders.partials.repair-order-line-card', [
        'line' => $line,
        'lineTitle' => $lineTitle,
        'repairOrder' => $repairOrder,
        'totals' => $totals,
        'partState' => $partState,
        'showActions' => $showActions,
        'showProcurement' => $showProcurement,
        'lineGrid' => $lineGrid,
        'lineGridClass' => $lineGridClass,
        'isTerminal' => $isTerminal,
        'lineMoneyDash' => $lineMoneyDash,
        'partStateOptions' => $partStateOptions,
        'estimateVersion' => $estimateVersion,
        'lineEditUrl' => $lineEditUrl,
        'linePresentationMode' => $linePresentationMode,
    ])
</div>
