@php
    $lifecyclePlacement = $lifecyclePlacement ?? 'rail';
    $partsPressure = $repairOrder->partsPressure();
    $partsPressureSummary = $repairOrder->partsPressureSummary();
    $customerIdentityPressure = $repairOrder->customerIdentityPressure();
    $customerIdentityHint = $repairOrder->customerIdentityPressureHint();
    $vehicleIdentityPressure = $repairOrder->vehicleIdentityPressure();
    $vehicleIdentityHint = $repairOrder->vehicleIdentityPressureHint();
    $showRailPressure = $customerIdentityPressure->showsChip()
        || $vehicleIdentityPressure->showsChip()
        || $partsPressure->showsChip();
@endphp

@if ($lifecyclePlacement === 'history')
    @php
        $lifecycleMilestones = app(\App\Ark\Operations\RepairOrders\RepairOrderLifecycleProjection::class)->for($repairOrder);
        $productionMetrics = app(\App\Ark\Operations\RepairOrders\RepairOrderWorkDurationProjection::class)->for($repairOrder);
        $displayTimezone = config('app.display_timezone');
    @endphp
    <section class="d-flex flex-column gap-3" aria-label="Repair order status history" data-lifecycle-history>
        <div class="card card-sm">
            <div class="card-header">
                <h3 class="card-title">Status history</h3>
            </div>
            <div class="list-group list-group-flush">
                @foreach ($lifecycleMilestones as $milestone)
                    <div class="list-group-item d-flex align-items-baseline justify-content-between gap-3 py-2">
                        <span class="small text-secondary">{{ $milestone['label'] }}</span>
                        @if ($milestone['occurred_at'] instanceof \Illuminate\Support\Carbon)
                            <time
                                datetime="{{ $milestone['occurred_at']->toIso8601String() }}"
                                class="small fw-medium ark-money text-nowrap"
                                title="{{ $milestone['source'] }}"
                            >
                                {{ $milestone['occurred_at']->timezone($displayTimezone)->format('M j, g:i A') }}
                            </time>
                        @else
                            <span class="small text-secondary" title="{{ $milestone['note'] ?? $milestone['source'] }}">-</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        @if (collect($productionMetrics)->contains(fn (array $metric): bool => $metric['duration_label'] !== null))
            <div class="card card-sm">
                <div class="card-header">
                    <h3 class="card-title">Production metrics</h3>
                </div>
                <div class="list-group list-group-flush">
                    @foreach ($productionMetrics as $metric)
                        @if ($metric['duration_label'] !== null)
                            <div class="list-group-item d-flex align-items-baseline justify-content-between gap-3 py-2">
                                <span class="small text-secondary">{{ $metric['label'] }}</span>
                                <span class="small fw-medium ark-money text-nowrap">{{ $metric['duration_label'] }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
    </section>
@elseif ($showRailPressure)
    <section class="ops-review-panel mt-2" aria-label="Repair order attention">
        @if ($customerIdentityPressure->showsChip())
            <div class="border-b border-slate-100 bg-slate-50/80 px-3 py-2">
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-600">Customer info</span>
                    @include('operations.repair-orders.partials.repair-order-customer-identity-pressure', ['repairOrder' => $repairOrder])
                </div>
                @if ($customerIdentityHint)
                    <p class="mt-1 text-[11px] font-semibold leading-4 text-slate-700">{{ $customerIdentityHint }}</p>
                @endif
            </div>
        @endif
        @if ($vehicleIdentityPressure->showsChip())
            <div class="border-b border-amber-100 bg-amber-50/50 px-3 py-2">
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="text-[10px] font-bold uppercase tracking-[0.08em] text-amber-800">Vehicle identity</span>
                    @include('operations.repair-orders.partials.repair-order-vehicle-identity-pressure-chip', ['repairOrder' => $repairOrder])
                </div>
                @if ($vehicleIdentityHint)
                    <p class="mt-1 text-[11px] font-semibold leading-4 text-amber-950/90">{{ $vehicleIdentityHint }}</p>
                @endif
            </div>
        @endif
        @if ($partsPressure->showsChip())
            <div class="border-b border-amber-100 bg-amber-50/50 px-3 py-2">
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="text-[10px] font-bold uppercase tracking-[0.08em] text-amber-800">Parts</span>
                    @include('operations.repair-orders.partials.repair-order-parts-pressure-chip', ['repairOrder' => $repairOrder])
                </div>
                @if ($partsPressureSummary)
                    <p class="mt-1 text-[11px] font-semibold leading-4 text-amber-950/90">{{ $partsPressureSummary }}</p>
                @endif
                @if ($repairOrder->status === \App\Ark\Operations\RepairOrders\RepairOrderStatus::WaitingParts)
                    <p class="mt-1 text-[11px] font-semibold leading-4 text-amber-950">Receive parts on the Parts tab, then change status here when work can continue.</p>
                @endif
            </div>
        @endif
    </section>
@endif
