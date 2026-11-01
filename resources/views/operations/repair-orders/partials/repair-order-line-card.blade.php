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
    $contextLines = $isViewMode
        ? RepairOrderLineItemPresentation::viewContextLines($line)
        : RepairOrderLineItemPresentation::editContextLines($line);
    $needsAuthorization = isset($linesNeedingAuthorization)
        && collect($linesNeedingAuthorization)->contains(fn ($unauthorizedLine): bool => (int) $unauthorizedLine->id === (int) $line->id);
    $lineExceptions = isset($concern) && $concern->relationLoaded('authorizationExceptions')
        ? $concern->authorizationExceptions->filter(
            fn ($exception): bool => in_array((int) $line->id, $exception->lineIds(), true),
        )
        : collect();
    $canEditLine = $showActions && ! ($isTerminal ?? true) && $lineGrid !== 'review';
@endphp

@if ($isNoteLine)
    <div style="min-width: 0">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex flex-wrap align-items-center gap-1">
                <span class="subheader text-{{ $line->type->tablerColor() }} me-1">{{ $noteTypeLabel }}</span>
                @include('operations.repair-orders.partials.repair-order-note-visibility-badge', [
                    'audience' => [
                        'advisor' => $noteAudience->advisor,
                        'technician' => $noteAudience->technician,
                        'customer' => $noteAudience->customer,
                    ],
                    'ui' => 'tabler',
                ])
            </div>
            @unless ($isTerminal ?? true)
                <div class="btn-list">
                    @if ($showActions && $lineGrid !== 'review')
                        <a
                            href="{{ $lineEditUrl }}"
                            data-line-edit-trigger
                            data-line-card-ignore
                            data-refresh-scope="worksheet"
                            data-continuity-focus="#line-update-{{ $line->id }} [name='description']"
                            @click.stop="typeof editLine === 'function' ? editLine($event) : null"
                            class="btn btn-sm btn-ghost-secondary"
                            aria-label="Edit {{ $line->description }}"
                        >Edit</a>
                    @endif
                    @if (filled(trim((string) ($lineTitle ?? $line->description ?? ''))))
                        <button
                            type="button"
                            class="btn btn-sm btn-ghost-secondary"
                            data-line-card-ignore
                            @click.stop="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'dragon-service-advisor-line-note', context: { lineId: {{ $line->id }} }, invokeEl: $event.currentTarget } }))"
                        >
                            Rewrite
                        </button>
                    @endif
                </div>
            @endunless
        </div>
        <x-operations.note-body :text="$lineTitle" class="mt-1" ui="tabler" />
        <p class="small text-secondary mt-1 mb-0">
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
    <div class="ark-line-grid">
        <div style="min-width: 0">
            <div class="d-flex align-items-start gap-2">
                <div class="flex-fill" style="min-width: 0">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="subheader text-{{ $line->type->tablerColor() }}">{{ $line->type->staffLabel() }}</span>
                        @if ($needsAuthorization)
                            <span class="badge bg-yellow-lt">Needs authorization</span>
                        @endif
                        @if ($lineExceptions->isNotEmpty())
                            <button
                                type="button"
                                class="btn btn-sm btn-ghost-warning py-0"
                                data-line-card-ignore
                                data-authorization-exception-line="{{ $line->id }}"
                                aria-haspopup="dialog"
                                aria-label="{{ $lineExceptions->count() === 1 ? 'View work exception' : 'View work exceptions' }}"
                                @click.stop="window.dispatchEvent(new CustomEvent('ark-authorization-exception-open', { detail: { concernId: {{ $concern->id }}, mode: 'inspect', lineId: {{ $line->id }}, invokeEl: $event.currentTarget } }))"
                            >
                                Exception
                            </button>
                        @endif
                    </div>
                    <div class="fw-medium text-truncate">{{ $lineTitle }}</div>
                </div>
                @if ($canEditLine)
                    <a
                        href="{{ $lineEditUrl }}"
                        data-line-edit-trigger
                        data-line-card-ignore
                        data-refresh-scope="worksheet"
                        data-continuity-focus="#line-update-{{ $line->id }} [name='description']"
                        @click.stop="typeof editLine === 'function' ? editLine($event) : null"
                        class="btn btn-sm btn-ghost-secondary"
                        aria-label="Edit {{ $line->description }}"
                    >Edit</a>
                @endif
                <div class="fw-bold text-nowrap d-md-none">{{ $totals->format($line->total_cents) }}</div>
            </div>

            @if ($isPartLine)
                <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                    @if ($supplierLabel)
                        <x-operations.inspect-popover
                            :title="$supplierInspect['title'] ?? null"
                            :items="$supplierInspect['items'] ?? []"
                        >
                            <span class="badge bg-azure-lt">{{ $supplierLabel }}</span>
                        </x-operations.inspect-popover>
                    @endif

                    @if ($showProcurement && ! $isTerminal)
                        <x-operations.inspect-popover
                            :title="$procurementInspect['title'] ?? null"
                            :items="$procurementInspect['items'] ?? []"
                        >
                            @include('operations.repair-orders.partials.repair-order-part-procurement-actions', [
                                'line' => $line,
                                'repairOrder' => $repairOrder,
                                'estimateVersion' => $estimateVersion,
                                'partStateOptions' => $partStateOptions,
                                'statusChipTone' => $statusTone,
                                'returnMode' => $lineGrid === 'review' ? 'review' : null,
                                'ui' => 'tabler',
                            ])
                        </x-operations.inspect-popover>
                    @else
                        <x-operations.inspect-popover
                            :title="$procurementInspect['title'] ?? null"
                            :items="$procurementInspect['items'] ?? []"
                        >
                            <span class="badge bg-{{ RepairOrderLineItemPresentation::procurementTablerColor($statusTone) }}-lt">{{ $line->procurementStateLabel() }}</span>
                        </x-operations.inspect-popover>
                    @endif

                    @php
                        $partsLabelUrls = \App\Ark\Operations\Printing\PartsLabelPrintContext::printUrlsForLine($repairOrder, $line);
                        $partsLabelCount = count($partsLabelUrls);
                    @endphp
                    <button
                        type="button"
                        class="btn btn-sm btn-link"
                        data-line-card-ignore
                        @click.stop="window.arkPrintPartsLabelsBatch(@js($partsLabelUrls), $event.currentTarget)"
                    >
                        {{ $partsLabelCount > 1 ? 'Print labels' : 'Print label' }}
                        @if ($partsLabelCount > 1)
                            <span class="text-secondary ms-1">({{ $partsLabelCount }})</span>
                        @endif
                    </button>

                    @if ($line->has_core)
                        <span class="badge bg-{{ RepairOrderLineItemPresentation::partFlagTablerColor('core') }}-lt">Core</span>
                    @endif

                    @if ($line->save_old_part)
                        <span class="badge bg-{{ RepairOrderLineItemPresentation::partFlagTablerColor('save') }}-lt">Save</span>
                    @endif

                    @if ($matrixPricingChip)
                        <x-operations.inspect-popover
                            :title="$matrixInspect['title'] ?? null"
                            :items="$matrixInspect['items'] ?? []"
                        >
                            <span class="badge bg-{{ RepairOrderLineItemPresentation::partFlagTablerColor($matrixPricingChip['variant']) }}-lt">{{ $matrixPricingChip['label'] }}</span>
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
                                class="btn btn-sm btn-link"
                                data-line-card-ignore
                            >
                                Source
                            </a>
                        @endif
                    @endif
                </div>
            @endif

            <div class="small text-secondary mt-1">
                <span>Qty {{ $line->quantity }}</span>
                <span class="mx-1" aria-hidden="true">•</span>
                <span>{{ $factPriceLabel }} {{ $factPriceValue }}</span>
                @if ($isPartLine && $profitability)
                    <span class="mx-1" aria-hidden="true">•</span>
                    <x-operations.inspect-popover
                        :title="$profitabilityInspect['title'] ?? null"
                        :items="$profitabilityInspect['items'] ?? []"
                        :footer="$profitabilityInspect['footer'] ?? null"
                    >
                        <span class="fw-medium text-{{ RepairOrderLineItemPresentation::profitabilityTablerColor($profitability['tone']) }}">{{ $profitability['label'] }} {{ $profitability['percent'] }}%</span>
                    </x-operations.inspect-popover>
                @endif
            </div>

            @foreach ($contextLines as $contextLine)
                <p class="small text-secondary mt-1 mb-0">{{ $contextLine }}</p>
            @endforeach
        </div>

        <div class="ark-line-ledger" data-line-ledger>
            <div>
                <div class="subheader d-md-none">Qty/Hrs</div>
                <div>{{ $line->quantity }}</div>
            </div>

            <div>
                <div class="subheader d-md-none">Price</div>
                <div>{{ $totals->format($line->unit_price_cents) }}</div>
            </div>

            <div class="d-none d-md-block">
                <div>{{ $totals->format($line->subtotal_cents) }}</div>
            </div>

            <div class="d-none d-md-block">
                <div class="text-secondary">{{ $line->shop_fee_cents > 0 ? $totals->format($line->shop_fee_cents) : $lineMoneyDash }}</div>
            </div>

            <div class="d-none d-md-block">
                <div class="text-secondary">{{ $line->tax_cents > 0 ? $totals->format($line->tax_cents) : $lineMoneyDash }}</div>
            </div>

            <div>
                <div class="subheader d-md-none">Total</div>
                @if ($isPartLine && $profitabilityInspect)
                    <x-operations.inspect-popover
                        :title="$profitabilityInspect['title'] ?? null"
                        :items="$profitabilityInspect['items'] ?? []"
                        :footer="$profitabilityInspect['footer'] ?? null"
                        align="end"
                    >
                        <span class="fw-bold">{{ $totals->format($line->total_cents) }}</span>
                    </x-operations.inspect-popover>
                @else
                    <div class="fw-bold">{{ $totals->format($line->total_cents) }}</div>
                @endif
            </div>
        </div>
    </div>
@endif
