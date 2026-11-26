@php
    /** @var \App\Ark\Ui\Collection\CollectionSortState $collectionSort */
    /** @var \App\Ark\Runtime\Preferences\CollectionLayoutModule $collectionLayoutModule */
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
    $cookieDomain = \App\Ark\Runtime\Preferences\EcosystemDisplayTheme::cookieDomain();
    $routeName = 'operations.repair-orders.index';
    $filterQuery = $collectionFilterQuery ?? [];
@endphp

<x-operations.app title="Repair Orders" ui="tabler">
    <x-tabler.page layout="fluid" title="Repair Orders" pretitle="Records" :subtitle="$subtitle">
        <x-slot:actions>
            @if ($workboardReturnUrl ?? null)
                <x-tabler.page-action :href="$workboardReturnUrl" icon="arrow-left" label="Shop Queue triage" />
            @endif
            <x-tabler.page-action :href="route('operations.workboard')" icon="layout-grid" label="Shop Queue" />
            <x-tabler.page-action :href="route('operations.customers.search')" icon="users" label="Customers" />
            <x-tabler.page-action :href="route('operations.vehicles.search')" icon="car" label="Vehicles" />
        </x-slot:actions>

        <div
            data-collection
            data-collection-module="{{ $collectionLayoutModule->value }}"
            data-collection-layout="{{ $collectionLayoutMode }}"
            data-collection-layout-explicit="{{ $collectionLayoutExplicit ? '1' : '0' }}"
            data-collection-cookie="{{ $collectionLayoutModule->cookieName() }}"
            data-collection-cookie-domain="{{ $cookieDomain }}"
            data-ark-ui-tenant="{{ $uiPreferenceScope['tenant'] ?? 'local' }}"
            data-ark-ui-user="{{ $uiPreferenceScope['user'] ?? 'anon' }}"
        >
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
                    @if ($pipelineFilter ?? null)
                        <input type="hidden" name="pipeline" value="{{ $pipelineFilter }}">
                    @endif
                    @if ($collectionSort->key !== $collectionSort->defaultKey || $collectionSort->dir !== ($collectionSort->defaultDirs[$collectionSort->defaultKey] ?? 'asc'))
                        <input type="hidden" name="sort" value="{{ $collectionSort->key }}">
                        <input type="hidden" name="dir" value="{{ $collectionSort->dir }}">
                    @endif
                    <input
                        type="hidden"
                        name="view"
                        value="{{ $collectionLayoutMode === 'auto' ? '' : $collectionLayoutMode }}"
                        data-collection-view-input
                        @if ($collectionLayoutMode === 'auto') disabled @endif
                    >
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

            <x-tabler.collection-summary
                :title="$queueLabel ?? 'Results'"
                :paginator="$repairOrders"
                :has-filters="$hasFilters"
                :clear-url="$clearUrl"
            >
                <x-tabler.layout-toggle :mode="$collectionLayoutMode" aria-label="Repair Orders layout" class="ms-md-2" />
            </x-tabler.collection-summary>

            @if ($repairOrders->isEmpty())
                <x-tabler.empty-state icon="file-text" :clear-url="$hasFilters ? $clearUrl : null">
                    @if ($hasFilters)
                        No repair orders match these filters.
                    @else
                        No repair orders yet.
                    @endif
                </x-tabler.empty-state>
            @else
                <div data-collection-view="list">
                    <x-tabler.hybrid-records>
                        <x-slot:sort>
                            <x-tabler.hybrid-sort
                                :sort="$collectionSort"
                                :route-name="$routeName"
                                :filter-query="$filterQuery"
                                label="Sort repair orders"
                                :columns="['ro' => 'RO #', 'vehicle' => 'Vehicle', 'customer' => 'Customer', 'status' => 'Status', 'opened' => 'Opened', 'updated' => 'Updated']"
                            />
                        </x-slot:sort>
                        @foreach ($repairOrders as $repairOrder)
                            @include('operations.repair-orders.partials.index-list-row', ['repairOrder' => $repairOrder, 'indexMoney' => $indexMoney ?? []])
                        @endforeach
                    </x-tabler.hybrid-records>
                </div>

                <div data-collection-view="cards">
                    <div class="ark-card-grid">
                        @foreach ($repairOrders as $repairOrder)
                            @include('operations.repair-orders.partials.index-card', ['repairOrder' => $repairOrder])
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($repairOrders->hasPages())
                <div class="mt-3">
                    {{ $repairOrders->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </x-tabler.page>
</x-operations.app>
