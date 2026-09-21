@php
    use App\Ark\Operations\RepairOrders\RepairOrderLineItemPresentation;

    $isNoteLine = $line->type->isNote();
    $isPartLine = $line->type->isPart();
    $isSubletLine = $line->type->value === 'sublet';
    $lineTitle = $lineTitle ?? $line->description;
    $showActions = $showActions ?? false;
    $isTerminal = $isTerminal ?? true;
    $lineGrid = $lineGrid ?? 'worksheet';
    $lineMoneyDash = $lineMoneyDash ?? '—';
    $lineEditUrl = $lineEditUrl ?? route('operations.repair-orders.show', ['repairOrder' => $repairOrder, 'editing_line' => $line->id]);
    $interactive = $showActions && ! $isTerminal && $lineGrid !== 'review';
    $partState = $partState ?? ($isPartLine ? $line->procurementState() : null);
    $partStateOptions = $partStateOptions ?? ($isPartLine ? $line->availableProcurementTransitions() : []);
    $supplierLabel = $isPartLine ? RepairOrderLineItemPresentation::supplierLabel($line) : null;
    $statusTone = $isPartLine && $partState ? RepairOrderLineItemPresentation::procurementChipTone($partState) : null;
    $matrixPricingChip = $isPartLine ? RepairOrderLineItemPresentation::matrixPricingChip($line) : null;
    $matrixInspect = $isPartLine ? RepairOrderLineItemPresentation::matrixInspectCard($line, $totals) : null;
    $profitability = $isPartLine ? RepairOrderLineItemPresentation::profitabilityMeter($line) : null;
    $markupSegment = $isPartLine ? RepairOrderLineItemPresentation::markupPricingSegment($line) : null;
    $showCost = ($isPartLine || $isSubletLine) && $line->part_cost_cents !== null;
    $partNumber = $isPartLine && filled($line->part_number) ? trim((string) $line->part_number) : null;
    $shopFacts = [];

    if ($showCost) {
        $shopFacts[] = 'Cost '.$totals->format($line->part_cost_cents);
    }

    if ($markupSegment) {
        $shopFacts[] = $markupSegment;
    }

    if ($profitability) {
        $shopFacts[] = $profitability['label'].' '.$profitability['percent'].'%';
    }

    if ($supplierLabel) {
        $shopFacts[] = $supplierLabel;
    }

    if ($partNumber) {
        $shopFacts[] = 'Part # '.$partNumber;
    }

    $hasShopMeta = $isPartLine || $showCost || $shopFacts !== [];
@endphp

<tr
    id="line-{{ $line->id }}"
    @class([
        'ark-tabler-ro__line',
        'ark-tabler-ro__line--note' => $isNoteLine,
        'ark-tabler-ro__line--interactive' => $interactive,
    ])
    @if ($interactive)
        role="button"
        tabindex="0"
        aria-label="Edit line"
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
    @endif
>
    @if ($isNoteLine)
        <td colspan="7" class="ark-tabler-ro__line-note" x-data="{ open: false }">
            <span class="badge badge-outline text-secondary">Note</span>
            <div class="ark-tabler-ro__note-preview" :class="{ 'is-open': open }">
                <x-operations.note-body :text="$lineTitle" class="ops-note-body--worksheet" />
            </div>
            @if (\Illuminate\Support\Str::length(trim((string) $lineTitle)) > 160)
                <button type="button" class="ark-tabler-ro__note-more" data-line-card-ignore @click.stop="open = !open" x-text="open ? 'Show less' : 'Show more'"></button>
            @endif
        </td>
        <td class="text-end">
            @if ($interactive)
                <a
                    href="{{ $lineEditUrl }}"
                    data-line-edit-trigger
                    data-refresh-scope="worksheet"
                    @click="typeof editLine === 'function' ? editLine($event) : null"
                    class="btn btn-ghost btn-sm"
                >Edit</a>
            @endif
        </td>
    @else
        <td>
            <div class="ark-tabler-ro__line-desc">
                <span class="ark-tabler-ro__line-type ark-tabler-ro__line-type--{{ $line->type->value }}">{{ $line->type->staffLabel() }}</span>
                <span class="ark-tabler-ro__line-title">{{ $lineTitle }}</span>
            </div>

            @if ($hasShopMeta)
                <div class="ark-tabler-ro__line-shop" data-line-card-ignore>
                    @if ($isPartLine)
                        <div class="ark-tabler-ro__line-shop-chips">
                            @if ($matrixPricingChip)
                                <x-operations.inspect-popover
                                    :title="$matrixInspect['title'] ?? 'Matrix pricing'"
                                    :items="$matrixInspect['items'] ?? []"
                                    class="ark-tabler-ro__inspect-chip"
                                >
                                    <x-operations.line-item.part-flag-chip
                                        :label="$matrixPricingChip['label']"
                                        :variant="$matrixPricingChip['variant']"
                                    />
                                </x-operations.inspect-popover>
                            @endif

                            @if (! $isTerminal)
                                @include('operations.repair-orders.partials.repair-order-part-procurement-actions', [
                                    'line' => $line,
                                    'repairOrder' => $repairOrder,
                                    'estimateVersion' => $estimateVersion ?? null,
                                    'partStateOptions' => $partStateOptions,
                                    'statusChipTone' => $statusTone,
                                    'returnMode' => $lineGrid === 'review' ? 'review' : null,
                                ])
                            @else
                                <x-operations.line-item.status-chip
                                    :label="$line->procurementStateLabel()"
                                    :tone="$statusTone"
                                />
                            @endif

                            @if ($line->has_core)
                                <x-operations.line-item.part-flag-chip label="Core" variant="core" />
                            @endif

                            @if ($line->save_old_part)
                                <x-operations.line-item.part-flag-chip label="Save" variant="save" />
                            @endif
                        </div>
                    @endif

                    @if ($shopFacts !== [])
                        <p class="ark-tabler-ro__line-shop-facts">
                            @foreach ($shopFacts as $index => $fact)
                                @if ($index > 0)
                                    <span class="ark-tabler-ro__line-shop-sep" aria-hidden="true">·</span>
                                @endif
                                <span @class([
                                    'ark-tabler-ro__line-shop-margin' => $profitability && str_starts_with($fact, $profitability['label']),
                                    'ark-tabler-ro__line-shop-margin--'.$profitability['tone'] => $profitability && str_starts_with($fact, $profitability['label']),
                                ])>{{ $fact }}</span>
                            @endforeach
                        </p>
                    @endif
                </div>
            @endif
        </td>
        <td class="text-end text-nowrap tabular-nums">{{ $line->quantity }}</td>
        <td class="text-end text-nowrap tabular-nums">{{ $totals->format($line->unit_price_cents) }}</td>
        <td class="text-end text-nowrap tabular-nums">{{ $totals->format($line->subtotal_cents) }}</td>
        <td class="text-end text-nowrap tabular-nums d-none d-xl-table-cell text-secondary">{{ $line->shop_fee_cents > 0 ? $totals->format($line->shop_fee_cents) : $lineMoneyDash }}</td>
        <td class="text-end text-nowrap tabular-nums d-none d-xl-table-cell text-secondary">{{ $line->tax_cents > 0 ? $totals->format($line->tax_cents) : $lineMoneyDash }}</td>
        <td class="text-end text-nowrap tabular-nums fw-bold">{{ $totals->format($line->total_cents) }}</td>
        <td class="text-end">
            @if ($interactive)
                <a
                    href="{{ $lineEditUrl }}"
                    data-line-edit-trigger
                    data-refresh-scope="worksheet"
                    data-continuity-focus="#line-update-{{ $line->id }} [name='description']"
                    @click="typeof editLine === 'function' ? editLine($event) : null"
                    class="btn btn-ghost btn-sm"
                >Edit</a>
            @endif
        </td>
    @endif
</tr>
