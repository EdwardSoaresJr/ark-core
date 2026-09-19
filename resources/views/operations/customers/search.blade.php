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
                    <div class="ops-job-card-wrap">
                        <article class="ops-job-card ops-ro-index-card ops-ro-card--{{ $cardTone }}">
                            <div class="ops-job-card__scan">
                                <div class="ops-job-card__head">
                                    <a href="{{ $customerUrl }}" class="ops-job-card__ro truncate" title="{{ $customer->name }}">{{ $customer->name }}</a>
                                    <span class="ops-status-chip ops-status-chip--{{ $cardTone }}">{{ $chipLabel }}</span>
                                </div>
                                @if ($vehicleUrl !== null)
                                    <a href="{{ $vehicleUrl }}" class="ops-job-card__vehicle truncate">{{ $primaryVehicle?->display_name ?? 'Unknown vehicle' }}</a>
                                @else
                                    <div class="ops-job-card__vehicle truncate">{{ $primaryVehicle?->display_name ?? 'No vehicle on file' }}</div>
                                @endif
                                <p class="ops-job-card__customer">
                                    <span class="ops-job-card__customer-link ops-job-card__customer-link--static">
                                        {{ $customer->display_phone ?: 'No phone' }}
                                    </span>
                                </p>
                            </div>

                            <div class="ops-job-card__status-row">
                                <span class="ops-job-card__clock tabular-nums">{{ $clockLabel }}</span>
                            </div>

                            @include('operations.partials.index-card-actions', ['indexActions' => $indexActions])
                        </article>
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
