@php
    /** @var \App\Ark\Ui\Collection\CollectionSortState $collectionSort */
    /** @var \App\Ark\Runtime\Preferences\CollectionLayoutModule $collectionLayoutModule */
    $clearUrl = route('operations.vehicles.search');
    $activeFilterCount = collect([$selectedWork ?? '', $createdFrom ?? '', $createdTo ?? ''])->filter(fn ($value) => filled($value))->count();
    $cookieDomain = \App\Ark\Runtime\Preferences\EcosystemDisplayTheme::cookieDomain();
    $routeName = 'operations.vehicles.search';
    $filterQuery = $collectionFilterQuery ?? [];
@endphp

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
                id="vehicle"
                :action="route('operations.vehicles.search')"
                :query="$query"
                placeholder="Plate, VIN, year, make, model, nickname, or customer"
                label="Search vehicles"
                :clear-url="$clearUrl"
                :has-filters="$hasFilters ?? false"
                :active-filter-count="$activeFilterCount"
            >
                <x-slot:hidden>
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

            <x-tabler.collection-summary title="Vehicles" :paginator="$vehicles" :has-filters="$hasFilters ?? false" :clear-url="$clearUrl">
                <x-tabler.layout-toggle :mode="$collectionLayoutMode" aria-label="Vehicles layout" class="ms-md-2" />
            </x-tabler.collection-summary>

            @if ($vehicles->isEmpty())
                <x-tabler.empty-state icon="car" :clear-url="($hasFilters ?? false) ? $clearUrl : null">
                    @if ($query === '')
                        No vehicles yet.
                    @else
                        No vehicles match &ldquo;{{ $query }}&rdquo;.
                    @endif
                </x-tabler.empty-state>
            @else
                <div data-collection-view="list">
                    <div class="card d-none d-md-block">
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table table-sm ark-collection-list-table mb-0">
                                <thead>
                                    <tr>
                                        <x-tabler.sortable-th
                                            label="Vehicle"
                                            :href="$collectionSort->url('vehicle', $routeName, $filterQuery)"
                                            :active="$collectionSort->isActive('vehicle')"
                                            :aria-sort="$collectionSort->ariaSort('vehicle')"
                                            sort-key="vehicle"
                                        />
                                        <x-tabler.sortable-th
                                            label="Owner"
                                            :href="$collectionSort->url('customer', $routeName, $filterQuery)"
                                            :active="$collectionSort->isActive('customer')"
                                            :aria-sort="$collectionSort->ariaSort('customer')"
                                            sort-key="customer"
                                        />
                                        <x-tabler.sortable-th
                                            label="Plate"
                                            :href="$collectionSort->url('plate', $routeName, $filterQuery)"
                                            :active="$collectionSort->isActive('plate')"
                                            :aria-sort="$collectionSort->ariaSort('plate')"
                                            sort-key="plate"
                                            class="d-none d-lg-table-cell"
                                        />
                                        <x-tabler.sortable-th
                                            label="VIN"
                                            :href="$collectionSort->url('vin', $routeName, $filterQuery)"
                                            :active="$collectionSort->isActive('vin')"
                                            :aria-sort="$collectionSort->ariaSort('vin')"
                                            sort-key="vin"
                                            class="d-none d-xl-table-cell"
                                        />
                                        <th scope="col">Open work</th>
                                        <th scope="col" class="d-none d-xl-table-cell">Mileage</th>
                                        <x-tabler.sortable-th
                                            label="Updated"
                                            :href="$collectionSort->url('updated', $routeName, $filterQuery)"
                                            :active="$collectionSort->isActive('updated')"
                                            :aria-sort="$collectionSort->ariaSort('updated')"
                                            sort-key="updated"
                                            class="d-none d-xl-table-cell"
                                        />
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($vehicles as $vehicle)
                                        @include('operations.vehicles.partials.index-list-row', ['vehicle' => $vehicle])
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="ark-collection-mobile-list d-md-none">
                        @foreach ($vehicles as $vehicle)
                            @include('operations.vehicles.partials.index-mobile-row', ['vehicle' => $vehicle])
                        @endforeach
                    </div>
                </div>

                <div data-collection-view="cards">
                    <div class="ark-card-grid">
                        @foreach ($vehicles as $vehicle)
                            @include('operations.vehicles.partials.index-card', ['vehicle' => $vehicle])
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($vehicles->hasPages())
                <div class="mt-3">
                    {{ $vehicles->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </x-tabler.page>
</x-operations.app>
