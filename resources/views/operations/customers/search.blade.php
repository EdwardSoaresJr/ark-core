<x-operations.app title="Customers" ui="tabler">
    <x-tabler.page
        layout="fluid"
        title="Customers"
        pretitle="Records"
        subtitle="Browse customers or search by name, phone, email, plate, or VIN - then open the hub."
    >
        <x-slot:actions>
            <x-tabler.page-action :href="route('operations.vehicles.search')" icon="car" label="Vehicles" />
            <x-tabler.page-action :href="route('operations.workboard')" icon="layout-grid" label="Workboard" />
            @can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value)
                <x-tabler.page-action :href="route('operations.intake.create')" icon="plus" label="Check In" primary />
            @endcan
        </x-slot:actions>

        @php
            $clearUrl = route('operations.customers.search', ($intakeMode ?? false) ? ['intake' => 1] : []);
            $activeFilterCount = collect([$selectedType ?? '', $createdFrom ?? '', $createdTo ?? ''])->filter(fn ($value) => filled($value))->count();
        @endphp
        <x-tabler.search-filters
            id="customer"
            :action="route('operations.customers.search')"
            :query="$query"
            placeholder="Name, phone, email, plate, or VIN"
            label="Search customers"
            :clear-url="$clearUrl"
            :has-filters="$hasFilters ?? false"
            :active-filter-count="$activeFilterCount"
        >
            @if ($intakeMode ?? false)
                <x-slot:hidden>
                    <input type="hidden" name="intake" value="1">
                </x-slot:hidden>
            @endif

            <div class="col-12 col-md-4 col-xxl-auto">
                <label for="customer-type" class="form-label">Type</label>
                <select id="customer-type" name="type" class="form-select">
                    <option value="">Any type</option>
                    @foreach ($customerTypes as $customerType)
                        <option value="{{ $customerType['name'] }}" @selected(($selectedType ?? '') === $customerType['name'])>{{ $customerType['name'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-md-4 col-xxl-auto">
                <label for="customer-created-from" class="form-label">From</label>
                <input id="customer-created-from" name="created_from" type="date" value="{{ $createdFrom ?? '' }}" class="form-control">
            </div>

            <div class="col-6 col-md-4 col-xxl-auto">
                <label for="customer-created-to" class="form-label">To</label>
                <input id="customer-created-to" name="created_to" type="date" value="{{ $createdTo ?? '' }}" class="form-control">
            </div>
        </x-tabler.search-filters>

        <div class="d-flex align-items-center mb-2 mb-md-3">
            <h3 class="mb-0">Customers</h3>
            <span class="ms-auto text-secondary">{{ $customers->total() }} total</span>
        </div>

        @if ($customers->isEmpty())
            <x-tabler.empty-state icon="users" :clear-url="($hasFilters ?? false) ? $clearUrl : null">
                @if ($query === '')
                    No customers yet.
                @else
                    No customers match &ldquo;{{ $query }}&rdquo;.
                @endif
            </x-tabler.empty-state>
        @else
            <div class="ark-card-grid">
                @foreach ($customers as $customer)
                    @php
                        $displayTz = config('app.display_timezone');
                        $activeRepairOrder = $customer->repairOrders->first();
                        $primaryVehicle = $customer->vehicles->first();
                        $cardTone = $activeRepairOrder ? $activeRepairOrder->status->indexTone() : 'move';
                        $hubUrl = route('operations.customers.show', $customer);
                        $customerUrl = ($intakeMode ?? false)
                            ? route('operations.intake.create', ['customer_id' => $customer->id])
                            : $hubUrl;
                        $vehicleUrl = $primaryVehicle
                            ? route('operations.customers.show', [
                                'customer' => $customer,
                                'vehicle' => $primaryVehicle->id,
                            ])
                            : null;
                        $documentsUrl = route('operations.customers.show', [
                            'customer' => $customer,
                            'tab' => 'documents',
                        ]);
                        $roShowUrl = ($activeRepairOrder && filled($activeRepairOrder->repair_order_id))
                            ? route('operations.repair-orders.show', $activeRepairOrder)
                            : null;
                        $customerTag = trim((string) ($customer->customer_type ?: 'Retail'));
                        $hideDefaultRetail = ($intakeMode ?? false) && $customerTag === 'Retail';
                        $chipLabel = $activeRepairOrder
                            ? $activeRepairOrder->statusDisplayLabel()
                            : ($hideDefaultRetail ? 'No open RO' : $customerTag);
                        $vehicleCount = $customer->vehicles->count();
                        $clockLabel = $activeRepairOrder
                            ? 'RO #'.$activeRepairOrder->repair_order_id.' · Updated '.$activeRepairOrder->updated_at->timezone($displayTz)->format('M j, g:i A')
                            : $vehicleCount.' '.Str::plural('vehicle', $vehicleCount).' · Updated '.$customer->updated_at->timezone($displayTz)->format('M j, g:i A');
                        $indexActions = [
                            [
                                'href' => $vehicleUrl,
                                'key' => 'vehicle',
                                'label' => $primaryVehicle?->display_name ?? 'Vehicle',
                            ],
                            [
                                'href' => $documentsUrl,
                                'key' => 'documents',
                                'label' => 'Documents for '.$customer->name,
                            ],
                            [
                                'href' => $customerUrl,
                                'key' => 'customer',
                                'label' => $customer->name,
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
                        <div class="d-flex align-items-start gap-2">
                            <a href="{{ $customerUrl }}" class="fw-bold text-reset text-truncate" title="{{ $customer->name }}">{{ $customer->name }}</a>
                            <x-tabler.status-badge :tone="$cardTone" class="ms-auto flex-shrink-0">{{ $chipLabel }}</x-tabler.status-badge>
                        </div>
                        @if ($vehicleUrl !== null)
                            <a href="{{ $vehicleUrl }}" class="d-block text-truncate mt-1">{{ $primaryVehicle?->display_name ?? 'Unknown vehicle' }}</a>
                        @else
                            <div class="text-truncate text-secondary mt-1">{{ $primaryVehicle?->display_name ?? 'No vehicle on file' }}</div>
                        @endif
                        <div class="text-secondary">{{ $customer->display_phone ?: 'No phone' }}</div>
                        <div class="text-secondary small mt-1 mt-md-2">{{ $clockLabel }}</div>
                    </x-tabler.record-card>
                @endforeach
            </div>
        @endif

        @if ($customers->hasPages())
            <div class="mt-3">
                {{ $customers->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </x-tabler.page>
</x-operations.app>
