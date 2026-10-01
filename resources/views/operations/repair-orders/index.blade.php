<x-operations.app title="Repair Orders">
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
    @endphp

    <section class="ops-index space-y-2">
        <div class="ops-board-shell">
            <div class="ops-page-toolbar">
                <p class="ops-page-toolbar-note">
                    @if (($dispositionFilter ?? null) !== null || ($openQueueFilter ?? false))
                        {{ $queueLabel }} - drill-down from Shop Dashboard. Open an RO to act.
                    @elseif ($queueLabel !== null)
                        {{ $queueLabel }} - full inventory from the workboard overflow link.
                    @else
                        Search active and historical ROs without changing the live workboard queue.
                    @endif
                </p>
                <div class="ops-page-toolbar-actions">
                    @if ($workboardReturnUrl ?? null)
                        <a href="{{ $workboardReturnUrl }}" class="ops-page-link">← Workboard triage</a>
                    @endif
                    <a href="{{ route('operations.workboard') }}" class="ops-page-link">Workboard</a>
                    <a href="{{ route('operations.customers.search') }}" class="ops-page-link">Customers</a>
                    <a href="{{ route('operations.vehicles.search') }}" class="ops-page-link">Vehicles</a>
                </div>
            </div>

            <form method="GET" action="{{ route('operations.repair-orders.index') }}" class="ops-board-filters">
                <div class="ops-index-filters ops-index-filters--ro">
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
                    <div>
                        <label for="ro-search" class="ops-index-field-label">Search</label>
                        <input
                            id="ro-search"
                            name="q"
                            value="{{ $query }}"
                            type="search"
                            autofocus
                            autocomplete="off"
                            placeholder="RO #, customer, phone, vehicle, VIN, plate"
                            class="ops-index-field"
                        >
                    </div>

                    <div>
                        <label for="ro-status" class="ops-index-field-label">Status</label>
                        <select id="ro-status" name="status" class="ops-index-field">
                            <option value="">Any status</option>
                            @foreach ($statusFilterOptions as $statusOption)
                                <option value="{{ $statusOption['value'] }}" @selected($selectedStatus === $statusOption['value'])>{{ $statusOption['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <x-operations.date-field id="ro-created-from" name="created_from" label="From" :value="$createdFrom" />

                    <x-operations.date-field id="ro-created-to" name="created_to" label="To" :value="$createdTo" />

                    <button type="submit" class="ops-index-btn ops-index-btn--primary lg:self-end">Search</button>

                    @if ($hasFilters)
                        <a href="{{ route('operations.repair-orders.index') }}" class="ops-index-btn ops-index-btn--ghost lg:self-end">Clear</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="ops-board-shell">
            <div class="ops-index-results-head">
                <span>{{ $queueLabel ?? 'Results' }}</span>
                <span class="tabular-nums">{{ $repairOrders->total() }} total</span>
            </div>

            <div class="ops-ro-retrieval-grid">
                @forelse ($repairOrders as $repairOrder)
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
                    <div class="ops-job-card-wrap">
                        <article class="ops-job-card ops-ro-index-card ops-ro-card--{{ $cardTone }}">
                            <div class="ops-job-card__scan">
                                <div class="ops-job-card__head">
                                    @if ($roShowUrl !== null)
                                        <a href="{{ $roShowUrl }}" class="ops-job-card__ro">RO #{{ $repairOrder->repair_order_id }}</a>
                                    @else
                                        <span class="ops-job-card__ro">RO #{{ $repairOrder->repair_order_id }}</span>
                                    @endif
                                    <span class="ops-status-chip ops-status-chip--{{ $cardTone }}">
                                        {{ $repairOrder->statusDisplayLabel() }}
                                    </span>
                                </div>
                                @if ($vehicleUrl !== null)
                                    <a href="{{ $vehicleUrl }}" class="ops-job-card__vehicle truncate">{{ $vehicle?->display_name ?? 'Unknown vehicle' }}</a>
                                @else
                                    <div class="ops-job-card__vehicle truncate">{{ $vehicle?->display_name ?? 'Unknown vehicle' }}</div>
                                @endif
                                <p class="ops-job-card__customer">
                                    @if ($customerUrl !== null)
                                        <a href="{{ $customerUrl }}" class="ops-job-card__customer-link">
                                            {{ $customer?->name ?? 'Unknown customer' }}
                                        </a>
                                    @else
                                        <span class="ops-job-card__customer-link ops-job-card__customer-link--static">
                                            {{ $customer?->name ?? 'Unknown customer' }}
                                        </span>
                                    @endif
                                </p>
                                @if (filled($repairOrder->concern_summary))
                                    <p class="ops-ro-concern truncate">{{ $repairOrder->concern_summary }}</p>
                                @endif
                            </div>

                            <div class="ops-job-card__status-row">
                                <span class="ops-job-card__clock tabular-nums">{{ $clockLabel }}</span>
                            </div>

                            @include('operations.partials.index-card-actions', ['indexActions' => $indexActions])
                        </article>
                    </div>
                @empty
                    <div class="ops-index-empty ops-ro-retrieval-empty">
                        @if ($hasFilters)
                            No repair orders match these filters.
                        @else
                            No repair orders yet.
                        @endif
                    </div>
                @endforelse
            </div>
        </div>

        @if ($repairOrders->hasPages())
            <div class="ops-board-shell px-2 py-2">
                {{ $repairOrders->links() }}
            </div>
        @endif
    </section>
</x-operations.app>
