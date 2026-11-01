@php
    use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor;

    $boardQueueCount = collect($pressureBands)
        ->sum(fn (array $band): int => collect($band['statuses'])
            ->sum(fn ($status): int => $repairOrdersByStatus->get(is_string($status) ? $status : $status->value, collect())->count()));
    $partsChipColor = fn (string $tone): string => match ($tone) {
        'backordered' => 'red',
        'partial' => 'orange',
        'waiting' => 'yellow',
        default => 'secondary',
    };
@endphp

<x-tabler.page
    layout="fluid"
    title="Job Board"
    :title-count="$boardQueueCount"
    subtitle="Repair order lifecycle pressure in the building."
>
    <div class="ark-board pb-2" data-technician-board>
        @foreach ($pressureBands as $pressureBand)
            @php
                $repairOrderCount = collect($pressureBand['statuses'])
                    ->sum(fn ($status): int => $repairOrdersByStatus->get(is_string($status) ? $status : $status->value, collect())->count());
                $bandCount = $repairOrderCount;
                $laneSlug = Str::slug($pressureBand['label']);
                $laneColor = RepairOrderStatusColor::tablerColorForChipTone($pressureBand['tone']);
                $laneColor = $laneColor === 'dark' ? 'secondary' : $laneColor;
            @endphp

            <section
                id="ops-lane-{{ $laneSlug }}"
                class="d-flex flex-column gap-2"
                data-technician-lane="{{ $laneSlug }}"
                data-lane-tone="{{ $pressureBand['tone'] }}"
                aria-labelledby="ops-lane-{{ $laneSlug }}-title"
            >
                <div class="card card-sm">
                    <div class="card-status-top bg-{{ $laneColor }}"></div>
                    <div class="card-body py-2">
                        <div class="d-flex align-items-center gap-2">
                            <h2 class="card-title mb-0 text-truncate" id="ops-lane-{{ $laneSlug }}-title">{{ $pressureBand['label'] }}</h2>
                            <span class="badge bg-secondary-lt ms-auto" data-technician-lane-count>{{ $bandCount }}</span>
                        </div>
                        @if (filled($pressureBand['description'] ?? null))
                            <div class="small text-secondary text-truncate">{{ $pressureBand['description'] }}</div>
                        @endif
                    </div>
                </div>

                @if ($bandCount > 0)
                    @foreach ($pressureBand['statuses'] as $queueStatusSlug)
                        @php
                            $queueStatusSlug = is_string($queueStatusSlug) ? $queueStatusSlug : $queueStatusSlug->value;
                            $statusOrders = $repairOrdersByStatus->get($queueStatusSlug, collect());
                            $queueStatusEnum = \App\Ark\Operations\RepairOrders\RepairOrderStatus::tryFrom($queueStatusSlug);
                        @endphp

                        @foreach ($statusOrders as $repairOrder)
                            @php
                                $card = $workboardCards[$repairOrder->id] ?? null;
                                $partsPressure = $card?->partsPressure ?? $repairOrder->partsPressure();
                                $partsPressureSummary = $card?->partsPressureSummary;
                                $partsBlocker = $partsPressureSummary ?? $card?->partsBlockerSummary ?? $repairOrder->partsBlockerSummary();
                                $estimateTotalLabel = $card?->estimateTotalLabel
                                    ?? (($repairOrderTotals[$repairOrder->id] ?? null)?->totalCents() > 0
                                        ? ($repairOrderTotals[$repairOrder->id] ?? null)?->format(($repairOrderTotals[$repairOrder->id] ?? null)->totalCents())
                                        : null);
                                $age = str_replace(' ago', '', $repairOrder->updated_at->diffForHumans(short: true, parts: 1));
                                $ageMinutes = $repairOrder->updated_at->diffInMinutes();
                                $agePressure = $ageMinutes >= 240 ? 'hot' : ($ageMinutes >= 60 ? 'warm' : 'fresh');
                                $nextAction = $queueStatusEnum !== null
                                    ? ($card?->nextAction($repairOrder, $queueStatusEnum) ?? 'Review')
                                    : $repairOrder->executionNextAction();
                                $footnoteContext = $queueStatusEnum !== null
                                    ? $card?->footnoteContextFor($repairOrder, $queueStatusEnum)
                                    : null;
                                $vehicleIdentityPressure = $card?->vehicleIdentityPressure ?? $repairOrder->vehicleIdentityPressure();
                                $vehicleIdentityPressureHint = $card?->vehicleIdentityPressureHint ?? $repairOrder->vehicleIdentityPressureHint();
                                $partsPressureTitle = $partsPressureSummary ?? $repairOrder->partsPressureSummary($partsPressure);
                                $partsPressureLabel = $card?->partsPressureLabel() ?? $repairOrder->partsPressureLabel();
                                $canRecordInspection = \App\Ark\Operations\Inspections\InspectionCaptureLinks::canRecord(auth()->user(), $repairOrder);
                                $statusTone = $repairOrder->status->indexTone();
                            @endphp

                            <article class="card card-sm" data-technician-board-card data-age-pressure="{{ $agePressure }}">
                                <div class="card-body p-2">
                                    <div class="d-flex align-items-baseline gap-2">
                                        <a href="{{ route('operations.repair-orders.show', $repairOrder) }}" class="fw-bold text-reset text-truncate stretched-link" data-technician-board-card-link>{{ $repairOrder->vehicle->display_name }}</a>
                                        @if ($estimateTotalLabel)
                                            <span class="ms-auto fw-bold ark-money text-nowrap" title="Estimate total">{{ $estimateTotalLabel }}</span>
                                        @endif
                                    </div>
                                    <div class="small text-secondary text-truncate">
                                        #{{ $repairOrder->repair_order_id }} · {{ $repairOrder->customer->name }}
                                    </div>
                                    <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                                        <x-tabler.status-badge :tone="$statusTone">{{ $repairOrder->statusDisplayLabel() }}</x-tabler.status-badge>
                                        @if ($vehicleIdentityPressure->showsChip())
                                            <span class="badge bg-yellow-lt" @if ($vehicleIdentityPressureHint) title="{{ $vehicleIdentityPressureHint }}" @endif>{{ $vehicleIdentityPressure->label() }}</span>
                                        @endif
                                        @if ($partsPressure->showsChip())
                                            <span class="badge bg-{{ $partsChipColor($partsPressure->chipTone()) }}-lt" @if ($partsPressureTitle) title="{{ $partsPressureTitle }}" @endif data-technician-board-parts="{{ $partsPressure->chipTone() }}">{{ $partsPressureLabel }}</span>
                                        @endif
                                    </div>
                                    @if (filled($repairOrder->concern_summary))
                                        <p class="small mb-0 mt-1">{{ $repairOrder->concern_summary }}</p>
                                    @endif
                                    @if ($footnoteContext)
                                        <p @class(['small mb-0 mt-1', 'text-warning' => $partsPressure->showsChip() || $partsBlocker, 'text-secondary' => ! ($partsPressure->showsChip() || $partsBlocker)])>{{ $footnoteContext }}</p>
                                    @endif
                                </div>
                                <div class="card-footer p-2">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <span class="small fw-medium text-primary">{{ $nextAction === 'Review' ? 'Open work order' : $nextAction }}</span>
                                        <span @class([
                                            'ms-auto small text-nowrap',
                                            'text-danger' => $agePressure === 'hot',
                                            'text-warning' => $agePressure === 'warm',
                                            'text-secondary' => $agePressure === 'fresh',
                                        ])>{{ $age }}</span>
                                    </div>
                                    @if ($canRecordInspection && ! $repairOrder->isTerminal())
                                        <a
                                            href="{{ \App\Ark\Operations\Inspections\InspectionCaptureLinks::captureUrl($repairOrder) }}"
                                            class="btn btn-sm w-100 mt-2 position-relative z-2"
                                            x-on:click.stop
                                            data-inspection-capture-cta
                                        >Record finding</a>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    @endforeach
                @else
                    <p class="small text-secondary px-1 mb-0">No work in this lane.</p>
                @endif
            </section>
        @endforeach
    </div>
</x-tabler.page>
