<x-operations.app :title="$customer->name.' · Receivables'" ui="tabler">
    <x-tabler.page
        layout="fluid"
        :title="$customer->name"
        pretitle="Accounts Receivable"
        subtitle="Outstanding invoices, account balance, and payment receipts for this customer."
        :breadcrumbs="[
            ['label' => 'Accounts Receivable', 'url' => route('operations.financials.accounts-receivable.index')],
            ['label' => $customer->name],
        ]"
    >
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if (($pendingCaptures ?? collect())->isNotEmpty())
            <div class="alert alert-warning">
                <div class="fw-bold mb-2">Card charges awaiting invoice allocation</div>
                <p class="mb-2">These VizyPay charges succeeded. Do not charge the customer again — retry allocation only.</p>
                <ul class="mb-0 list-unstyled">
                    @foreach ($pendingCaptures as $capture)
                        <li class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <span>
                                Capture <code>{{ $capture->public_id }}</code>
                                · {{ $money((int) $capture->amount_cents) }}
                                · processor {{ $capture->provider_payment_id ?: '—' }}
                            </span>
                            @if ($canReceivePayment)
                                <form method="POST" action="{{ route('operations.financials.accounts-receivable.captures.reconcile', [$customer, $capture]) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-warning">Retry allocation</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

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
                        @if ((int) ($customer->store_credit_balance_cents ?? 0) > 0)
                            <div class="small text-secondary mt-1">Store credit {{ $money((int) $customer->store_credit_balance_cents) }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-3">
            @if ($canReceivePayment && count($dashboard['invoices']) > 0)
                <a href="{{ route('operations.financials.accounts-receivable.receive-payment.create', $customer) }}" class="btn btn-primary">Receive payment</a>
            @endif
            <a href="{{ route('operations.financials.accounts-receivable.statement.show', $customer) }}" class="btn btn-ghost-secondary">Statement</a>
            <a href="{{ route('operations.financials.accounts-receivable.statement.label', $customer) }}" class="btn btn-ghost-secondary" target="_blank">Address label</a>
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

        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title">Payment receipts</h3>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th>Allocations</th>
                            <th class="text-end">Amount</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($receipts as $receipt)
                            <tr class="{{ $receipt->isVoided() ? 'text-secondary' : '' }}">
                                <td>{{ $receipt->recorded_at?->timezone(config('app.timezone'))->format('M j, Y') }}</td>
                                <td>{{ \App\Ark\Operations\Financial\ManualPaymentMethods::label($receipt->payment_method) ?? $receipt->payment_method }}</td>
                                <td>{{ $receipt->reference ?: '—' }}</td>
                                <td>
                                    @foreach ($receipt->ledgerEntries->whereIn('entry_type', [
                                        \App\Ark\Operations\Financial\LedgerEntryType::Payment,
                                        \App\Ark\Operations\Financial\LedgerEntryType::StoreCreditApplication,
                                    ]) as $entry)
                                        <div class="small">
                                            INV #{{ $entry->financialDocument?->document_number ?? '—' }}
                                            · {{ $money((int) $entry->amount_cents) }}
                                            @if ($entry->entry_type === \App\Ark\Operations\Financial\LedgerEntryType::StoreCreditApplication)
                                                <span class="text-secondary">(account credit)</span>
                                            @endif
                                            @if ($entry->isVoided())
                                                <span class="text-secondary">(voided)</span>
                                            @endif
                                        </div>
                                    @endforeach
                                    @if ($receipt->unapplied_cents > 0)
                                        <div class="small text-secondary">Unapplied / account credit {{ $money((int) $receipt->unapplied_cents) }}</div>
                                    @endif
                                    @if ($receipt->change_cents > 0)
                                        <div class="small text-secondary">Change returned {{ $money((int) $receipt->change_cents) }}</div>
                                    @endif
                                    @if (($receipt->refund_pending_cents ?? 0) > 0)
                                        <div class="small text-warning">Pending refund/return {{ $money((int) $receipt->refund_pending_cents) }}</div>
                                        @if ($receipt->requiresProcessorRefundConfirmation())
                                            <div class="small text-secondary">Card/processor refund — awaits verified processor settlement.</div>
                                        @endif
                                    @endif
                                    @if ($receipt->refund_confirmed_at)
                                        <div class="small text-secondary">
                                            Return confirmed {{ $receipt->refund_confirmed_at->timezone(config('app.timezone'))->format('M j, Y g:ia') }}
                                            @if ($receipt->refund_evidence_reference)
                                                · {{ $receipt->refund_evidence_reference }}
                                            @endif
                                        </div>
                                    @endif
                                    @if ($receipt->isVoided())
                                        <div class="small text-danger">Receipt voided</div>
                                    @endif
                                </td>
                                <td class="text-end fw-bold">{{ $money((int) $receipt->amount_cents) }}</td>
                                <td class="text-end">
                                    <div class="d-flex flex-column align-items-end gap-1">
                                        @if (
                                            $canVoidPayment
                                            && ! $receipt->isVoided()
                                            && ($receipt->refund_pending_cents ?? 0) > 0
                                            && $receipt->allowsManualRefundConfirmation()
                                        )
                                            <form
                                                method="POST"
                                                action="{{ route('operations.financials.accounts-receivable.payments.confirm-refund', [$customer, $receipt]) }}"
                                                class="d-flex flex-wrap gap-1 justify-content-end"
                                            >
                                                @csrf
                                                <input type="hidden" name="evidence_kind" value="manual_return">
                                                <input
                                                    type="text"
                                                    name="evidence"
                                                    class="form-control form-control-sm"
                                                    style="width: 11rem;"
                                                    maxlength="191"
                                                    required
                                                    placeholder="Check # / cash till proof"
                                                >
                                                <button type="submit" class="btn btn-sm btn-outline-warning">Confirm manual return</button>
                                            </form>
                                        @endif
                                        @if ($canVoidPayment && ! $receipt->isVoided())
                                            <form
                                                method="POST"
                                                action="{{ route('operations.financials.accounts-receivable.payments.void', [$customer, $receipt]) }}"
                                                onsubmit="return confirm('Void this payment and reverse all allocations?');"
                                            >
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-ghost-danger">Void</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-secondary">No AR payment receipts yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-tabler.page>
</x-operations.app>
