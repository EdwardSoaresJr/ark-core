@if ($searchQuery !== '')
    <div class="mt-3" data-intake-search-results>
        <div class="d-flex justify-content-between subheader mb-2">
            <span>Results</span>
            <span class="ark-money">{{ $searchCustomers->count() }}</span>
        </div>
        <div class="d-flex flex-column gap-2">
            @forelse ($searchCustomers as $result)
                @php
                    $activeRepairOrder = $result->repairOrders->first();
                    $toneColor = \App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor::tablerColorForChipTone(
                        $activeRepairOrder ? $activeRepairOrder->status->indexTone() : 'move',
                    );
                    $customerTag = trim((string) ($result->customer_type ?: 'Retail'));
                    $vehicleCount = $result->vehicles->count();
                @endphp
                <div
                    data-intake-customer-id="{{ $result->id }}"
                    class="card card-sm card-link cursor-pointer"
                    role="button"
                    tabindex="0"
                >
                    <div class="card-status-start bg-{{ $toneColor }}"></div>
                    <div class="card-body p-2">
                        <div class="d-flex align-items-start gap-2">
                            <div class="flex-fill overflow-hidden">
                                <div class="fw-bold text-truncate" title="{{ $result->name }}">{{ $result->name }}</div>
                                <div class="small text-secondary text-truncate">{{ $result->display_phone ?: 'No phone' }}</div>
                            </div>
                            @if ($customerTag !== 'Retail')
                                <span class="badge bg-secondary-lt">{{ $customerTag }}</span>
                            @endif
                        </div>
                        @include('operations.customers.partials.customer-contact-lines', ['result' => $result])
                        <div class="small text-secondary border-top mt-2 pt-1" data-intake-customer-footnote>
                            @if ($activeRepairOrder)
                                <a href="{{ route('operations.repair-orders.show', $activeRepairOrder) }}" aria-label="Open RO #{{ $activeRepairOrder->repair_order_id }}" class="ark-money">RO #{{ $activeRepairOrder->repair_order_id }}</a>
                                · {{ $activeRepairOrder->status->label() }}
                            @else
                                {{ $vehicleCount }} {{ Str::plural('vehicle', $vehicleCount) }} · no active RO
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-secondary small">
                    No customers found for &ldquo;{{ $searchQuery }}&rdquo;.
                    <a href="{{ route('operations.customers.search', ['q' => $searchQuery, 'intake' => 1]) }}">Open full customer search</a>
                </div>
            @endforelse
        </div>
    </div>
@endif
