@php
    use App\Ark\Operations\Financial\EstimateTotalsCalculator;
    use App\Ark\Operations\RepairOrders\RepairOrderLineItemPresentation;

    $isNoteLine = $line->type->isNote();
    $isPartLine = $line->type->isPart();
    $noteAudience = $isNoteLine ? $line->noteAudience() : null;
    $isPrivateNote = $isNoteLine && $line->isPrivateNote();
    $noteTypeLabel = $isNoteLine
        ? ($line->repair_order_work_group_id ? 'Note' : 'Concern Note')
        : null;
    $linePresentationMode = $linePresentationMode ?? 'view';
    $isViewMode = $linePresentationMode === 'view';
    $lineInspectCalculator = app(EstimateTotalsCalculator::class);
    $supplierLabel = $isPartLine ? RepairOrderLineItemPresentation::supplierLabel($line) : null;
    $statusTone = $isPartLine ? RepairOrderLineItemPresentation::procurementChipTone($partState) : null;
    $profitability = $isPartLine ? RepairOrderLineItemPresentation::profitabilityMeter($line) : null;
    $matrixPricingChip = $isPartLine ? RepairOrderLineItemPresentation::matrixPricingChip($line) : null;
    $profitabilityInspect = $isPartLine
        ? RepairOrderLineItemPresentation::profitabilityInspectCard($line, $totals, $lineInspectCalculator)
        : null;
    $supplierInspect = $isPartLine ? RepairOrderLineItemPresentation::supplierInspectCard($line) : null;
    $procurementInspect = $isPartLine ? RepairOrderLineItemPresentation::procurementInspectCard($line, $repairOrder) : null;
    $matrixInspect = $isPartLine ? RepairOrderLineItemPresentation::matrixInspectCard($line, $totals) : null;
    $lineEditUrl = $lineEditUrl ?? route('operations.repair-orders.show', ['repairOrder' => $repairOrder, 'editing_line' => $line->id]);
    $factPriceLabel = $isPartLine || $line->type->value === 'sublet' ? 'Cost' : 'Price';
    $factPriceValue = $isPartLine || $line->type->value === 'sublet'
        ? ($line->part_cost_cents !== null ? $totals->format($line->part_cost_cents) : $totals->format($line->unit_price_cents))
        : $totals->format($line->unit_price_cents);
    $lineGridClass = $lineGridClass ?? 'md:grid-cols-[minmax(0,1fr)_52px_78px_64px_64px_64px_88px]';
    $contextLines = $isViewMode
        ? RepairOrderLineItemPresentation::viewContextLines($line)
        : RepairOrderLineItemPresentation::editContextLines($line);
@endphp

<div @class([
    'ops-line-card__main',
    'ops-line-card__main--note' => $isNoteLine,
    'grid gap-2 md:items-start '.$lineGridClass => ! $isNoteLine,
])>
    @if ($isNoteLine)
        <div class="ops-line-card__content ops-line-card__content--note min-w-0 w-full">
            <div class="ops-line-card__note-toolbar justify-between">
                <div class="flex min-w-0 flex-wrap items-center gap-1.5">
                    <span class="ops-line-type ops-line-type--note">{{ $noteTypeLabel }}</span>
                    @include('operations.repair-orders.partials.repair-order-note-visibility-badge', [
                        'audience' => [
                            'advisor' => $noteAudience->advisor,
                            'technician' => $noteAudience->technician,
                            'customer' => $noteAudience->customer,
                        ],
                    ])
                </div>
                @unless ($isTerminal ?? true)
                    @if (filled(trim((string) ($lineTitle ?? $line->description ?? ''))))
                        <button
                            type="button"
                            class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 hover:text-slate-800"
                            data-line-card-ignore
                            @click.stop="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'dragon-service-advisor-line-note', context: { lineId: {{ $line->id }} }, invokeEl: $event.currentTarget } }))"
                        >
                            Rewrite
                        </button>
                    @endif
                @endunless
            </div>
            <x-operations.note-body :text="$lineTitle" class="ops-note-body--worksheet" />
            <p class="ops-line-card__note-meta">
                @if ($noteAudience->customer && $noteAudience->technician)
                    On tech sheet and customer estimate
                @elseif ($noteAudience->customer)
                    On customer estimate
                @elseif ($noteAudience->technician)
                    On tech sheet · hidden from customer
                @else
                    Advisor only · hidden from tech sheet and customer
                @endif
            </p>
        </div>
    @else
        <div class="ops-line-card__content min-w-0">
            <div class="ops-line-card__head">
                <div class="min-w-0 flex-1">
                    <span class="ops-line-type ops-line-type--{{ $line->type->value }}">{{ $line->type->staffLabel() }}</span>
                    <p class="ops-line-card__title truncate">{{ $lineTitle }}</p>
                </div>
                <p class="ops-line-card__total shrink-0 tabular-nums md:hidden">{{ $totals->format($line->total_cents) }}</p>
            </div>

            @if ($isPartLine)
                <div class="ops-line-card__chips flex min-w-0 flex-wrap items-center gap-1">
                    @if ($supplierLabel)
                        <x-operations.inspect-popover
                            :title="$supplierInspect['title'] ?? null"
                            :items="$supplierInspect['items'] ?? []"
                            class="ops-line-card__inspect-chip"
                        >
                            <x-operations.line-item.supplier-chip :label="$supplierLabel" />
                        </x-operations.inspect-popover>
                    @endif

                    @if ($showProcurement && ! $isTerminal)
                        <x-operations.inspect-popover
                            :title="$procurementInspect['title'] ?? null"
                            :items="$procurementInspect['items'] ?? []"
                            class="ops-line-card__inspect-chip"
                        >
                            @include('operations.repair-orders.partials.repair-order-part-procurement-actions', [
                                'line' => $line,
                                'repairOrder' => $repairOrder,
                                'estimateVersion' => $estimateVersion,
                                'partStateOptions' => $partStateOptions,
                                'statusChipTone' => $statusTone,
                                'returnMode' => $lineGrid === 'review' ? 'review' : null,
                            ])
                        </x-operations.inspect-popover>
                    @else
                        <x-operations.inspect-popover
                            :title="$procurementInspect['title'] ?? null"
                            :items="$procurementInspect['items'] ?? []"
                            class="ops-line-card__inspect-chip"
                        >
                            <x-operations.line-item.status-chip
                                :label="$line->procurementStateLabel()"
                                :tone="$statusTone"
                            />
                        </x-operations.inspect-popover>
                    @endif

                    @php
                        $partsLabelUrls = \App\Ark\Operations\Printing\PartsLabelPrintContext::printUrlsForLine($repairOrder, $line);
                        $partsLabelCount = count($partsLabelUrls);
                    @endphp
                    <button
                        type="button"
                        class="text-[11px] font-semibold text-slate-600 underline decoration-slate-300 underline-offset-2 hover:text-slate-900"
                        data-line-card-ignore
                        @click.stop="window.arkPrintPartsLabelsBatch(@js($partsLabelUrls), $event.currentTarget)"
                    >
                        {{ $partsLabelCount > 1 ? 'Print labels' : 'Print label' }}
                        @if ($partsLabelCount > 1)
                            <span class="tabular-nums text-slate-500">({{ $partsLabelCount }})</span>
                        @endif
                    </button>

                    @if ($line->has_core)
                        <x-operations.line-item.part-flag-chip label="Core" variant="core" />
                    @endif

                    @if ($line->save_old_part)
                        <x-operations.line-item.part-flag-chip label="Save" variant="save" />
                    @endif

                    @if ($matrixPricingChip)
                        <x-operations.inspect-popover
                            :title="$matrixInspect['title'] ?? null"
                            :items="$matrixInspect['items'] ?? []"
                            class="ops-line-card__inspect-chip"
                        >
                            <x-operations.line-item.part-flag-chip
                                :label="$matrixPricingChip['label']"
                                :variant="$matrixPricingChip['variant']"
                            />
                        </x-operations.inspect-popover>
                    @endif

                    @if ($line->dealer_quote_line_id)
                        @php
                            $dealerQuoteForSource = $line->relationLoaded('dealerQuoteLine')
                                ? $line->dealerQuoteLine?->quote
                                : $line->dealerQuoteLine()->with('quote')->first()?->quote;
                        @endphp
                        @if ($dealerQuoteForSource)
                            <a
                                href="{{ route('operations.repair-orders.dealer-quotes.show', [$repairOrder, $dealerQuoteForSource]) }}"
                                class="ops-line-card__source-link text-[10px] font-bold uppercase tracking-[0.08em] text-sky-700 hover:text-sky-900"
                                data-line-card-ignore
                            >
                                Source
                            </a>
                        @endif
                    @endif
                </div>
            @endif

            <div class="ops-line-card__facts">
                <div class="ops-line-card__facts-line">
                    <span>Qty {{ $line->quantity }}</span>
                    <span class="ops-line-card__facts-sep" aria-hidden="true">•</span>
                    <span>{{ $factPriceLabel }} {{ $factPriceValue }}</span>
                    @if ($isPartLine && $profitability)
                        <span class="ops-line-card__facts-sep" aria-hidden="true">•</span>
                        <x-operations.inspect-popover
                            :title="$profitabilityInspect['title'] ?? null"
                            :items="$profitabilityInspect['items'] ?? []"
                            :footer="$profitabilityInspect['footer'] ?? null"
                            class="ops-line-card__inspect-margin"
                        >
                            <span @class([
                                'ops-line-card__margin-label',
                                'ops-line-card__margin-label--'.$profitability['tone'],
                            ])>{{ $profitability['label'] }} {{ $profitability['percent'] }}%</span>
                        </x-operations.inspect-popover>
                    @endif
                </div>
            </div>

            @foreach ($contextLines as $contextLine)
                <p class="ops-line-card__context">{{ $contextLine }}</p>
            @endforeach
        </div>

        <div @class([
            'ops-line-ledger grid grid-cols-3 gap-2 text-right md:contents',
            'max-md:mt-1 max-md:border-t max-md:border-slate-100 max-md:pt-1.5' => $lineGrid === 'review',
            'max-md:mt-1 max-md:border-t max-md:border-slate-100 max-md:pt-1' => $lineGrid !== 'review',
        ])>
            <div class="md:block">
                @if ($lineGrid === 'review')
                    <p class="ops-line-column-label max-md:block md:hidden">Qty</p>
                @else
                    <p class="ops-line-column-label">Qty</p>
                @endif
                <p class="ops-line-ledger__value tabular-nums">{{ $line->quantity }}</p>
            </div>

            <div class="md:block">
                @if ($lineGrid === 'review')
                    <p class="ops-line-column-label max-md:block md:hidden">Price</p>
                @else
                    <p class="ops-line-column-label">Price</p>
                @endif
                <p class="ops-line-ledger__value tabular-nums">{{ $totals->format($line->unit_price_cents) }}</p>
            </div>

            <div class="max-md:hidden md:block">
                @if ($lineGrid !== 'review')
                    <p class="ops-line-column-label">Subtotal</p>
                @endif
                <p class="ops-line-ledger__value tabular-nums">{{ $totals->format($line->subtotal_cents) }}</p>
            </div>

            <div class="max-md:hidden md:block">
                @if ($lineGrid !== 'review')
                    <p class="ops-line-column-label">Fees</p>
                @endif
                <p class="ops-line-ledger__value ops-line-ledger__value--muted tabular-nums">{{ $line->shop_fee_cents > 0 ? $totals->format($line->shop_fee_cents) : $lineMoneyDash }}</p>
            </div>

            <div class="max-md:hidden md:block">
                @if ($lineGrid !== 'review')
                    <p class="ops-line-column-label">Tax</p>
                @endif
                <p class="ops-line-ledger__value ops-line-ledger__value--muted tabular-nums">{{ $line->tax_cents > 0 ? $totals->format($line->tax_cents) : $lineMoneyDash }}</p>
            </div>

            <div class="md:text-right">
                @if ($lineGrid === 'review')
                    <p class="ops-line-column-label max-md:block md:hidden">Total</p>
                @else
                    <p class="ops-line-column-label">Total</p>
                @endif
                @if ($isPartLine && $profitabilityInspect)
                    <x-operations.inspect-popover
                        :title="$profitabilityInspect['title'] ?? null"
                        :items="$profitabilityInspect['items'] ?? []"
                        :footer="$profitabilityInspect['footer'] ?? null"
                        align="end"
                        class="ops-line-card__inspect-total"
                    >
                        <p class="ops-line-ledger__value ops-line-ledger__value--total tabular-nums">{{ $totals->format($line->total_cents) }}</p>
                    </x-operations.inspect-popover>
                @else
                    <p class="ops-line-ledger__value ops-line-ledger__value--total tabular-nums">{{ $totals->format($line->total_cents) }}</p>
                @endif
            </div>
        </div>
    @endif

    @if ($showActions && ! $isTerminal && $lineGrid !== 'review')
        {{-- Visually hidden trigger — whole interactive row opens edit; keep for a11y + continuity. --}}
        <a
            href="{{ $lineEditUrl }}"
            data-line-edit-trigger
            data-refresh-scope="worksheet"
            data-continuity-focus="#line-update-{{ $line->id }} [name='description']"
            @click="typeof editLine === 'function' ? editLine($event) : null"
            class="sr-only"
        >
            Edit {{ $line->description }}
        </a>
    @endif
</div>
