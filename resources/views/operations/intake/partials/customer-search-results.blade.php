@if ($searchQuery !== '')
    <div class="ops-intake-find-search-results-panel">
        <div class="ops-index-results-head">
            <span>Results</span>
            <span class="tabular-nums">{{ $searchCustomers->count() }}</span>
        </div>
        <div class="ops-ro-retrieval-grid">
            @forelse ($searchCustomers as $result)
                @php
                    $activeRepairOrder = $result->repairOrders->first();
                    $cardTone = $activeRepairOrder ? $activeRepairOrder->status->indexTone() : 'move';
                @endphp
                <div
                    data-intake-customer-id="{{ $result->id }}"
                    class="ops-ro-card ops-ro-card--{{ $cardTone }} ops-ro-card--selectable"
                    role="button"
                    tabindex="0"
                >
                    <div class="ops-ro-card-select-body">
                        <div class="ops-ro-card-top">
                            <div class="ops-ro-card-primary min-w-0">
                                <p class="ops-ro-vehicle" title="{{ $result->name }}">{{ $result->name }}</p>
                                <p class="ops-ro-subline truncate">{{ $result->display_phone ?: 'No phone' }}</p>
                            </div>
                            @include('operations.customers.partials.customer-search-card-badges', [
                                'result' => $result,
                                'intakeMode' => true,
                            ])
                        </div>
                        @include('operations.customers.partials.customer-contact-lines', ['result' => $result])
                    </div>
                    @include('operations.customers.partials.customer-search-card-footnote', ['result' => $result])
                </div>
            @empty
                <div class="ops-index-empty ops-ro-retrieval-empty col-span-full">
                    No customers found for &ldquo;{{ $searchQuery }}&rdquo;.
                    <a href="{{ route('operations.customers.search', ['q' => $searchQuery, 'intake' => 1]) }}" class="ml-1 font-semibold text-slate-700 underline">Open full customer search</a>
                </div>
            @endforelse
        </div>
    </div>
@endif
