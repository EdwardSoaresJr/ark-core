@php
    $boardQueueCount = collect($pressureBands)
        ->sum(fn (array $band): int => collect($band['statuses'])
            ->sum(fn ($status): int => $repairOrdersByStatus->get(is_string($status) ? $status : $status->value, collect())->count()));
@endphp

<x-operations.queue-page-header
    :title="$isTechnicianBoard ? 'Tech Operations' : 'Operations'"
    description="Repair order lifecycle pressure in the building."
    :count="$boardQueueCount"
    :show-back="false"
/>

<section class="space-y-2">
    <div class="ops-workboard-grid">
        <div class="ops-radar">
        @foreach ($pressureBands as $pressureBand)
            @php
                $repairOrderCount = collect($pressureBand['statuses'])
                    ->sum(fn ($status): int => $repairOrdersByStatus->get(is_string($status) ? $status : $status->value, collect())->count());
                $bandCount = $repairOrderCount;
            @endphp

            <section id="ops-lane-{{ Str::slug($pressureBand['label']) }}" class="ops-pressure-band ops-pressure-band--{{ $pressureBand['tone'] }} ops-queue-band--{{ $pressureBand['tone'] }}">
                <x-operations.queue-band-header
                    variant="lane"
                    :label="$pressureBand['label']"
                    :description="$pressureBand['description']"
                    :count="$bandCount"
                />

                <div class="ops-radar-cards">
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
                                @endphp

                                <a href="{{ route('operations.repair-orders.show', $repairOrder) }}" class="ops-queue-card ops-ro-card">
                                    <div class="ops-ro-card-top">
                                        <div class="ops-ro-card-primary">
                                            <p class="ops-ro-vehicle">{{ $repairOrder->vehicle->display_name }}</p>
                                            <p class="ops-ro-subline">
                                                <span class="ops-ro-number">#{{ $repairOrder->repair_order_id }}</span>
                                                <span class="ops-ro-sep">·</span>
                                                <span class="ops-ro-customer">{{ $repairOrder->customer->name }}</span>
                                                <span class="ops-ro-sep">·</span>
                                                <span class="ops-ro-flag">{{ $repairOrder->statusDisplayLabel() }}</span>
                                                @include('operations.repair-orders.partials.repair-order-vehicle-identity-pressure-chip', [
                                                    'repairOrder' => $repairOrder,
                                                    'vehicleIdentityPressure' => $card?->vehicleIdentityPressure,
                                                    'vehicleIdentityPressureHint' => $card?->vehicleIdentityPressureHint,
                                                ])
                                                @include('operations.repair-orders.partials.repair-order-parts-pressure-chip', [
                                                    'repairOrder' => $repairOrder,
                                                    'partsPressure' => $partsPressure,
                                                    'partsPressureSummary' => $partsPressureSummary,
                                                    'partsPressureLabel' => $card?->partsPressureLabel(),
                                                ])
                                            </p>
                                        </div>
                                        <div class="ops-ro-card-aside">
                                            @if ($estimateTotalLabel)
                                                <span class="ops-ro-total" title="Estimate total">{{ $estimateTotalLabel }}</span>
                                            @endif
                                            <span class="ops-ro-age ops-ro-age--{{ $agePressure }}">{{ $age }}</span>
                                        </div>
                                    </div>

                                    <p class="ops-ro-concern">{{ $repairOrder->concern_summary }}</p>

                                    <div class="ops-ro-card-footer">
                                        @if ($footnoteContext)
                                            <p class="ops-ro-footnote {{ ($partsPressure->showsChip() || $partsBlocker) ? 'ops-ro-footnote--alert' : '' }}">{{ $footnoteContext }}</p>
                                        @else
                                            <span class="ops-ro-footnote-spacer" aria-hidden="true"></span>
                                        @endif
                                        <span class="ops-ro-next-action">{{ $nextAction === 'Review' ? 'Open work order' : $nextAction }}</span>
                                        @php
                                            $laneInspectionCoverage = \App\Ark\Operations\Inspections\InspectionCoverageProjection::for($repairOrder, auth()->user());
                                        @endphp
                                        @if ($laneInspectionCoverage['can_record'] && ! $repairOrder->isTerminal())
                                            <a
                                                href="{{ $laneInspectionCoverage['capture_url'] }}"
                                                class="ops-ro-finding-link"
                                                x-on:click.stop
                                                data-inspection-capture-cta
                                                data-capture-surface="{{ $laneInspectionCoverage['capture_surface'] }}"
                                                data-desktop-walk-url="{{ $laneInspectionCoverage['walk_url'] }}"
                                                data-tablet-url="{{ $laneInspectionCoverage['tablet_url'] }}"
                                            >{{ $laneInspectionCoverage['cta_label'] }}</a>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        @endforeach
                    @else
                        <p class="px-1 py-2 text-[11px] font-semibold text-slate-400">No work in this lane.</p>
                    @endif
                </div>
            </section>
        @endforeach
        </div>
    </div>
</section>
