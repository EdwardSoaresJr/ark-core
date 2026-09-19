<x-operations.app title="Vehicles">
    <section class="ops-index space-y-2">
        <div class="ops-board-shell">
            <div class="ops-page-toolbar">
                <p class="ops-page-toolbar-note">Browse vehicles or search by plate, VIN, YMM, nickname, or customer — then open the hub.</p>
                <div class="ops-page-toolbar-actions">
                    <a href="{{ route('operations.customers.search') }}" class="ops-page-link">Customers</a>
                    <a href="{{ route('operations.repair-orders.index') }}" class="ops-page-link">Repair Orders</a>
                    @can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value)
                        <a href="{{ route('operations.intake.create') }}" class="ops-page-link ops-page-link--primary">+ Check In</a>
                    @endcan
                </div>
            </div>

            <form method="GET" action="{{ route('operations.vehicles.search') }}" class="ops-board-filters">
                <div class="ops-index-filters ops-index-filters--vehicle">
                    <div>
                        <label for="vehicle-search" class="ops-index-field-label">Search</label>
                        <input
                            id="vehicle-search"
                            name="q"
                            value="{{ $query }}"
                            type="search"
                            autofocus
                            autocomplete="off"
                            placeholder="Plate, VIN, year, make, model, nickname, or customer"
                            class="ops-index-field"
                        >
                    </div>

                    <div>
                        <label for="vehicle-work" class="ops-index-field-label">Work</label>
                        <select id="vehicle-work" name="work" class="ops-index-field">
                            <option value="">Any work</option>
                            <option value="open" @selected(($selectedWork ?? '') === 'open')>Open RO</option>
                            <option value="idle" @selected(($selectedWork ?? '') === 'idle')>No open RO</option>
                        </select>
                    </div>

                    <x-operations.date-field id="vehicle-created-from" name="created_from" label="From" :value="$createdFrom ?? ''" />

                    <x-operations.date-field id="vehicle-created-to" name="created_to" label="To" :value="$createdTo ?? ''" />

                    <button type="submit" class="ops-index-btn ops-index-btn--primary lg:self-end">Search</button>

                    @if ($hasFilters ?? false)
                        <a href="{{ route('operations.vehicles.search') }}" class="ops-index-btn ops-index-btn--ghost lg:self-end">Clear</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="ops-board-shell">
            <div class="ops-index-results-head">
                <span>Vehicles</span>
                <span class="tabular-nums">{{ $vehicles->total() }} total</span>
            </div>

            <div class="ops-ro-retrieval-grid">
                @forelse ($vehicles as $vehicle)
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
                    <div class="ops-job-card-wrap">
                        <article class="ops-job-card ops-ro-index-card ops-ro-card--{{ $cardTone }}">
                            <div class="ops-job-card__scan">
                                <div class="ops-job-card__head">
                                    @if ($hubUrl !== null)
                                        <a href="{{ $hubUrl }}" class="ops-job-card__ro truncate" title="{{ $vehicle->display_name }}">{{ $vehicle->operational_identity }}</a>
                                    @else
                                        <span class="ops-job-card__ro truncate" title="{{ $vehicle->display_name }}">{{ $vehicle->operational_identity }}</span>
                                    @endif
                                    <span class="ops-index-card-chips">
                                        @include('operations.repair-orders.partials.repair-order-vehicle-identity-pressure-chip', ['vehicle' => $vehicle])
                                        <span class="ops-status-chip ops-status-chip--{{ $cardTone }}">{{ $chipLabel }}</span>
                                    </span>
                                </div>
                                @if ($customerUrl !== null)
                                    <p class="ops-job-card__customer">
                                        <a href="{{ $customerUrl }}" class="ops-job-card__customer-link">
                                            {{ $customer?->name ?? 'Unknown customer' }}
                                        </a>
                                    </p>
                                @else
                                    <p class="ops-job-card__customer">
                                        <span class="ops-job-card__customer-link ops-job-card__customer-link--static">
                                            {{ $customer?->name ?? 'Unknown customer' }}
                                        </span>
                                    </p>
                                @endif
                                <p class="ops-ro-concern truncate">
                                    {{ $identityBits !== '' ? $identityBits : 'No plate or VIN on file' }}
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
                            No vehicles yet.
                        @else
                            No vehicles match &ldquo;{{ $query }}&rdquo;.
                        @endif
                    </div>
                @endforelse
            </div>
        </div>

        @if ($vehicles->hasPages())
            <div class="ops-board-shell px-2 py-2">
                {{ $vehicles->links() }}
            </div>
        @endif
    </section>
</x-operations.app>
