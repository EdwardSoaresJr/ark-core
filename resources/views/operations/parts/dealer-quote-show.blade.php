<x-operations.app :title="'Dealer Quote · '.($dealerQuote->quote_number ?: $dealerQuote->id)" ui="tabler">
    <x-tabler.page
        layout="focused"
        :title="$dealerQuote->sourceHeadline()"
        pretitle="Dealer Quote"
        actions-label="Dealer quote commands"
        stack-actions
    >
        <x-slot:actions>
            <x-tabler.page-action :href="route('operations.repair-orders.show', $repairOrder).'#estimate-lines'" icon="arrow-left" label="Back to estimate" />
            @if ($dealerQuote->hasOriginalDocument())
                <x-tabler.page-action
                    :href="route('operations.repair-orders.dealer-quotes.download', [$repairOrder, $dealerQuote])"
                    icon="file-text"
                    label="View Original Quote"
                    primary
                />
            @endif
        </x-slot:actions>

        <div class="card mb-3" data-dealer-quote-details>
            <div class="card-body">
                <p class="text-secondary">
                    This estimate line originated from a captured dealer quote - not from catalog search.
                </p>
                <div class="datagrid">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Supplier</div>
                        <div class="datagrid-content">{{ $dealerQuote->supplier_name ?: '-' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Quote number</div>
                        <div class="datagrid-content">{{ $dealerQuote->quote_number ?: '-' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Vehicle</div>
                        <div class="datagrid-content">{{ $dealerQuote->vehicle_description ?: '-' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">VIN</div>
                        <div class="datagrid-content text-break">{{ $dealerQuote->vin ?: '-' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Captured by</div>
                        <div class="datagrid-content">{{ $dealerQuote->capturedBy?->name ?: '-' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Captured at</div>
                        <div class="datagrid-content">{{ $dealerQuote->captured_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?: '-' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" data-dealer-quote-lines>
            <div class="card-header">
                <h3 class="card-title">Quote lines</h3>
            </div>
            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead>
                        <tr>
                            <th class="text-end">Qty</th>
                            <th>Part #</th>
                            <th>Description</th>
                            <th class="text-end">Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dealerQuote->lines as $line)
                            <tr>
                                <td class="text-end ark-money">{{ $line->quantity }}</td>
                                <td class="font-monospace small text-nowrap">{{ $line->part_number ?: '-' }}</td>
                                <td>{{ $line->description }}</td>
                                <td class="text-end ark-money text-nowrap">${{ $line->unitCostDecimal() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </x-tabler.page>
</x-operations.app>
