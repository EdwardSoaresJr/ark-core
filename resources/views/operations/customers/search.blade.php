@php
    /** @var \App\Ark\Ui\Collection\CollectionSortState $collectionSort */
    /** @var \App\Ark\Runtime\Preferences\CollectionLayoutModule $collectionLayoutModule */
    $clearUrl = route('operations.customers.search', ($intakeMode ?? false) ? ['intake' => 1] : []);
    $activeFilterCount = collect([$selectedType ?? '', $createdFrom ?? '', $createdTo ?? ''])->filter(fn ($value) => filled($value))->count();
    $cookieDomain = \App\Ark\Runtime\Preferences\EcosystemDisplayTheme::cookieDomain();
    $routeName = 'operations.customers.search';
    $filterQuery = $collectionFilterQuery ?? [];
@endphp

<x-operations.app title="Customers" ui="tabler">
    <x-tabler.page
        layout="fluid"
        title="Customers"
        pretitle="Records"
        subtitle="Browse customers or search by name, phone, email, plate, or VIN - then open the hub."
    >
        <x-slot:actions>
            <x-tabler.page-action :href="route('operations.vehicles.search')" icon="car" label="Vehicles" />
            <x-tabler.page-action :href="route('operations.workboard')" icon="layout-grid" label="Shop Queue" />
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
                id="customer"
                :action="route('operations.customers.search')"
                :query="$query"
                placeholder="Name, phone, email, plate, or VIN"
                label="Search customers"
                :clear-url="$clearUrl"
                :has-filters="$hasFilters ?? false"
                :active-filter-count="$activeFilterCount"
            >
                <x-slot:hidden>
                    @if ($intakeMode ?? false)
                        <input type="hidden" name="intake" value="1">
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

            <x-tabler.collection-summary title="Customers" :paginator="$customers" :has-filters="$hasFilters ?? false" :clear-url="$clearUrl">
                <x-tabler.layout-toggle :mode="$collectionLayoutMode" aria-label="Customers layout" class="ms-md-2" />
            </x-tabler.collection-summary>

            @if ($customers->isEmpty())
                <x-tabler.empty-state icon="users" :clear-url="($hasFilters ?? false) ? $clearUrl : null">
                    @if ($query === '')
                        No customers yet.
                    @else
                        No customers match &ldquo;{{ $query }}&rdquo;.
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
                                label="Sort customers"
                                :columns="['name' => 'Customer', 'phone' => 'Phone', 'email' => 'Email', 'type' => 'Type', 'vehicles' => 'Vehicles', 'updated' => 'Updated']"
                            />
                        </x-slot:sort>
                        @foreach ($customers as $customer)
                            @include('operations.customers.partials.index-list-row', ['customer' => $customer, 'intakeMode' => $intakeMode ?? false])
                        @endforeach
                    </x-tabler.hybrid-records>
                </div>

                <div data-collection-view="cards">
                    <div class="ark-card-grid">
                        @foreach ($customers as $customer)
                            @include('operations.customers.partials.index-card', ['customer' => $customer, 'intakeMode' => $intakeMode ?? false])
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($customers->hasPages())
                <div class="mt-3">
                    {{ $customers->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </x-tabler.page>
</x-operations.app>
