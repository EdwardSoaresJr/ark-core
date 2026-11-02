@php
    use App\Ark\Operations\Printing\PartsLabelPrintContext;
    use App\Ark\Operations\RepairOrders\PartProcurementState;
    use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
    use App\Ark\Operations\RepairOrders\RepairOrderLineItemPresentation;

    $repairOrder->loadMissing(['concerns.lines']);

    $partRows = $repairOrder->concerns
        ->sortBy('position')
        ->flatMap(function ($concern) {
            return $concern->lines
                ->filter(fn ($line) => $line->isPart())
                ->map(fn ($line) => [
                    'line' => $line,
                    'concern' => $concern,
                ]);
        })
        ->sortBy(fn (array $row): string => sprintf(
            '%d-%d-%03d-%03d',
            $row['concern']->disposition === RepairOrderConcernDisposition::Approved ? 0 : 1,
            $row['line']->hasUnresolvedProcurement() && $row['concern']->disposition === RepairOrderConcernDisposition::Approved ? 0 : 1,
            $row['concern']->position,
            $row['line']->position ?? 0,
        ))
        ->values();

    $receivedPrintUrls = $partRows
        ->filter(fn (array $row): bool => $row['line']->procurementState() === PartProcurementState::Received)
        ->flatMap(fn (array $row): array => PartsLabelPrintContext::printUrlsForLine($repairOrder, $row['line']))
        ->values()
        ->all();
@endphp

<div id="parts-rail" data-parts-panel>
    <div class="card-body">
        <div class="row g-2 mb-2">
            <div class="col-6">
                <div class="small text-secondary">Blocking</div>
                <div @class(['h3 mb-0 ark-money', 'text-orange' => $partsBlockingCount > 0, 'text-green' => $partsBlockingCount === 0])>{{ $partsBlockingCount }}</div>
            </div>
            <div class="col-6">
                <div class="small text-secondary">Install Ready</div>
                <div class="h3 mb-0 ark-money">{{ $partsReadinessCounts['received'] + $partsReadinessCounts['installed'] }}</div>
            </div>
        </div>
        <div class="small fw-medium">{{ $repairOrder->procurementReadinessSummary() }}</div>
        @if ($repairOrder->status === \App\Ark\Operations\RepairOrders\RepairOrderStatus::WaitingParts)
            <div class="small fw-medium text-orange mt-1">When parts arrive, mark them Received here. Then move the RO status off Waiting Parts.</div>
        @elseif (($railMode ?? 'review') === 'builder' && $partsBlockingCount > 0)
            <div class="small text-secondary mt-1">Update sourcing or receiving on approved part lines in the worksheet.</div>
        @elseif ($partRows->isNotEmpty())
            <div class="small text-secondary mt-1">Mark each part Received when it arrives. If the RO is Waiting Parts, move status after the blockers clear.</div>
        @endif
        @if (count($receivedPrintUrls) > 0)
            <button
                type="button"
                class="btn btn-sm mt-2"
                @click="window.arkPrintPartsLabelsBatch(@js($receivedPrintUrls), $event.currentTarget)"
            >
                Print all received labels
                <span class="text-secondary ms-1 ark-money">({{ count($receivedPrintUrls) }})</span>
            </button>
        @endif
    </div>

    <div class="card-header border-top">
        <h3 class="card-title">Part Lines</h3>
    </div>

    <div class="list-group list-group-flush">
        @forelse ($partRows as $row)
            @php
                $line = $row['line'];
                $concern = $row['concern'];
                $isApproved = $concern->disposition === RepairOrderConcernDisposition::Approved;
                $supplierLabel = RepairOrderLineItemPresentation::supplierLabel($line);
                $statusTone = RepairOrderLineItemPresentation::procurementChipTone($line->procurementState());
                $linePrintUrls = PartsLabelPrintContext::printUrlsForLine($repairOrder, $line);
                $stickerCount = count($linePrintUrls);
            @endphp
            <div class="list-group-item" data-parts-line="{{ $line->id }}">
                <div class="d-flex align-items-start justify-content-between gap-2">
                    <div style="min-width: 0">
                        <div class="fw-medium text-truncate">{{ $line->description }}</div>
                        <div class="small text-secondary">
                            {{ $concern->summary }}
                            · {{ $concern->disposition->label() }}
                            @if (! $isApproved)
                                · Not blocking until approved
                            @endif
                        </div>
                    </div>
                    <span class="badge bg-{{ RepairOrderLineItemPresentation::procurementTablerColor($statusTone) }}-lt flex-shrink-0">{{ $line->procurementStateLabel() }}</span>
                </div>
                @if ($supplierLabel || $line->has_core || $line->save_old_part)
                    <div class="d-flex flex-wrap gap-1 mt-1">
                        @if ($supplierLabel)
                            <span class="badge bg-azure-lt">{{ $supplierLabel }}</span>
                        @endif
                        @if ($line->has_core)
                            <span class="badge bg-{{ RepairOrderLineItemPresentation::partFlagTablerColor('core') }}-lt">Core</span>
                        @endif
                        @if ($line->save_old_part)
                            <span class="badge bg-{{ RepairOrderLineItemPresentation::partFlagTablerColor('save') }}-lt">Save</span>
                        @endif
                    </div>
                @endif
                <div class="small text-secondary mt-1">
                    Qty {{ $line->quantity }}
                    @if ($line->part_number)
                        · Part # {{ $line->part_number }}
                    @endif
                </div>
                @if ($line->sourcing_notes)
                    <div class="small text-secondary mt-1">{{ $line->sourcing_notes }}</div>
                @endif
                <button
                    type="button"
                    class="btn btn-link btn-sm px-0 mt-1"
                    @click="window.arkPrintPartsLabelsBatch(@js($linePrintUrls), $event.currentTarget)"
                >
                    {{ $stickerCount > 1 ? 'Print labels' : 'Print label' }}
                    @if ($stickerCount > 1)
                        <span class="text-secondary ms-1">({{ $stickerCount }})</span>
                    @endif
                </button>
            </div>
        @empty
            <div class="list-group-item small text-secondary">
                No part lines on this repair order.
            </div>
        @endforelse
    </div>
</div>
