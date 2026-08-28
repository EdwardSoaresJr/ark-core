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
                        $activeRepairOrder = $vehicle->repairOrders->first();
                        $cardTone = $activeRepairOrder ? $activeRepairOrder->status->indexTone() : 'move';
                        $customer = $vehicle->customer;
                        $hubUrl = route('operations.customers.show', [
                            'customer' => $customer,
                            'vehicle' => $vehicle->id,
                        ]);
                        $plateLabel = trim(collect([$vehicle->plate, $vehicle->plate_state])->filter()->implode(' '));
                        $vinTail = filled($vehicle->vin) ? substr($vehicle->vin, -8) : null;
                    @endphp
                    <div class="ops-ro-card ops-ro-card--{{ $cardTone }}">
                        <a href="{{ $hubUrl }}" class="ops-ro-card-body-link">
                            <div class="ops-ro-card-top">
                                <div class="ops-ro-card-primary min-w-0">
                                    <p class="ops-ro-vehicle" title="{{ $vehicle->display_name }}">{{ $vehicle->operational_identity }}</p>
                                    <p class="ops-ro-subline truncate">
                                        <span class="ops-ro-customer">{{ $customer?->name ?? 'Unknown customer' }}</span>
                                    </p>
                                </div>
                                @include('operations.repair-orders.partials.repair-order-vehicle-identity-pressure-chip', ['vehicle' => $vehicle])
                            </div>

                            <p class="ops-ro-subline truncate">
                                @if ($plateLabel !== '')
                                    <span>{{ $plateLabel }}</span>
                                @endif
                                @if ($plateLabel !== '' && $vinTail)
                                    <span class="ops-ro-sep">·</span>
                                @endif
                                @if ($vinTail)
                                    <span class="ops-vin-display">VIN {{ $vinTail }}</span>
                                @elseif ($plateLabel === '')
                                    <span class="text-slate-400">No plate or VIN on file</span>
                                @endif
                            </p>
                        </a>
                        @include('operations.vehicles.partials.vehicle-search-card-footnote', [
                            'vehicle' => $vehicle,
                            'activeRepairOrder' => $activeRepairOrder,
                        ])
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
