@php
    use App\Ark\Operations\RepairOrders\RepairOrderVisitMode;
    use App\Ark\Runtime\Authorization\ArkCapability;

    $identity = $identity ?? App\Ark\Operations\RepairOrders\OperationalIdentityPresenter::forRepairOrder($repairOrder);
    $visitMode = RepairOrderVisitMode::fromRepairOrder($repairOrder);
    $mileageLineLabels = ['Mileage'];
    $canEditMileage = $identityVariant === 'staff'
        && isset($repairOrder)
        && auth()->user()?->can(ArkCapability::RepairOrdersManage->value);
    $showMileageInlineEditor = $canEditMileage && ($mileageEditable ?? true);
    // Identity stays editable after close — same posture as mileage. Visit posture stays open-RO only.
    $canEditCustomer = $identityVariant === 'staff'
        && isset($repairOrder)
        && auth()->user()?->can(ArkCapability::CustomersManage->value);
    $canEditVehicle = $identityVariant === 'staff'
        && isset($repairOrder)
        && auth()->user()?->can(ArkCapability::VehiclesManage->value);
    $canEditVisitPosture = $identityVariant === 'staff'
        && isset($repairOrder)
        && ! $repairOrder->isTerminal()
        && auth()->user()?->can(ArkCapability::RepairOrdersManage->value);
    $customerProfileHref = isset($repairOrder)
        ? route('operations.customers.show', $repairOrder->customer)
        : null;
    $scheduleFromRoHref = isset($repairOrder)
        && $identityVariant === 'staff'
        && \App\Ark\Operations\OperationsFeatures::appointmentsEnabled()
        ? \App\Ark\Operations\Appointments\ScheduleUrl::to([
            'repair_order' => $repairOrder->id,
        ])
        : null;
    $newRoFromExistingHref = isset($repairOrder)
        && $identityVariant === 'staff'
        && auth()->user()?->can(ArkCapability::RepairOrdersManage->value)
        ? route('operations.intake.create', array_filter([
            'customer_id' => $repairOrder->customer_id,
            'vehicle_id' => $repairOrder->vehicle_id ?: null,
            'source_repair_order_id' => $repairOrder->id,
        ]))
        : null;
    $arrivalPosture = $arrivalPosture ?? (
        isset($repairOrder)
        && $identityVariant !== 'staff'
        && \App\Ark\Operations\OperationsFeatures::appointmentsEnabled()
            ? app(\App\Ark\Operations\Appointments\ArrivalPostureProjection::class)->forRepairOrder($repairOrder)
            : null
    );
    $inspectionPosture = $inspectionPosture ?? (
        isset($repairOrder) && $identityVariant !== 'staff'
            ? app(\App\Ark\Operations\Inspections\InspectionPostureProjection::class)->forRepairOrder($repairOrder)
            : null
    );
    $vehicleProfileHref = isset($repairOrder)
        ? route('operations.customers.show', [
            'customer' => $repairOrder->customer,
            'vehicle' => $repairOrder->vehicle_id,
        ])
        : null;
    $identityChromePadding = ($embeddedInServiceLaneBand ?? false) ? 'px-2 py-1.5' : 'px-2.5 py-2';
@endphp

<div @class([
    'ops-ro-identity-band grid bg-slate-50 md:grid-cols-3',
    'border-b border-slate-200' => ! ($embeddedInServiceLaneBand ?? false),
    'bg-white' => in_array($identityVariant, ['document', 'document-pdf'], true),
]) @if (empty($embeddedInServiceLaneBand)) id="ro-identity-band" @endif>
    <section @class(['ops-ro-identity-col min-w-0 border-b border-slate-200 md:border-b-0 md:border-r md:border-slate-200', $identityChromePadding])>
        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Customer</p>
        @if ($canEditCustomer)
            @include('operations.repair-orders.partials.repair-order-identity-customer-inline', [
                'repairOrder' => $repairOrder,
                'identity' => $identity,
                'scheduleFromRoHref' => $scheduleFromRoHref,
                'newRoFromExistingHref' => $newRoFromExistingHref,
            ])
        @else
            <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                @if ($customerProfileHref && $identityVariant === 'staff')
                    <a href="{{ $customerProfileHref }}" class="ops-identity-title-link">{{ $identity['customer']['title'] }}</a>
                @else
                    <p class="text-[15px] font-extrabold leading-5 tracking-tight text-slate-950">{{ $identity['customer']['title'] }}</p>
                @endif
                <span class="ops-state-pill shrink-0">{{ $identity['customer']['type'] ?? 'Retail' }}</span>
                @if ($identityVariant === 'staff' && isset($repairOrder))
                    <a href="#communication-rail" class="ops-page-link shrink-0 text-[11px]">Message</a>
                @endif
                @if ($scheduleFromRoHref)
                    <a href="{{ $scheduleFromRoHref }}" class="ops-page-link shrink-0 text-[11px]">Schedule Follow-up</a>
                @endif
                @if (! empty($newRoFromExistingHref))
                    <a href="{{ $newRoFromExistingHref }}" class="ops-page-link shrink-0 text-[11px]" title="Open another repair order for this customer and vehicle">New RO</a>
                @endif
            </div>
            @if ($identityVariant === 'staff' && isset($repairOrder) && $repairOrder->customerIdentityPressure()->showsChip())
                <div class="mt-1">
                    @include('operations.repair-orders.partials.repair-order-customer-identity-pressure', ['repairOrder' => $repairOrder])
                </div>
            @endif
            <dl class="mt-1.5 space-y-0.5">
                @foreach ($identity['customer']['lines'] as $line)
                    <div class="grid grid-cols-[4.75rem_minmax(0,1fr)] gap-x-2 text-xs leading-4">
                        <dt class="font-semibold text-slate-500">{{ $line['label'] }}</dt>
                        <dd class="min-w-0 font-semibold text-slate-800 break-words">
                            @if (($line['href'] ?? null) && $identityVariant === 'staff')
                                <a href="{{ $line['href'] }}" class="text-slate-800 underline decoration-slate-300 underline-offset-2 hover:text-slate-950">{{ $line['value'] }}</a>
                            @else
                                {{ $line['value'] }}
                            @endif
                            @if (filled($line['secondary_value'] ?? null))
                                <span class="block">{{ $line['secondary_value'] }}</span>
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </section>

    <section @class(['ops-ro-identity-col min-w-0 border-b border-slate-200 md:border-b-0 md:border-r md:border-slate-200', $identityChromePadding])>
        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Vehicle</p>
        @if ($canEditVehicle)
            @include('operations.repair-orders.partials.repair-order-identity-vehicle-inline', [
                'repairOrder' => $repairOrder,
                'identity' => $identity,
                'hideMileageLines' => $showMileageInlineEditor,
            ])
        @else
            @if ($vehicleProfileHref && isset($repairOrder) && $identityVariant === 'staff')
                <a href="{{ $vehicleProfileHref }}" class="ops-identity-title-link mt-0.5 block">{{ $identity['vehicle']['title'] }}</a>
            @else
                <p class="mt-0.5 text-[15px] font-extrabold leading-5 tracking-tight text-slate-950">{{ $identity['vehicle']['title'] }}</p>
            @endif
            @if (filled($identity['vehicle']['subtitle'] ?? null))
                <p class="ops-meta mt-0.5">{{ $identity['vehicle']['subtitle'] }}</p>
            @endif
            @if ($identityVariant === 'staff' && isset($repairOrder) && $repairOrder->vehicleIdentityPressure()->showsChip())
                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                    @include('operations.repair-orders.partials.repair-order-vehicle-identity-pressure-chip', ['repairOrder' => $repairOrder])
                    @if ($vehicleIdentityHint = $repairOrder->vehicleIdentityPressureHint())
                        <span class="text-[11px] font-semibold leading-4 text-amber-900/90">{{ $vehicleIdentityHint }}</span>
                    @endif
                </div>
            @endif
            <dl class="mt-1.5 space-y-0.5">
                @foreach ($identity['vehicle']['lines'] as $line)
                    @if ($showMileageInlineEditor && in_array($line['label'], $mileageLineLabels, true))
                        @continue
                    @endif
                    <div class="grid grid-cols-[4.75rem_minmax(0,1fr)] gap-x-2 text-xs leading-4">
                        <dt class="font-semibold text-slate-500">{{ $line['label'] }}</dt>
                        <dd class="min-w-0 font-semibold text-slate-800 break-words">
                            @if ($line['label'] === 'VIN')
                                <x-operations.vin-display :vin="$line['value']" />
                            @else
                                {{ $line['value'] }}
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endif
        @if ($showMileageInlineEditor)
            @include('operations.repair-orders.partials.repair-order-mileage-inline', [
                'repairOrder' => $repairOrder,
                'estimateVersion' => $estimateVersion,
            ])
        @endif
    </section>

    <section @class(['ops-ro-identity-col min-w-0 border-b border-slate-200 md:border-b-0 md:last:border-r-0', $identityChromePadding])>
        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Visit</p>
        @include('operations.repair-orders.partials.repair-order-visit-posture-inline', [
            'repairOrder' => $repairOrder,
            'visitMode' => $visitMode,
            'canEdit' => $canEditVisitPosture,
        ])
        {{-- Staff: Appointment + Inspection live on the review toolbar (same bar as PartsTech). --}}
        @if ($identityVariant !== 'staff')
            @include('operations.repair-orders.partials.repair-order-arrival-posture-inline', [
                'arrivalPosture' => $arrivalPosture,
                'scheduleFromRoHref' => $scheduleFromRoHref,
                'repairOrder' => $repairOrder,
            ])
            @include('operations.repair-orders.partials.repair-order-inspection-posture-inline', [
                'inspectionPosture' => $inspectionPosture,
            ])
        @endif
        <dl class="mt-1.5 space-y-0.5">
            @foreach ($identity['visit']['lines'] as $line)
                <div class="grid grid-cols-[4.75rem_minmax(0,1fr)] gap-x-2 text-xs leading-4">
                    <dt class="font-semibold text-slate-500">{{ $line['label'] }}</dt>
                    <dd class="min-w-0 font-semibold text-slate-800 break-words">{{ $line['value'] }}</dd>
                </div>
            @endforeach
        </dl>
        @if ($identityVariant === 'staff' && isset($repairOrder) && $repairOrder->partsPressure()->showsChip())
            <div @class([
                'flex flex-wrap items-center gap-1.5',
                ($embeddedInServiceLaneBand ?? false) ? 'mt-1' : 'mt-1.5',
            ])>
                @include('operations.repair-orders.partials.repair-order-parts-pressure-chip', ['repairOrder' => $repairOrder])
                @if ($partsPressureSummary = $repairOrder->partsPressureSummary())
                    <span class="text-[11px] font-semibold leading-4 text-amber-900/90">{{ $partsPressureSummary }}</span>
                @endif
            </div>
        @endif
        @if ($identityVariant === 'staff' && filled($identity['visit']['posture'] ?? null))
            <p class="mt-1.5 text-[11px] font-bold leading-4 text-ops-accent">{{ $identity['visit']['posture'] }}</p>
        @endif
    </section>
</div>
@if (
    $identityVariant === 'staff'
    && isset($repairOrder)
    && filled(trim($repairOrder->customer->notes ?? ''))
)
    @include('operations.repair-orders.partials.repair-order-customer-notes-band', [
        'repairOrder' => $repairOrder,
    ])
@endif
