<x-operations.app :title="'Receive Payment · '.$customer->name" ui="tabler">
    <x-tabler.page
        layout="fluid"
        title="Receive Payment"
        pretitle="{{ $customer->name }}"
        subtitle="Apply one payment across one or more outstanding invoices. When tender exceeds the selected invoices, choose whether to keep account credit, return change, or refund."
        :breadcrumbs="[
            ['label' => 'Accounts Receivable', 'url' => route('operations.financials.accounts-receivable.index')],
            ['label' => $customer->name, 'url' => route('operations.financials.accounts-receivable.customer', $customer)],
            ['label' => 'Receive Payment'],
        ]"
    >
        @if (session('status'))
            <div class="alert alert-info">{{ session('status') }}</div>
        @endif
        @error('capture')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror
        @error('disposition')
            <div class="alert alert-warning">{{ $message }}</div>
        @enderror

        @if (count($invoices) === 0)
            <div class="alert alert-info">No outstanding invoices on this account.</div>
            <a href="{{ route('operations.financials.accounts-receivable.customer', $customer) }}" class="btn btn-ghost-secondary">Back to account</a>
        @else
            @php
                $initialDisposition = old(
                    'disposition',
                    $dispositionDefault === \App\Ark\Operations\Financial\OverpaymentDisposition::Ask
                        ? ''
                        : $dispositionDefault->value,
                );
            @endphp
            <form
                method="POST"
                :action="isCardMethod()
                    ? @js(route('operations.financials.accounts-receivable.receive-payment.card', $customer))
                    : @js(route('operations.financials.accounts-receivable.receive-payment.store', $customer))"
                class="card"
                @submit="onSubmit($event)"
                x-data="arReceivePayment({
                    invoices: @js(collect($invoices)->map(fn ($i) => [
                        'invoice_id' => $i['invoice_id'],
                        'balance_cents' => $i['balance_due_cents'],
                    ])->values()),
                    keyedEnabled: @js((bool) ($cardCapture['keyedEnabled'] ?? false)),
                    terminalEnabled: @js((bool) ($cardCapture['terminalEnabled'] ?? false)),
                    publicConfig: @js($cardCapture['webPaymentsPublicConfig'] ?? null),
                    cardContainerId: 'ar-receive-payment-card',
                    allowStubToken: @js(app()->environment('local', 'testing')),
                    stubToken: @js(app()->environment('local', 'testing') ? 'tok_stub_ar' : ''),
                    storeCreditCents: @js((int) ($storeCreditCents ?? 0)),
                    dispositionDefault: @js($dispositionDefault->value),
                    initialDisposition: @js($initialDisposition),
                    initialMethod: @js(old('payment_method', ($storeCreditCents ?? 0) > 0 ? 'store_credit' : 'check')),
                })"
            >
                @csrf
                <input type="hidden" name="{{ \App\Ark\Operations\RepairOrders\WorksheetMutationIdempotency::FIELD }}" value="{{ $intentKey }}">
                <input type="hidden" name="capture_method" x-bind:value="cardCaptureMethod()" x-bind:disabled="!isCardMethod()">
                <input type="hidden" name="source_token" x-bind:value="sourceToken" x-bind:disabled="method !== 'ark_card_keyed'">
                @if (app()->environment('local', 'testing'))
                    <input type="hidden" name="stub_scenario" value="immediate_success" x-bind:disabled="!isCardMethod()">
                @endif

                <div class="card-body border-bottom">
                    <div class="row g-3">
                        <div class="col-12 col-md-3">
                            <label class="form-label" for="ar-payment-amount">Payment amount</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input
                                    id="ar-payment-amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    name="amount"
                                    class="form-control @error('amount') is-invalid @enderror"
                                    x-model="paymentAmount"
                                    @input="autoAllocate()"
                                    value="{{ old('amount') }}"
                                    :required="!isCardMethod()"
                                    :disabled="isCardMethod()"
                                >
                            </div>
                            <div class="form-hint" x-show="isCardMethod()">Card charge equals the allocated total.</div>
                            @error('amount')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label" for="ar-payment-method">Method</label>
                            <select id="ar-payment-method" name="payment_method" class="form-select" x-model="method" :required="!isCardMethod()">
                                @foreach ($methods as $method)
                                    <option value="{{ $method['key'] }}" @selected(old('payment_method', 'check') === $method['key'])>
                                        {{ $method['label'] }}
                                    </option>
                                @endforeach
                                <template x-if="keyedEnabled">
                                    <option value="ark_card_keyed">Card (keyed / phone)</option>
                                </template>
                                <template x-if="terminalEnabled">
                                    <option value="ark_card_terminal">Card (terminal)</option>
                                </template>
                            </select>
                            @if (! ($cardCapture['keyedEnabled'] ?? false) && ! ($cardCapture['terminalEnabled'] ?? false))
                                <div class="form-hint">ARK Pay keyed/terminal is not enabled for this shop.</div>
                            @endif
                            @if (! ($cardCapture['savedCardsEnabled'] ?? false))
                                <div class="form-hint text-secondary">{{ $cardCapture['savedCardsBlocker'] ?? '' }}</div>
                            @endif
                        </div>
                        <div class="col-12 col-md-3" x-show="method === 'ark_card_terminal'">
                            <label class="form-label" for="ar-device-ref">Reader</label>
                            <select id="ar-device-ref" name="device_ref" class="form-select" x-bind:disabled="method !== 'ark_card_terminal'">
                                <option value="">Choose a reader</option>
                                @foreach (($cardCapture['readers'] ?? []) as $reader)
                                    <option value="{{ $reader['device_ref'] }}">{{ $reader['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12" x-show="method === 'ark_card_keyed'">
                            <label class="form-label">Card details</label>
                            <div id="ar-receive-payment-card" class="form-control" style="min-height: 48px"></div>
                            <div class="form-hint">Card numbers are tokenized by ARK Pay. Raw PAN/CVV are never stored in ARK.</div>
                            <p class="small text-danger mb-0" x-show="cardError" x-text="cardError"></p>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label" for="ar-payment-reference">Reference</label>
                            <input id="ar-payment-reference" type="text" name="reference" class="form-control" value="{{ old('reference') }}" maxlength="128" placeholder="Check # / confirmation">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label" for="ar-payment-paid-at">Paid on</label>
                            <input id="ar-payment-paid-at" type="date" name="paid_at" class="form-control" value="{{ old('paid_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}">
                        </div>
                    </div>
                    @error('allocations')
                        <div class="text-danger small mt-2">{{ $message }}</div>
                    @enderror
                    @error('source_token')
                        <div class="text-danger small mt-2">{{ $message }}</div>
                    @enderror
                </div>

                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th style="width: 2.5rem;"></th>
                                <th>Invoice / RO</th>
                                <th>Due</th>
                                <th class="text-end">Balance</th>
                                <th class="text-end" style="width: 10rem;">Allocate</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoices as $index => $invoice)
                                <tr>
                                    <td>
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            name="allocations[{{ $index }}][selected]"
                                            value="1"
                                            x-model="rows[{{ $index }}].selected"
                                            @change="syncSelected({{ $index }})"
                                        >
                                        <input type="hidden" name="allocations[{{ $index }}][invoice_id]" value="{{ $invoice['invoice_id'] }}">
                                    </td>
                                    <td>
                                        INV #{{ $invoice['document_number'] }}
                                        <div class="small text-secondary">RO #{{ $invoice['repair_order_number'] }} · {{ $invoice['vehicle_label'] ?: '—' }}</div>
                                    </td>
                                    <td>
                                        {{ $invoice['due_at']?->timezone(config('app.timezone'))->format('M j, Y') ?? '—' }}
                                        @if (($invoice['days_past_due'] ?? 0) > 0)
                                            <div class="small text-danger">{{ $invoice['days_past_due'] }} days past due</div>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ $money($invoice['balance_due_cents']) }}</td>
                                    <td class="text-end">
                                        <div class="input-group input-group-sm justify-content-end">
                                            <span class="input-group-text">$</span>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                class="form-control text-end"
                                                name="allocations[{{ $index }}][amount]"
                                                x-model="rows[{{ $index }}].amount"
                                                @input="rows[{{ $index }}].selected = parseFloat(rows[{{ $index }}].amount || 0) > 0"
                                                :max="(rows[{{ $index }}].balance_cents / 100).toFixed(2)"
                                            >
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="card-body border-top" x-show="showsExcess()" x-cloak>
                    <div class="row g-3 align-items-start">
                        <div class="col-12 col-lg-4">
                            <div class="subheader">Customer tendered</div>
                            <div class="h3 mb-0" x-text="'$' + paymentDollars()"></div>
                        </div>
                        <div class="col-12 col-lg-4">
                            <div class="subheader">Invoices selected</div>
                            <div class="h3 mb-0" x-text="'$' + allocatedDollars()"></div>
                        </div>
                        <div class="col-12 col-lg-4">
                            <div class="subheader text-warning">Excess payment</div>
                            <div class="h3 mb-0 text-warning" x-text="'$' + remainingDollars()"></div>
                        </div>
                        <div class="col-12">
                            <fieldset>
                                <legend class="form-label mb-2">How should we handle the excess?</legend>
                                <div class="form-selectgroup form-selectgroup-boxes d-flex flex-column flex-md-row">
                                    <label class="form-selectgroup-item flex-fill" x-show="allowsKeepCredit()">
                                        <input type="radio" name="disposition" value="keep_credit" class="form-selectgroup-input" x-model="disposition">
                                        <span class="form-selectgroup-label d-flex align-items-start gap-2">
                                            <span class="form-selectgroup-check mt-1"></span>
                                            <span>
                                                <span class="fw-bold d-block">Keep as account credit</span>
                                                <span class="text-secondary small">Available toward future services on this account.</span>
                                            </span>
                                        </span>
                                    </label>
                                    <label class="form-selectgroup-item flex-fill" x-show="allowsReturnChange()">
                                        <input type="radio" name="disposition" value="return_change" class="form-selectgroup-input" x-model="disposition">
                                        <span class="form-selectgroup-label d-flex align-items-start gap-2">
                                            <span class="form-selectgroup-check mt-1"></span>
                                            <span>
                                                <span class="fw-bold d-block">Return change (cash)</span>
                                                <span class="text-secondary small">Record the excess as change returned to the customer.</span>
                                            </span>
                                        </span>
                                    </label>
                                    <label class="form-selectgroup-item flex-fill" x-show="allowsRefund()">
                                        <input type="radio" name="disposition" value="refund" class="form-selectgroup-input" x-model="disposition">
                                        <span class="form-selectgroup-label d-flex align-items-start gap-2">
                                            <span class="form-selectgroup-check mt-1"></span>
                                            <span>
                                                <span class="fw-bold d-block">Pending refund / return</span>
                                                <span class="text-secondary small">Hold as a refund liability until the return is confirmed. Not treated as returned yet.</span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </fieldset>
                            <p class="form-hint mb-0 mt-2" x-text="previewCopy()"></p>
                        </div>
                    </div>
                </div>

                <div class="card-footer sticky-bottom bg-body d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                    <div class="small text-secondary">
                        Allocated <span class="fw-bold" x-text="'$' + allocatedDollars()"></span>
                        <span x-show="!isCardMethod() && remainingCents() > 0">
                            · Excess <span class="fw-bold" x-text="'$' + remainingDollars()"></span>
                            <span x-text="excessLabel()"></span>
                        </span>
                    </div>
                    <div class="d-flex flex-wrap gap-2 w-100 w-sm-auto justify-content-end">
                        <a href="{{ route('operations.financials.accounts-receivable.customer', $customer) }}" class="btn btn-ghost-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary" :disabled="busy || allocatedCents() <= 0 || (showsExcess() && !disposition)">
                            <span x-show="!isCardMethod() && !isStoreCredit()">Record payment</span>
                            <span x-show="isStoreCredit()">Apply account credit</span>
                            <span x-show="isCardMethod()" x-text="'Charge $' + allocatedDollars()">Charge card</span>
                        </button>
                    </div>
                </div>
            </form>
        @endif
    </x-tabler.page>

</x-operations.app>
