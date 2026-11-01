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
                    $smsCapability = filled($result->phone ?? null)
                        ? \App\Ark\Operations\Messaging\PhoneSmsCapability::findByNormalizedPhone((string) $result->phone)
                        : null;
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
                        @if ($result->email)
                            <div class="small text-secondary text-truncate">{{ $result->email }}</div>
                        @endif
                        @if ($result->contact_preference)
                            <div class="small fw-medium text-azure text-truncate">{{ $result->contact_preference->outreachLabel() }}</div>
                        @endif
                        @if ($smsCapability !== null && ! $smsCapability->sms_capable)
                            <div class="small text-warning text-truncate">{{ $smsCapability->blockReason() }}</div>
                        @endif
                        <div @class(['small text-truncate', 'text-secondary' => (bool) $result->display_address, 'text-muted' => ! $result->display_address])>
                            {{ $result->display_address ?: 'No address on file' }}
                        </div>
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
