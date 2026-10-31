<x-operations.app title="Vehicles" ui="tabler">
    <x-tabler.page
        layout="fluid"
        title="Vehicles"
        pretitle="Records"
        subtitle="Browse vehicles or search by plate, VIN, YMM, nickname, or customer - then open the hub."
    >
        <x-slot:actions>
            <x-tabler.page-action :href="route('operations.customers.search')" icon="users" label="Customers" />
            <x-tabler.page-action :href="route('operations.repair-orders.index')" icon="file-text" label="Repair Orders" />
            @can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value)
                <x-tabler.page-action :href="route('operations.intake.create')" icon="plus" label="Check In" primary />
            @endcan
        </x-slot:actions>

        @php
            $clearUrl = route('operations.vehicles.search');
            $activeFilterCount = collect([$selectedWork ?? '', $createdFrom ?? '', $createdTo ?? ''])->filter(fn ($value) => filled($value))->count();
        @endphp
        <x-tabler.search-filters
            id="vehicle"
            :action="route('operations.vehicles.search')"
            :query="$query"
            placeholder="Plate, VIN, year, make, model, nickname, or customer"
            label="Search vehicles"
            :clear-url="$clearUrl"
            :has-filters="$hasFilters ?? false"
            :active-filter-count="$activeFilterCount"
        >
            <div class="col-12 col-md-4 col-xxl-auto">
                <label for="vehicle-work" class="form-label">Work</label>
                <select id="vehicle-work" name="work" class="form-select">
                    <option value="">Any work</option>
                    <option value="open" @selected(($selectedWork ?? '') === 'open')>Open RO</option>
                    <option value="idle" @selected(($selectedWork ?? '') === 'idle')>No open RO</option>
                </select>
            </div>

            <div class="col-6 col-md-4 col-xxl-auto">
                <label for="vehicle-created-from" class="form-label">From</label>
                <input id="vehicle-created-from" name="created_from" type="date" value="{{ $createdFrom ?? '' }}" class="form-control">
            </div>

            <div class="col-6 col-md-4 col-xxl-auto">
                <label for="vehicle-created-to" class="form-label">To</label>
                <input id="vehicle-created-to" name="created_to" type="date" value="{{ $createdTo ?? '' }}" class="form-control">
            </div>
        </x-tabler.search-filters>

        <div class="d-flex align-items-center mb-2 mb-md-3">
            <h3 class="mb-0">Vehicles</h3>
            <span class="ms-auto text-secondary">{{ $vehicles->total() }} total</span>
        </div>

        @if ($vehicles->isEmpty())
            <x-tabler.empty-state icon="car" :clear-url="($hasFilters ?? false) ? $clearUrl : null">
                @if ($query === '')
                    No vehicles yet.
                @else
                    No vehicles match &ldquo;{{ $query }}&rdquo;.
                @endif
            </x-tabler.empty-state>
        @else
            <div class="ark-card-grid">
                @foreach ($vehicles as $vehicle)
                    @php
                        $displayTz = config('app.display_timezone');
                        $activeRepairOrder = $vehicle->repairOrders->first();
                        $cardTone = $activeRepairOrder ? $activeRepairOrder->status->indexTone() : 'move';
                        $customer = $vehicle->customer;
                        $hubUrl = $customer
                            ? route('operations.customers.show', [
                                'customer' => $customer,
                                'vehicle' => $vehicle->id,
                            ])
                            : null;
                        $customerUrl = $customer
                            ? route('operations.customers.show', $customer)
                            : null;
                        $documentsUrl = $customer
                            ? route('operations.customers.show', [
                                'customer' => $customer,
                                'tab' => 'documents',
                            ])
                            : null;
                        $roShowUrl = ($activeRepairOrder && filled($activeRepairOrder->repair_order_id))
                            ? route('operations.repair-orders.show', $activeRepairOrder)
                            : null;
                        $plateLabel = trim(collect([$vehicle->plate, $vehicle->plate_state])->filter()->implode(' '));
                        $vinTail = filled($vehicle->vin) ? substr($vehicle->vin, -8) : null;
                        $identityBits = collect([$plateLabel !== '' ? $plateLabel : null, $vinTail ? 'VIN '.$vinTail : null])
                            ->filter()
                            ->implode(' · ');
                        $chipLabel = $activeRepairOrder
                            ? $activeRepairOrder->statusDisplayLabel()
                            : 'No open RO';
                        $clockLabel = $activeRepairOrder
                            ? 'RO #'.$activeRepairOrder->repair_order_id.' · Updated '.$activeRepairOrder->updated_at->timezone($displayTz)->format('M j, g:i A')
                            : $vehicle->repair_orders_count.' '.Str::plural('RO', $vehicle->repair_orders_count).' on file · Updated '.$vehicle->updated_at->timezone($displayTz)->format('M j, g:i A');
                        $indexActions = [
                            [
                                'href' => $hubUrl,
                                'key' => 'vehicle',
                                'label' => $vehicle->display_name,
                            ],
                            [
                                'href' => $documentsUrl,
                                'key' => 'documents',
                                'label' => $customer ? 'Documents for '.$customer->name : 'Documents',
                            ],
                            [
                                'href' => $customerUrl,
                                'key' => 'customer',
                                'label' => $customer?->name ?? 'Customer',
                            ],
                            [
                                'href' => $roShowUrl,
                                'key' => 'ro',
                                'label' => $activeRepairOrder
                                    ? 'RO #'.$activeRepairOrder->repair_order_id
                                    : 'Repair order',
                            ],
                        ];
                    @endphp
                    <x-tabler.record-card :tone="$cardTone" :actions="$indexActions">
                        @if ($hubUrl !== null)
                            <a href="{{ $hubUrl }}" class="d-block fw-bold text-reset text-truncate" title="{{ $vehicle->display_name }}">{{ $vehicle->operational_identity }}</a>
                        @else
                            <div class="fw-bold text-truncate" title="{{ $vehicle->display_name }}">{{ $vehicle->operational_identity }}</div>
                        @endif
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            <x-tabler.status-badge :tone="$cardTone">{{ $chipLabel }}</x-tabler.status-badge>
                            <x-tabler.vehicle-identity-chip :vehicle="$vehicle" />
                        </div>
                        @if ($customerUrl !== null)
                            <a href="{{ $customerUrl }}" class="d-block text-truncate mt-1">{{ $customer?->name ?? 'Unknown customer' }}</a>
                        @else
                            <div class="text-truncate text-secondary mt-1">{{ $customer?->name ?? 'Unknown customer' }}</div>
                        @endif
                        <div class="text-secondary text-truncate">{{ $identityBits !== '' ? $identityBits : 'No plate or VIN on file' }}</div>
                        <div class="text-secondary small mt-1 mt-md-2">{{ $clockLabel }}</div>
                    </x-tabler.record-card>
                @endforeach
            </div>
        @endif

        @if ($vehicles->hasPages())
            <div class="mt-3">
                {{ $vehicles->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </x-tabler.page>
</x-operations.app>
