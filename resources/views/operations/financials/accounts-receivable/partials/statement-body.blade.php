@php
    $blocks = ($combined ?? false) ? ($statements ?? []) : [($statement ?? [])];
@endphp

@foreach ($blocks as $statement)
    <section class="ar-statement-page mb-4">
        <div class="d-flex justify-content-between gap-3 mb-3">
            <div>
                <div class="h2 mb-1">{{ $statement['shop']['name'] }}</div>
                <div class="text-secondary">{{ $statement['shop']['address'] }}</div>
                <div class="text-secondary">{{ $statement['shop']['phone'] }} {{ $statement['shop']['email'] }}</div>
            </div>
            <div class="text-end">
                <div class="h3 mb-1">Account Statement</div>
                <div class="text-secondary">As of {{ $statement['as_of']->timezone(config('app.timezone'))->format('M j, Y') }}</div>
            </div>
        </div>

        <div class="mb-3">
            <strong>{{ $statement['customer']['name'] }}</strong>
            @foreach ($statement['customer']['address_lines'] as $line)
                <div>{{ $line }}</div>
            @endforeach
            @if ($statement['customer']['email'])
                <div class="text-secondary">{{ $statement['customer']['email'] }}</div>
            @endif
            @if ($statement['customer']['phone'])
                <div class="text-secondary">{{ $statement['customer']['phone'] }}</div>
            @endif
        </div>

        <div class="row g-3 mb-3">
            <div class="col-auto">
                <div class="subheader">Balance due</div>
                <div class="h3 mb-0">{{ $money($statement['total_outstanding_cents']) }}</div>
            </div>
            <div class="col-auto">
                <div class="subheader">Past due</div>
                <div class="h3 mb-0 {{ $statement['past_due_cents'] > 0 ? 'text-danger' : '' }}">{{ $money($statement['past_due_cents']) }}</div>
            </div>
            <div class="col-auto">
                <div class="subheader">Current</div>
                <div class="h3 mb-0">{{ $money($statement['current_cents']) }}</div>
            </div>
            @if ($statement['customer']['store_credit_cents'] > 0)
                <div class="col-auto">
                    <div class="subheader">Store credit</div>
                    <div class="h3 mb-0">{{ $money($statement['customer']['store_credit_cents']) }}</div>
                </div>
            @endif
        </div>

        <h3 class="mb-2">Outstanding invoices</h3>
        <div class="table-responsive mb-3">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>RO / Vehicle</th>
                        <th>Issued</th>
                        <th>Due</th>
                        <th class="text-end">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($statement['outstanding_invoices'] as $invoice)
                        <tr>
                            <td>INV #{{ $invoice['document_number'] }}</td>
                            <td>
                                RO #{{ $invoice['repair_order_number'] }}
                                <div class="small text-secondary">{{ $invoice['vehicle_label'] ?: '—' }}</div>
                            </td>
                            <td>{{ $invoice['issued_at']?->timezone(config('app.timezone'))->format('M j, Y') ?? '—' }}</td>
                            <td>
                                {{ $invoice['due_at']?->timezone(config('app.timezone'))->format('M j, Y') ?? '—' }}
                                @if (($invoice['days_past_due'] ?? 0) > 0)
                                    <div class="small text-danger">{{ $invoice['days_past_due'] }} days past due</div>
                                @endif
                            </td>
                            <td class="text-end">{{ $money($invoice['balance_due_cents']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-secondary">No outstanding invoices.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (count($statement['payments']) > 0)
            <h3 class="mb-2">Recent payments</h3>
            <div class="table-responsive mb-3">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($statement['payments'] as $payment)
                            <tr>
                                <td>{{ $payment['recorded_at']?->timezone(config('app.timezone'))->format('M j, Y') }}</td>
                                <td>{{ $payment['payment_method'] }}</td>
                                <td>{{ $payment['reference'] ?: '—' }}</td>
                                <td class="text-end">{{ $money($payment['amount_cents']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if (count($statement['paid_invoices']) > 0)
            <h3 class="mb-2">Recently paid invoices</h3>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>RO</th>
                            <th>Issued</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($statement['paid_invoices'] as $invoice)
                            <tr>
                                <td>INV #{{ $invoice['document_number'] }}</td>
                                <td>RO #{{ $invoice['repair_order_number'] }}</td>
                                <td>{{ $invoice['issued_at']?->timezone(config('app.timezone'))->format('M j, Y') ?? '—' }}</td>
                                <td class="text-end">{{ $money($invoice['invoice_total_cents']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
    @if (! $loop->last)
        <hr class="my-4">
    @endif
@endforeach
