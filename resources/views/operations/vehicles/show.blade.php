<x-operations.app :title="$vehicle->operational_identity" ui="tabler">
    <x-tabler.page
        layout="fluid"
        :title="$vehicle->operational_identity"
        :breadcrumbs="[
            ['label' => 'Vehicles', 'url' => route('operations.vehicles.search')],
            ...($customer ? [['label' => $customer->name, 'url' => route('operations.customers.show', $customer)]] : []),
        ]"
        actions-label="Vehicle commands"
    >
        <x-slot:actions>
            @if ($customer && filled($customer->phone))
                @php($callHref = \App\Ark\Operations\PhoneNumber::telUri($customer->phone))
                @if ($callHref)
                    <x-tabler.page-action :href="$callHref" icon="phone" label="Call owner" />
                @endif
            @endif
            @if ($customer && \App\Ark\Operations\OperationsFeatures::appointmentsEnabled())
                <x-tabler.page-action
                    :href="\App\Ark\Operations\Appointments\ScheduleUrl::to([
                        'customer' => $customer->id,
                        'vehicle' => $vehicle->id,
                    ])"
                    icon="calendar"
                    label="Schedule"
                />
            @endif
            @can(App\Ark\Runtime\Authorization\ArkCapability::VehiclesManage->value)
                @if ($customer)
                    <x-tabler.page-action
                        :href="route('operations.customers.show', [
                            'customer' => $customer,
                            'vehicle' => $vehicle->id,
                            'tab' => 'vehicles',
                            'edit' => 1,
                        ])"
                        icon="pencil"
                        label="Edit Vehicle"
                    />
                @endif
            @endcan
            @can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value)
                <x-tabler.page-action
                    :href="route('operations.intake.create', array_filter([
                        'customer_id' => $customer?->id,
                        'vehicle_id' => $vehicle->id,
                    ]))"
                    icon="plus"
                    label="Create RO"
                    primary
                />
            @endcan
        </x-slot:actions>

        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif

        <div class="row g-3">
            <div class="col-12 col-lg-5 col-xl-4">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title mb-1">{{ $vehicle->operational_identity }}</h3>
                        <div class="text-secondary small mb-3">
                            @if ($customer)
                                Owner
                                <a href="{{ route('operations.customers.show', $customer) }}">{{ $customer->name }}</a>
                            @else
                                Owner not linked
                            @endif
                        </div>
                        <dl class="row mb-0 small">
                            <dt class="col-4 text-secondary">VIN</dt>
                            <dd class="col-8">{{ $vehicle->normalized_vin ?: ($vehicle->vin ?: '—') }}</dd>
                            <dt class="col-4 text-secondary">Plate</dt>
                            <dd class="col-8">
                                {{ $vehicle->plate ?: '—' }}
                                @if ($vehicle->plate_state)
                                    / {{ $vehicle->plate_state }}
                                @endif
                            </dd>
                            <dt class="col-4 text-secondary">Engine</dt>
                            <dd class="col-8">{{ $vehicle->engine ?: '—' }}</dd>
                            <dt class="col-4 text-secondary">Drive</dt>
                            <dd class="col-8">{{ $vehicle->drive ?: '—' }}</dd>
                            <dt class="col-4 text-secondary">Color</dt>
                            <dd class="col-8">{{ $vehicle->color ?: '—' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-7 col-xl-8">
                <div class="card mb-3">
                    <div class="card-body">
                        <h3 class="card-title">Current work</h3>
                        @forelse ($activeRepairOrders as $repairOrder)
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 @if (! $loop->first) border-top @endif">
                                <div>
                                    <a href="{{ route('operations.repair-orders.show', $repairOrder) }}" class="fw-bold">
                                        RO #{{ $repairOrder->repair_order_id }}
                                    </a>
                                    <div class="small text-secondary">
                                        {{ $repairOrder->status->label() }}
                                        @if ($repairOrder->lines->first()?->concern?->title)
                                            · {{ $repairOrder->lines->first()->concern->title }}
                                        @endif
                                    </div>
                                </div>
                                <a href="{{ route('operations.repair-orders.show', $repairOrder) }}" class="btn btn-sm">Open RO</a>
                            </div>
                        @empty
                            <p class="text-secondary mb-0 small">No active repair orders on this vehicle.</p>
                        @endforelse
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Service history</h3>
                        @forelse ($recentRepairOrders as $repairOrder)
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 @if (! $loop->first) border-top @endif">
                                <div>
                                    <a href="{{ route('operations.repair-orders.show', $repairOrder) }}">
                                        RO #{{ $repairOrder->repair_order_id }}
                                    </a>
                                    <div class="small text-secondary">
                                        {{ $repairOrder->status->label() }}
                                        · {{ $repairOrder->updated_at->timezone(config('app.display_timezone'))->format('M j, Y') }}
                                    </div>
                                </div>
                                <span class="badge bg-secondary-lt">{{ $repairOrder->status->label() }}</span>
                            </div>
                        @empty
                            <p class="text-secondary mb-0 small">No closed visits on file yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </x-tabler.page>
</x-operations.app>
