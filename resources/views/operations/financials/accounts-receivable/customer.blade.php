<x-operations.app :title="$customer->name.' · Receivables'" ui="tabler">
    <x-tabler.page
        layout="fluid"
        :title="$customer->name"
        pretitle="Accounts Receivable"
        subtitle="Outstanding invoices on this account. Payment allocation across invoices comes in the next slice."
        :breadcrumbs="[
            ['label' => 'Accounts Receivable', 'url' => route('operations.financials.accounts-receivable.index')],
            ['label' => $customer->name],
        ]"
    >
        <div class="row row-cards mb-3">
            <div class="col-sm-4">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="subheader">Account balance</div>
                        <div class="h2 mb-0">{{ $money($dashboard['total_outstanding_cents']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="subheader">Past due</div>
                        <div class="h2 mb-0 text-danger">{{ $money($dashboard['past_due_cents']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="subheader">Open invoices</div>
                        <div class="h2 mb-0">{{ count($dashboard['invoices']) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="{{ route('operations.customers.show', $customer) }}" class="btn btn-ghost-secondary">Customer profile</a>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Outstanding invoices</h3>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Invoice / RO</th>
                            <th>Vehicle</th>
                            <th>Issued</th>
                            <th>Due</th>
                            <th class="text-end">Original</th>
                            <th class="text-end">Paid</th>
                            <th class="text-end">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($dashboard['invoices'] as $invoice)
                            <tr>
                                <td>
                                    <a href="{{ route('operations.repair-orders.show', $invoice['repair_order_id']) }}">
                                        INV #{{ $invoice['document_number'] }}
                                    </a>
                                    <div class="small text-secondary">RO #{{ $invoice['repair_order_number'] }} · {{ \App\Ark\Operations\Financial\InvoiceStatus::tryFrom($invoice['status'])?->label() ?? $invoice['status'] }}</div>
                                </td>
                                <td>{{ $invoice['vehicle_label'] ?: '—' }}</td>
                                <td>{{ $invoice['issued_at']?->timezone(config('app.timezone'))->format('M j, Y') ?? '—' }}</td>
                                <td>
                                    @if ($invoice['due_at'])
                                        {{ $invoice['due_at']->timezone(config('app.timezone'))->format('M j, Y') }}
                                        @if (($invoice['payment_terms_days'] ?? null) !== null)
                                            <div class="small text-secondary">Net {{ $invoice['payment_terms_days'] }}</div>
                                        @endif
                                        @if ($invoice['days_past_due'] > 0)
                                            <div class="small text-danger">{{ $invoice['days_past_due'] }} days past due</div>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end">{{ $money($invoice['invoice_total_cents']) }}</td>
                                <td class="text-end">{{ $money($invoice['amount_paid_cents']) }}</td>
                                <td class="text-end fw-bold">{{ $money($invoice['balance_due_cents']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-secondary">No outstanding invoices on this account.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-tabler.page>
</x-operations.app>
