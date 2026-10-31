<x-operations.app title="Customers" ui="tabler">
    <x-tabler.page
        layout="fluid"
        title="Customers"
        pretitle="Records"
        subtitle="Browse customers or search by name, phone, email, plate, or VIN - then open the hub."
    >
        <x-slot:actions>
            <a href="{{ route('operations.vehicles.search') }}" class="btn d-none d-sm-inline-flex">
                <x-tabler.icon name="car" />
                Vehicles
            </a>
            <a href="{{ route('operations.vehicles.search') }}" class="btn btn-icon d-sm-none" title="Vehicles" aria-label="Vehicles" data-bs-toggle="tooltip">
                <x-tabler.icon name="car" />
            </a>
            <a href="{{ route('operations.workboard') }}" class="btn d-none d-sm-inline-flex">
                <x-tabler.icon name="layout-grid" />
                Workboard
            </a>
            <a href="{{ route('operations.workboard') }}" class="btn btn-icon d-sm-none" title="Workboard" aria-label="Workboard" data-bs-toggle="tooltip">
                <x-tabler.icon name="layout-grid" />
            </a>
            @can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value)
                <a href="{{ route('operations.intake.create') }}" class="btn btn-primary">
                    <x-tabler.icon name="plus" />
                    Check In
                </a>
            @endcan
        </x-slot:actions>

        @php
            $clearUrl = route('operations.customers.search', ($intakeMode ?? false) ? ['intake' => 1] : []);
            $activeFilterCount = collect([$selectedType ?? '', $createdFrom ?? '', $createdTo ?? ''])->filter(fn ($value) => filled($value))->count();
        @endphp
        <div class="card mb-3">
            <div class="card-body p-2 p-md-3">
                <form method="GET" action="{{ route('operations.customers.search') }}" role="search" aria-label="Search customers">
                    @if ($intakeMode ?? false)
                        <input type="hidden" name="intake" value="1">
                    @endif

                    <div class="row g-2 align-items-end">
                        <div class="col col-lg">
                            <label for="customer-search" class="form-label d-none d-md-block">Search</label>
                            <div class="input-icon">
                                <span class="input-icon-addon">
                                    <x-tabler.icon name="search" />
                                </span>
                                <input
                                    id="customer-search"
                                    name="q"
                                    value="{{ $query }}"
                                    type="search"
                                    autofocus
                                    autocomplete="off"
                                    placeholder="Name, phone, email, plate, or VIN"
                                    aria-label="Search customers"
                                    class="form-control"
                                >
                            </div>
                        </div>

                        <div class="col-auto order-lg-last">
                            <div class="btn-list flex-nowrap">
                                <button type="submit" class="btn btn-primary">Search</button>
                                @if ($hasFilters ?? false)
                                    <a href="{{ $clearUrl }}" class="btn btn-ghost-secondary d-none d-md-inline-flex">Clear</a>
                                @endif
                            </div>
                        </div>

                        <div class="col-12 d-md-none">
                            <div class="d-flex align-items-center">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-ghost-secondary"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#customer-filters"
                                    aria-controls="customer-filters"
                                    aria-expanded="{{ $activeFilterCount > 0 ? 'true' : 'false' }}"
                                >
                                    <x-tabler.icon name="adjustments-horizontal" />
                                    Filters
                                    @if ($activeFilterCount > 0)
                                        <span class="badge bg-primary text-primary-fg ms-2">{{ $activeFilterCount }}</span>
                                    @endif
                                </button>
                                @if ($hasFilters ?? false)
                                    <a href="{{ $clearUrl }}" class="btn btn-sm btn-ghost-secondary ms-auto">Clear</a>
                                @endif
                            </div>
                        </div>

                        <div @class(['col-12 col-lg-6 col-xxl-auto collapse d-md-block', 'show' => $activeFilterCount > 0]) id="customer-filters">
                            <div class="row g-2">
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
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="d-flex align-items-center mb-2 mb-md-3">
            <h3 class="mb-0">Customers</h3>
            <span class="ms-auto text-secondary">{{ $customers->total() }} total</span>
        </div>

        @if ($customers->isEmpty())
            <div class="card">
                <div class="empty">
                    <div class="empty-icon">
                        <x-tabler.icon name="users" />
                    </div>
                    <p class="empty-title">
                        @if ($query === '')
                            No customers yet.
                        @else
                            No customers match &ldquo;{{ $query }}&rdquo;.
                        @endif
                    </p>
                    @if ($hasFilters ?? false)
                        <div class="empty-action">
                            <a href="{{ $clearUrl }}" class="btn">Clear search</a>
                        </div>
                    @endif
                </div>
            </div>
        @else
            <div class="ark-card-grid">
                @foreach ($customers as $customer)
                    @php
                        $displayTz = config('app.display_timezone');
                        $activeRepairOrder = $customer->repairOrders->first();
                        $primaryVehicle = $customer->vehicles->first();
                        $cardTone = $activeRepairOrder ? $activeRepairOrder->status->indexTone() : 'move';
                        $cardColor = \App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor::tablerColorForChipTone($cardTone);
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
                    <article class="card card-sm h-100">
                        <div class="card-status-start bg-{{ $cardColor }}"></div>
                        <div class="card-body">
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
                        </div>
                        <div class="card-footer py-2">
                            @include('operations.partials.tabler-index-card-actions', ['indexActions' => $indexActions])
                        </div>
                    </article>
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
