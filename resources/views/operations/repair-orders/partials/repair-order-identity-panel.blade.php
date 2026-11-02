@php
    use App\Ark\Operations\RepairOrders\RepairOrderVisitMode;
    use App\Ark\Runtime\Authorization\ArkCapability;

    $identity = $identity ?? App\Ark\Operations\RepairOrders\OperationalIdentityPresenter::forRepairOrder($repairOrder);
    $visitMode = RepairOrderVisitMode::fromRepairOrder($repairOrder);
    $mileageLineLabels = ['Mileage', 'Mileage in', 'Mileage out'];
    $user = auth()->user();
    $showMileageInlineEditor = (bool) $user?->can(ArkCapability::RepairOrdersManage->value) && ($mileageEditable ?? true);
    // Identity stays editable after close - same posture as mileage. Visit posture stays open-RO only.
    $canEditCustomer = (bool) $user?->can(ArkCapability::CustomersManage->value);
    $canEditVehicle = (bool) $user?->can(ArkCapability::VehiclesManage->value);
    $canEditVisitPosture = ! $repairOrder->isTerminal()
        && (bool) $user?->can(ArkCapability::RepairOrdersManage->value);
    $customerProfileHref = route('operations.customers.show', $repairOrder->customer);
    $scheduleFromRoHref = \App\Ark\Operations\OperationsFeatures::appointmentsEnabled()
        ? \App\Ark\Operations\Appointments\ScheduleUrl::to([
            'repair_order' => $repairOrder->id,
        ])
        : null;
    $newRoFromExistingHref = $user?->can(ArkCapability::RepairOrdersManage->value)
        ? route('operations.intake.create', array_filter([
            'customer_id' => $repairOrder->customer_id,
            'vehicle_id' => $repairOrder->vehicle_id ?: null,
            'source_repair_order_id' => $repairOrder->id,
        ]))
        : null;
    $vehicleProfileHref = route('operations.customers.show', [
        'customer' => $repairOrder->customer,
        'vehicle' => $repairOrder->vehicle_id,
    ]);
    $customer = $repairOrder->customer;
    $vehicle = $repairOrder->vehicle;
    $customerType = $identity['customer']['type'] ?? ($canEditCustomer ? ($customer->customer_type ?: 'Retail') : 'Retail');
    $vehicleLines = collect($identity['vehicle']['lines'])
        ->reject(fn (array $line): bool => in_array($line['label'], $mileageLineLabels, true));
    $visitLines = collect($identity['visit']['lines'])
        ->reject(fn (array $line): bool => $showMileageInlineEditor && in_array($line['label'], $mileageLineLabels, true));
    $customerNotes = trim($customer->notes ?? '');
@endphp

{{-- Worksheet continuity replaces this card by its id. --}}
<div class="card mb-3" id="ro-identity-band" data-identity-band>
    <div class="row g-0 ark-identity-panel">
        <section class="col-md-4 p-2 p-md-3 min-w-0" data-identity-present="customer" aria-labelledby="ro-identity-customer-heading">
            <h3 class="subheader mb-1" id="ro-identity-customer-heading">Customer</h3>
            <div class="d-flex flex-wrap align-items-center gap-2">
                @if ($canEditCustomer)
                    <button
                        type="button"
                        class="btn btn-link p-0 fw-bold text-reset text-start"
                        title="Open to edit customer"
                        data-identity-edit="customer"
                        @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'customer-identity', invokeEl: $event.currentTarget } }))"
                    >
                        {{ $identity['customer']['title'] }}
                    </button>
                @else
                    <a href="{{ $customerProfileHref }}" class="fw-bold">{{ $identity['customer']['title'] }}</a>
                @endif
                <span class="d-inline-flex align-items-center gap-1">
                    <span class="badge bg-secondary-lt">{{ $customerType }}</span>
                    @if ($canEditCustomer)
                        <x-operations.help-tip
                            text="Billing class sets default scope billing and standing discounts. Scope billing posture on each concern is financial authority. Changing billing class does not change billing on scopes already on this estimate."
                            label="Billing class help"
                        />
                    @endif
                </span>
            </div>
            <div class="d-flex flex-wrap gap-2 small mt-1">
                <a href="#communication-rail">Message</a>
                @if ($scheduleFromRoHref)
                    <a href="{{ $scheduleFromRoHref }}">Schedule Follow-up</a>
                @endif
                @if ($newRoFromExistingHref)
                    <a href="{{ $newRoFromExistingHref }}" title="Open another repair order for this customer and vehicle">New RO</a>
                @endif
            </div>

            @if ($canEditCustomer ? $customer->identityPressure()->showsChip() : $repairOrder->customerIdentityPressure()->showsChip())
                <div class="mt-2">
                    @include('operations.repair-orders.partials.repair-order-customer-identity-pressure', [
                        'repairOrder' => $canEditCustomer ? null : $repairOrder,
                        'customer' => $customer,
                        'showMissingFields' => true,
                        'ui' => 'tabler',
                    ])
                </div>
            @endif

            <dl class="row g-0 small mt-2 mb-0">
                @foreach ($identity['customer']['lines'] as $line)
                    <dt class="col-4 text-secondary fw-normal">{{ $line['label'] }}</dt>
                    <dd class="col-8 mb-1 fw-medium text-break">
                        @if ($line['href'] ?? null)
                            <a href="{{ $line['href'] }}" class="text-reset">{{ $line['value'] }}</a>
                            @if (! $canEditCustomer && filled($line['secondary_value'] ?? null))
                                <span class="d-block">{{ $line['secondary_value'] }}</span>
                            @endif
                        @else
                            <span class="d-block">{{ $line['value'] }}</span>
                            @if (filled($line['secondary_value'] ?? null))
                                <span class="d-block">{{ $line['secondary_value'] }}</span>
                            @endif
                        @endif
                    </dd>
                @endforeach
            </dl>
        </section>

        <section class="col-md-4 p-2 p-md-3 min-w-0" data-identity-present="vehicle" aria-labelledby="ro-identity-vehicle-heading">
            <h3 class="subheader mb-1" id="ro-identity-vehicle-heading">Vehicle</h3>
            @if ($canEditVehicle)
                <button
                    type="button"
                    class="btn btn-link p-0 fw-bold text-reset text-start"
                    title="Open to edit vehicle"
                    data-identity-edit="vehicle"
                    @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'vehicle-identity', invokeEl: $event.currentTarget } }))"
                >
                    {{ $identity['vehicle']['title'] }}
                </button>

                @include('operations.repair-orders.partials.repair-order-vehicle-change', [
                    'repairOrder' => $repairOrder,
                ])
            @else
                <a href="{{ $vehicleProfileHref }}" class="fw-bold d-block">{{ $identity['vehicle']['title'] }}</a>
            @endif

            @if (filled($identity['vehicle']['subtitle'] ?? null))
                <div class="small text-secondary mt-1">{{ $identity['vehicle']['subtitle'] }}</div>
            @endif

            @if ($canEditVehicle ? ! filled($vehicle->authoritativeVin()) : $repairOrder->vehicleIdentityPressure()->showsChip())
                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                    @include('operations.repair-orders.partials.repair-order-vehicle-identity-pressure-chip', [
                        'repairOrder' => $repairOrder,
                        'ui' => 'tabler',
                    ])
                    @if (! $canEditVehicle && ($vehicleIdentityHint = $repairOrder->vehicleIdentityPressureHint()))
                        <span class="small text-warning">{{ $vehicleIdentityHint }}</span>
                    @endif
                </div>
            @endif

            <dl class="row g-0 small mt-2 mb-0">
                @foreach ($vehicleLines as $line)
                    <dt class="col-4 text-secondary fw-normal">{{ $line['label'] }}</dt>
                    <dd class="col-8 mb-1 fw-medium text-break">
                        @if ($line['label'] === 'VIN')
                            <x-tabler.vin-display :vin="$line['value']" />
                        @else
                            {{ $line['value'] }}
                        @endif
                    </dd>
                @endforeach
            </dl>
        </section>

        <section class="col-md-4 p-2 p-md-3 min-w-0" data-identity-present="visit" aria-labelledby="ro-identity-visit-heading">
            <h3 class="subheader mb-1" id="ro-identity-visit-heading">Visit</h3>
            @include('operations.repair-orders.partials.repair-order-visit-posture-inline', [
                'repairOrder' => $repairOrder,
                'visitMode' => $visitMode,
                'canEdit' => $canEditVisitPosture,
                'ui' => 'tabler',
            ])

            <dl class="row g-0 small mt-2 mb-0">
                @if ($showMileageInlineEditor)
                    @include('operations.repair-orders.partials.repair-order-mileage-inline', [
                        'repairOrder' => $repairOrder,
                        'estimateVersion' => $estimateVersion,
                    ])
                @endif
                @foreach ($visitLines as $line)
                    <dt class="col-4 text-secondary fw-normal">{{ $line['label'] }}</dt>
                    <dd class="col-8 mb-1 fw-medium text-break">{{ $line['value'] }}</dd>
                @endforeach
            </dl>

            @if ($repairOrder->partsPressure()->showsChip())
                <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                    @include('operations.repair-orders.partials.repair-order-parts-pressure-chip', [
                        'repairOrder' => $repairOrder,
                        'ui' => 'tabler',
                    ])
                    @if ($partsPressureSummary = $repairOrder->partsPressureSummary())
                        <span class="small text-warning">{{ $partsPressureSummary }}</span>
                    @endif
                </div>
            @endif

            @if (filled($identity['visit']['posture'] ?? null))
                <div class="small fw-bold text-primary mt-2">{{ $identity['visit']['posture'] }}</div>
            @endif
        </section>
    </div>

    @if ($customerNotes !== '')
        @include('operations.repair-orders.partials.repair-order-customer-notes-band', [
            'repairOrder' => $repairOrder,
        ])
    @endif
</div>
