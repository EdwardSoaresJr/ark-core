<x-operations.app title="Repair Orders" ui="tabler">
    @php
        $laneQueueLabel = $laneFilter !== null
            ? \App\Ark\Operations\Workboard\WorkboardSwimlaneCatalog::inventoryLabelForLane($laneFilter)
            : null;
        $attentionQueueLabel = $attentionFilter !== null
            ? \App\Ark\Operations\Workboard\WorkboardAttentionInventoryQuery::label($attentionFilter)
            : null;
        $dispositionQueueLabel = match ($dispositionFilter ?? null) {
            'recommended' => 'Pending (recommended) work',
            'declined' => 'Declined work',
            'approved' => 'Approved work',
            default => null,
        };
        $openQueueLabel = ($openQueueFilter ?? false) ? 'Open queue' : null;
        $hasFilters = $query !== '' || $selectedStatus !== null || $createdFrom !== null || $createdTo !== null || $pickupFilter !== null || $unassignedFilter || $laneFilter !== null || $attentionFilter !== null || $pipelineFilter !== null || ($dispositionFilter ?? null) !== null || ($openQueueFilter ?? false);
        $pickupQueueLabel = match ($pickupFilter) {
            'all' => 'Awaiting pickup queue',
            'stale' => 'Overdue pickup queue',
            default => null,
        };
        $queueLabel = $unassignedFilter
            ? 'Unassigned shop floor queue'
            : ($pipelineFilterLabel ?? $attentionQueueLabel ?? $laneQueueLabel ?? $pickupQueueLabel ?? $dispositionQueueLabel ?? $openQueueLabel);
        $subtitle = match (true) {
            ($dispositionFilter ?? null) !== null || ($openQueueFilter ?? false) => $queueLabel.' - drill-down from Shop Dashboard. Open an RO to act.',
            $queueLabel !== null => $queueLabel.' - full inventory from the workboard overflow link.',
            default => 'Search active and historical ROs without changing the live workboard queue.',
        };
        $clearUrl = route('operations.repair-orders.index');
        $activeFilterCount = collect([$selectedStatus, $createdFrom, $createdTo])->filter(fn ($value) => filled($value))->count();
    @endphp

    <x-tabler.page layout="fluid" title="Repair Orders" pretitle="Records" :subtitle="$subtitle">
        <x-slot:actions>
            @if ($workboardReturnUrl ?? null)
                <x-tabler.page-action :href="$workboardReturnUrl" icon="arrow-left" label="Job Board triage" />
            @endif
            <x-tabler.page-action :href="route('operations.workboard')" icon="layout-grid" label="Job Board" />
            <x-tabler.page-action :href="route('operations.customers.search')" icon="users" label="Customers" />
            <x-tabler.page-action :href="route('operations.vehicles.search')" icon="car" label="Vehicles" />
        </x-slot:actions>

        <x-tabler.search-filters
            id="ro"
            :action="route('operations.repair-orders.index')"
            :query="$query"
            placeholder="RO #, customer, phone, vehicle, VIN, plate"
            label="Search repair orders"
            :clear-url="$clearUrl"
            :has-filters="$hasFilters"
            :active-filter-count="$activeFilterCount"
        >
            <x-slot:hidden>
                @if ($pickupFilter !== null)
                    <input type="hidden" name="pickup" value="{{ $pickupFilter }}">
                @endif
                @if ($unassignedFilter)
                    <input type="hidden" name="unassigned" value="1">
                @endif
                @if ($laneFilter !== null)
                    <input type="hidden" name="lane" value="{{ $laneFilter }}">
                @endif
                @if ($attentionFilter !== null)
                    <input type="hidden" name="attention" value="{{ $attentionFilter }}">
                @endif
                @if ($dispositionFilter ?? null)
                    <input type="hidden" name="disposition" value="{{ $dispositionFilter }}">
                @endif
                @if ($openQueueFilter ?? false)
                    <input type="hidden" name="open" value="1">
                @endif
            </x-slot:hidden>

            <div class="col-12 col-md-4 col-xxl-auto">
                <label for="ro-status" class="form-label">Status</label>
                <select id="ro-status" name="status" class="form-select">
                    <option value="">Any status</option>
                    @foreach ($statusFilterOptions as $statusOption)
                        <option value="{{ $statusOption['value'] }}" @selected($selectedStatus === $statusOption['value'])>{{ $statusOption['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-md-4 col-xxl-auto">
                <label for="ro-created-from" class="form-label">From</label>
                <input id="ro-created-from" name="created_from" type="date" value="{{ $createdFrom ?? '' }}" class="form-control">
            </div>

            <div class="col-6 col-md-4 col-xxl-auto">
                <label for="ro-created-to" class="form-label">To</label>
                <input id="ro-created-to" name="created_to" type="date" value="{{ $createdTo ?? '' }}" class="form-control">
            </div>
        </x-tabler.search-filters>

        <div class="d-flex align-items-center mb-2 mb-md-3">
            <h3 class="mb-0">{{ $queueLabel ?? 'Results' }}</h3>
            <span class="ms-auto text-secondary">{{ $repairOrders->total() }} total</span>
        </div>

        @if ($repairOrders->isEmpty())
            <x-tabler.empty-state icon="file-text" :clear-url="$hasFilters ? $clearUrl : null">
                @if ($hasFilters)
                    No repair orders match these filters.
                @else
                    No repair orders yet.
                @endif
            </x-tabler.empty-state>
        @else
            <div class="ark-card-grid">
                @foreach ($repairOrders as $repairOrder)
                    @php
                        $displayTz = config('app.display_timezone');
                        $customer = $repairOrder->customer;
                        $vehicle = $repairOrder->vehicle;
                        $roShowUrl = filled($repairOrder->repair_order_id)
                            ? route('operations.repair-orders.show', $repairOrder)
                            : null;
                        $customerUrl = $customer
                            ? route('operations.customers.show', $customer)
                            : null;
                        $vehicleUrl = ($customer && $vehicle)
                            ? route('operations.customers.show', [
                                'customer' => $customer,
                                'vehicle' => $vehicle->id,
                            ])
                            : null;
                        $documentsUrl = $customer
                            ? route('operations.customers.show', [
                                'customer' => $customer,
                                'tab' => 'documents',
                            ])
                            : null;
                        $cardTone = $repairOrder->status->indexTone();
                        $indexActions = [
                            [
                                'href' => $vehicleUrl,
                                'key' => 'vehicle',
                                'label' => $vehicle?->display_name ?? 'Vehicle',
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
                                'label' => filled($repairOrder->repair_order_id) ? 'RO #'.$repairOrder->repair_order_id : 'Repair order',
                            ],
                        ];
                        $openedLabel = 'Opened '.$repairOrder->displayOpenedAt()->timezone($displayTz)->format('M j, Y');
                        if ($closedAt = $repairOrder->displayClosedAt()) {
                            $clockLabel = $openedLabel.' · Closed '.$closedAt->timezone($displayTz)->format('M j, g:i A');
                        } else {
                            $clockLabel = $openedLabel.' · Updated '.$repairOrder->updated_at->timezone($displayTz)->format('M j, g:i A');
                        }
                    @endphp
                    <x-tabler.record-card :tone="$cardTone" :actions="$indexActions">
                        <div class="d-flex align-items-start gap-2">
                            @if ($roShowUrl !== null)
                                <a href="{{ $roShowUrl }}" class="fw-bold text-reset">RO #{{ $repairOrder->repair_order_id }}</a>
                            @else
                                <span class="fw-bold">RO #{{ $repairOrder->repair_order_id }}</span>
                            @endif
                            <x-tabler.status-badge :tone="$cardTone" class="ms-auto">{{ $repairOrder->statusDisplayLabel() }}</x-tabler.status-badge>
                        </div>
                        @if ($vehicleUrl !== null)
                            <a href="{{ $vehicleUrl }}" class="d-block text-truncate mt-1">{{ $vehicle?->display_name ?? 'Unknown vehicle' }}</a>
                        @else
                            <div class="text-truncate mt-1">{{ $vehicle?->display_name ?? 'Unknown vehicle' }}</div>
                        @endif
                        @if ($customerUrl !== null)
                            <a href="{{ $customerUrl }}" class="d-block text-truncate">{{ $customer?->name ?? 'Unknown customer' }}</a>
                        @else
                            <div class="text-truncate text-secondary">{{ $customer?->name ?? 'Unknown customer' }}</div>
                        @endif
                        @if (filled($repairOrder->concern_summary))
                            <div class="text-secondary text-truncate" title="{{ $repairOrder->concern_summary }}">{{ $repairOrder->concern_summary }}</div>
                        @endif
                        <div class="text-secondary small mt-1 mt-md-2">{{ $clockLabel }}</div>
                    </x-tabler.record-card>
                @endforeach
            </div>
        @endif

        @if ($repairOrders->hasPages())
            <div class="mt-3">
                {{ $repairOrders->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </x-tabler.page>
</x-operations.app>
