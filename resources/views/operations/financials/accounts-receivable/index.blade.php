<x-operations.app title="Accounts Receivable" ui="tabler">
    <x-tabler.page
        layout="fluid"
        title="Accounts Receivable"
        pretitle="Financials"
        subtitle="Outstanding finalized invoices after on-account close. Balances come from the ledger and invoice snapshot."
    >
        <form method="GET" action="{{ route('operations.financials.accounts-receivable.index') }}" class="row g-2 align-items-end mb-3">
            <div class="col-md-6 col-lg-4">
                <label class="form-label" for="ar-search">Search</label>
                <input
                    id="ar-search"
                    type="search"
                    name="q"
                    value="{{ $search }}"
                    class="form-control"
                    placeholder="Customer, phone, RO, or invoice #"
                >
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Search</button>
            </div>
            @if ($search !== '')
                <div class="col-auto">
                    <a href="{{ route('operations.financials.accounts-receivable.index') }}" class="btn btn-ghost-secondary">Clear</a>
                </div>
            @endif
        </form>

        <div class="row row-cards mb-3">
            <div class="col-sm-6 col-lg-3">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="subheader">Total outstanding</div>
                        <div class="h2 mb-0">{{ $money($dashboard['total_outstanding_cents']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="subheader">Past due</div>
                        <div class="h2 mb-0 text-danger">{{ $money($dashboard['past_due_cents']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="subheader">Current</div>
                        <div class="h2 mb-0">{{ $money($dashboard['current_cents']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="subheader">Accounts</div>
                        <div class="h2 mb-0">{{ count($dashboard['accounts']) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">Aging</h3>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach ([
                        'current' => 'Current',
                        'days_1_30' => '1–30 days',
                        'days_31_60' => '31–60 days',
                        'days_61_90' => '61–90 days',
                        'days_90_plus' => '90+ days',
                    ] as $key => $label)
                        <div class="col">
                            <div class="subheader">{{ $label }}</div>
                            <div class="fw-bold">{{ $money($dashboard['aging'][$key]) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Customer accounts</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th class="text-end">Invoices</th>
                                    <th class="text-end">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($dashboard['accounts'] as $account)
                                    <tr>
                                        <td>
                                            <a href="{{ route('operations.financials.accounts-receivable.customer', $account['customer_id']) }}">
                                                {{ $account['customer_name'] }}
                                            </a>
                                            @if ($account['past_due_cents'] > 0)
                                                <div class="small text-danger">{{ $money($account['past_due_cents']) }} past due</div>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ $account['invoice_count'] }}</td>
                                        <td class="text-end fw-bold">{{ $money($account['balance_cents']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-secondary">No outstanding receivables.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Outstanding invoices</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead>
                                <tr>
                                    <th>Invoice</th>
                                    <th>Customer / vehicle</th>
                                    <th>Due</th>
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
                                            <div class="small text-secondary">RO #{{ $invoice['repair_order_number'] }}</div>
                                        </td>
                                        <td>
                                            <div>{{ $invoice['customer_name'] }}</div>
                                            <div class="small text-secondary">{{ $invoice['vehicle_label'] ?: '—' }}</div>
                                        </td>
                                        <td>
                                            @if ($invoice['due_at'])
                                                {{ $invoice['due_at']->timezone(config('app.timezone'))->format('M j, Y') }}
                                                @if ($invoice['days_past_due'] > 0)
                                                    <div class="small text-danger">{{ $invoice['days_past_due'] }} days past due</div>
                                                @endif
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-end fw-bold">{{ $money($invoice['balance_due_cents']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-secondary">No outstanding invoices match.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </x-tabler.page>
</x-operations.app>
