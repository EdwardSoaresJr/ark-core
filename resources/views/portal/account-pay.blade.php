<x-portal.app>
    <div class="container py-4">
        <h1 class="h3 mb-2">Pay your account</h1>
        <p class="text-secondary mb-4">Select invoices and pay securely with one card charge. Card details are tokenized by ARK Pay.</p>

        @if (session('status'))
            <div class="alert alert-info">{{ session('status') }}</div>
        @endif
        @error('allocations')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror
        @error('source_token')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror
        @error('capture')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror

        @if (count($invoices) === 0)
            <p class="text-secondary">No outstanding invoices.</p>
            <a href="{{ route('portal.account.statement') }}" class="btn btn-outline-secondary">View statement</a>
        @else
            <form
                method="POST"
                action="{{ route('portal.account.pay.store') }}"
                class="space-y-4"
                @submit="onSubmit($event)"
                x-data="portalAccountPay({
                    publicConfig: @js($webPaymentsPublicConfig ?? null),
                    cardContainerId: 'portal-ar-card',
                    allowStubToken: @js(app()->environment('local', 'testing')),
                    stubToken: @js(app()->environment('local', 'testing') ? 'tok_stub_portal_ar' : ''),
                    rows: @js(collect($invoices)->map(fn ($i) => [
                        'invoice_id' => $i['invoice_id'],
                        'balance' => number_format($i['balance_due_cents'] / 100, 2, '.', ''),
                        'selected' => true,
                    ])->values()),
                })"
            >
                @csrf
                <input type="hidden" name="intent_key" value="{{ $intentKey }}">
                <input type="hidden" name="source_token" x-model="sourceToken">
                @if (app()->environment('local', 'testing'))
                    <input type="hidden" name="stub_scenario" value="immediate_success">
                @endif

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Invoice</th>
                                <th class="text-end">Balance</th>
                                <th class="text-end">Pay</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoices as $index => $invoice)
                                <tr>
                                    <td>
                                        <input type="checkbox" name="allocations[{{ $index }}][selected]" value="1" x-model="rows[{{ $index }}].selected">
                                        <input type="hidden" name="allocations[{{ $index }}][invoice_id]" value="{{ $invoice['invoice_id'] }}">
                                    </td>
                                    <td>
                                        INV #{{ $invoice['document_number'] }}
                                        <div class="small text-secondary">RO #{{ $invoice['repair_order_number'] }}</div>
                                    </td>
                                    <td class="text-end">{{ $money($invoice['balance_due_cents']) }}</td>
                                    <td class="text-end">
                                        <input class="form-control form-control-sm text-end" style="max-width: 7rem; margin-left: auto;" type="number" step="0.01" min="0.01" name="allocations[{{ $index }}][amount]" x-model="rows[{{ $index }}].balance">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mb-3">
                    <label class="form-label">Card</label>
                    <div id="portal-ar-card" class="form-control" style="min-height: 48px"></div>
                    <div class="form-hint">Raw card numbers are never stored in ARK.</div>
                    <p class="small text-danger mb-0" x-show="cardError" x-text="cardError"></p>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary" :disabled="busy">Pay selected invoices</button>
                    <a href="{{ route('portal.account.statement') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        @endif
    </div>
</x-portal.app>
