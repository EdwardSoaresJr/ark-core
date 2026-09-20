@php
    $lifecycleMilestones = app(\App\Ark\Operations\RepairOrders\RepairOrderLifecycleProjection::class)->for($repairOrder);
    $productionMetrics = app(\App\Ark\Operations\RepairOrders\RepairOrderWorkDurationProjection::class)->for($repairOrder);
    $displayTimezone = config('app.display_timezone');
    $partsPressure = $repairOrder->partsPressure();
    $partsPressureSummary = $repairOrder->partsPressureSummary();
    $customerIdentityPressure = $repairOrder->customerIdentityPressure();
    $customerIdentityHint = $repairOrder->customerIdentityPressureHint();
    $vehicleIdentityPressure = $repairOrder->vehicleIdentityPressure();
    $vehicleIdentityHint = $repairOrder->vehicleIdentityPressureHint();
@endphp

<section class="ops-review-panel mt-2" aria-label="Repair order status history">
    <p class="border-b border-slate-100 px-3 py-2 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Status history</p>
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
    <dl class="divide-y divide-slate-100">
        @foreach ($lifecycleMilestones as $milestone)
            <div class="flex items-baseline justify-between gap-3 px-3 py-1.5">
                <dt class="min-w-0 text-xs font-medium text-slate-600">{{ $milestone['label'] }}</dt>
                <dd class="shrink-0 text-right">
                    @if ($milestone['occurred_at'] instanceof \Illuminate\Support\Carbon)
                        <time
                            datetime="{{ $milestone['occurred_at']->toIso8601String() }}"
                            class="text-xs font-semibold tabular-nums text-slate-900"
                            title="{{ $milestone['source'] }}"
                        >
                            {{ $milestone['occurred_at']->timezone($displayTimezone)->format('M j, g:i A') }}
                        </time>
                    @else
                        <span class="text-xs font-semibold tabular-nums text-slate-300" title="{{ $milestone['note'] ?? $milestone['source'] }}">—</span>
                    @endif
                </dd>
            </div>
        @endforeach
    </dl>
    @if (collect($productionMetrics)->contains(fn (array $metric): bool => $metric['duration_label'] !== null))
        <div class="border-t border-slate-100">
            <p class="border-b border-slate-100 px-3 py-2 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Production metrics</p>
            <dl class="divide-y divide-slate-100">
                @foreach ($productionMetrics as $metric)
                    @if ($metric['duration_label'] !== null)
                        <div class="flex items-baseline justify-between gap-3 px-3 py-1.5">
                            <dt class="min-w-0 text-xs font-medium text-slate-600">{{ $metric['label'] }}</dt>
                            <dd class="shrink-0 text-right">
                                <span class="text-xs font-semibold tabular-nums text-slate-900">{{ $metric['duration_label'] }}</span>
                            </dd>
                        </div>
                    @endif
                @endforeach
            </dl>
        </div>
    @endif
</section>
