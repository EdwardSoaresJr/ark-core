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

<div id="parts-rail" class="ops-review-rail-tab-panel divide-y divide-slate-100 text-sm">
    <div class="grid gap-2 p-3">
        <div class="grid grid-cols-2 gap-px bg-slate-200">
            <div class="bg-white px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Blocking</p>
                <p class="mt-0.5 text-lg font-black tabular-nums {{ $partsBlockingCount > 0 ? 'text-amber-800' : 'text-emerald-800' }}">{{ $partsBlockingCount }}</p>
            </div>
            <div class="bg-white px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Install Ready</p>
                <p class="mt-0.5 text-lg font-black tabular-nums text-slate-950">{{ $partsReadinessCounts['received'] + $partsReadinessCounts['installed'] }}</p>
            </div>
        </div>
        <p class="text-xs font-semibold leading-4 text-slate-600">{{ $repairOrder->procurementReadinessSummary() }}</p>
        @if ($repairOrder->status === \App\Ark\Operations\RepairOrders\RepairOrderStatus::WaitingParts)
            <p class="text-xs font-semibold leading-4 text-amber-950">When parts arrive, mark them Received here. Then move the RO status off Waiting Parts.</p>
        @elseif (($railMode ?? 'review') === 'builder' && $partsBlockingCount > 0)
            <p class="text-xs leading-4 text-slate-600">Update sourcing or receiving on approved part lines in the worksheet.</p>
        @elseif ($partRows->isNotEmpty())
            <p class="text-[11px] leading-4 text-slate-500">Mark each part Received when it arrives. If the RO is Waiting Parts, move status after the blockers clear.</p>
        @endif
        @if (count($receivedPrintUrls) > 0)
            <div>
                <button
                    type="button"
                    class="text-[11px] font-semibold text-slate-700 underline decoration-slate-300 underline-offset-2 hover:text-slate-950"
                    @click="window.arkPrintPartsLabelsBatch(@js($receivedPrintUrls), $event.currentTarget)"
                >
                    Print all received labels
                    <span class="tabular-nums text-slate-500">({{ count($receivedPrintUrls) }})</span>
                </button>
            </div>
        @endif
    </div>

    <div class="ops-review-panel-header">
        <p class="ops-eyebrow">Part Lines</p>
    </div>

    <div class="divide-y divide-slate-100">
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
            <div class="px-3 py-2">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-slate-950">{{ $line->description }}</p>
                        <p class="mt-0.5 text-xs leading-4 text-slate-500">
                            {{ $concern->summary }}
                            <span class="text-slate-300">·</span>
                            {{ $concern->disposition->label() }}
                            @if (! $isApproved)
                                <span class="text-slate-300">·</span>
                                <span>Not blocking until approved</span>
                            @endif
                        </p>
                    </div>
                    <x-operations.line-item.status-chip
                        class="shrink-0"
                        :label="$line->procurementStateLabel()"
                        :tone="$statusTone"
                    />
                </div>
                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                    @if ($supplierLabel)
                        <x-operations.line-item.supplier-chip :label="$supplierLabel" />
                    @endif
                    @if ($line->has_core)
                        <x-operations.line-item.part-flag-chip label="Core" variant="core" />
                    @endif
                    @if ($line->save_old_part)
                        <x-operations.line-item.part-flag-chip label="Save" variant="save" />
                    @endif
                </div>
                <p class="mt-1 text-xs leading-4 text-slate-600">
                    Qty {{ $line->quantity }}
                    @if ($line->part_number)
                        <span class="text-slate-300">·</span>
                        Part # {{ $line->part_number }}
                    @endif
                </p>
                @if ($line->sourcing_notes)
                    <p class="mt-1 text-xs leading-4 text-slate-500">{{ $line->sourcing_notes }}</p>
                @endif
                <div class="mt-1.5">
                    <button
                        type="button"
                        class="text-[11px] font-semibold text-slate-600 underline decoration-slate-300 underline-offset-2 hover:text-slate-900"
                        @click="window.arkPrintPartsLabelsBatch(@js($linePrintUrls), $event.currentTarget)"
                    >
                        {{ $stickerCount > 1 ? 'Print labels' : 'Print label' }}
                        @if ($stickerCount > 1)
                            <span class="tabular-nums text-slate-500">({{ $stickerCount }})</span>
                        @endif
                    </button>
                </div>
            </div>
        @empty
            <div class="px-3 py-2 text-xs leading-4 text-slate-500">
                No part lines on this repair order.
            </div>
        @endforelse
    </div>
</div>
