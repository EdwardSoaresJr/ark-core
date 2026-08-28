<x-operations.app title="Customers">
    <section class="ops-index space-y-2">
        <div class="ops-board-shell">
            <div class="ops-page-toolbar">
                <p class="ops-page-toolbar-note">Browse customers or search by name, phone, email, plate, or VIN — then open the hub.</p>
                <div class="ops-page-toolbar-actions">
                    <a href="{{ route('operations.vehicles.search') }}" class="ops-page-link">Vehicles</a>
                    <a href="{{ route('operations.workboard') }}" class="ops-page-link">Workboard</a>
                    @can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value)
                        <a href="{{ route('operations.intake.create') }}" class="ops-page-link ops-page-link--primary">+ Check In</a>
                    @endcan
                </div>
            </div>

            <form method="GET" action="{{ route('operations.customers.search') }}" class="ops-board-filters">
                <div class="ops-index-filters ops-index-filters--customer">
                    @if ($intakeMode ?? false)
                        <input type="hidden" name="intake" value="1">
                    @endif
                    <div>
                        <label for="customer-search" class="ops-index-field-label">Search</label>
                        <input
                            id="customer-search"
                            name="q"
                            value="{{ $query }}"
                            type="search"
                            autofocus
                            autocomplete="off"
                            placeholder="Name, phone, email, plate, or VIN"
                            class="ops-index-field"
                        >
                    </div>

                    <div>
                        <label for="customer-type" class="ops-index-field-label">Type</label>
                        <select id="customer-type" name="type" class="ops-index-field">
                            <option value="">Any type</option>
                            @foreach ($customerTypes as $customerType)
                                <option value="{{ $customerType['name'] }}" @selected(($selectedType ?? '') === $customerType['name'])>{{ $customerType['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <x-operations.date-field id="customer-created-from" name="created_from" label="From" :value="$createdFrom ?? ''" />

                    <x-operations.date-field id="customer-created-to" name="created_to" label="To" :value="$createdTo ?? ''" />

                    <button type="submit" class="ops-index-btn ops-index-btn--primary lg:self-end">Search</button>

                    @if ($hasFilters ?? false)
                        <a
                            href="{{ route('operations.customers.search', ($intakeMode ?? false) ? ['intake' => 1] : []) }}"
                            class="ops-index-btn ops-index-btn--ghost lg:self-end"
                        >Clear</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="ops-board-shell">
            <div class="ops-index-results-head">
                <span>Customers</span>
                <span class="tabular-nums">{{ $customers->total() }} total</span>
            </div>

            <div class="ops-ro-retrieval-grid">
                @forelse ($customers as $customer)
                    @php
                        $activeRepairOrder = $customer->repairOrders->first();
                        $cardTone = $activeRepairOrder ? $activeRepairOrder->status->indexTone() : 'move';
                    @endphp
                    <div class="ops-ro-card ops-ro-card--{{ $cardTone }}">
                        <a
                            href="{{ ($intakeMode ?? false) ? route('operations.intake.create', ['customer_id' => $customer->id]) : route('operations.customers.show', $customer) }}"
                            class="ops-ro-card-body-link"
                        >
                            <div class="ops-ro-card-top">
                                <div class="ops-ro-card-primary min-w-0">
                                    <p class="ops-ro-vehicle" title="{{ $customer->name }}">{{ $customer->name }}</p>
                                    <p class="ops-ro-subline truncate">{{ $customer->display_phone ?: 'No phone' }}</p>
                                </div>
                                @include('operations.customers.partials.customer-search-card-badges', ['customer' => $customer])
                            </div>

                            @include('operations.customers.partials.customer-contact-lines', ['customer' => $customer])
                        </a>
                        @include('operations.customers.partials.customer-search-card-footnote', ['customer' => $customer])
                    </div>
                @empty
                    <div class="ops-index-empty ops-ro-retrieval-empty">
                        @if ($query === '')
                            No customers yet.
                        @else
                            No customers match &ldquo;{{ $query }}&rdquo;.
                        @endif
                    </div>
                @endforelse
            </div>
        </div>

        @if ($customers->hasPages())
            <div class="ops-board-shell px-2 py-2">
                {{ $customers->links() }}
            </div>
        @endif
    </section>
</x-operations.app>
