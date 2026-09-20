<form
    method="POST"
    action="{{ route('operations.repair-orders.deposit.update', $repairOrder) }}"
    data-refresh-scope="rail"
    data-continuity-focus="#deposit-record-button-{{ $repairOrder->repair_order_id }}"
    @keydown.enter.prevent
    @submit.prevent="
        const amountInput = $el.querySelector('[name=amount]');
        const amount = amountInput?.value?.trim();
        const methodLabel = $el.querySelector('[name=payment_method]')?.selectedOptions?.[0]?.text?.trim() ?? 'payment';
        if (! amount) {
            amountInput?.focus();
            return;
        }
        if (! confirm(`Confirm ${amount} ${methodLabel} collected from the customer?\n\nThis writes to the payment ledger — only continue if money actually changed hands.`)) {
            return;
        }
        $el.querySelector('[name=deposit_confirmed]').value = '1';
        submitWorksheetForm($event);
    "
    class="grid gap-2 border border-amber-200 bg-amber-50/40 p-3"
>
    @csrf
    @method('PATCH')
    <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
    <input type="hidden" name="deposit_confirmed" value="0">

    <div>
        <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-amber-900">Customer paid — record deposit</p>
        <p class="mt-1 text-[11px] leading-4 text-amber-950/80">Ledger entry only. Use when cash, check, or manual card actually changed hands.</p>
        @if (filled($financial['remainingSuggestedDeposit'] ?? null))
            <p class="mt-1 text-[11px] font-semibold text-amber-950">Up to {{ $financial['remainingSuggestedDeposit'] }} remaining on suggested deposit.</p>
        @elseif (filled($financial['remainingCollectableDeposit'] ?? null))
            <p class="mt-1 text-[11px] font-semibold text-amber-950">Up to {{ $financial['remainingCollectableDeposit'] }} remaining on this repair.</p>
        @endif
    </div>

    <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_8rem]">
        <label class="sr-only" for="deposit-amount-{{ $repairOrder->repair_order_id }}">Amount collected</label>
        <input
            id="deposit-amount-{{ $repairOrder->repair_order_id }}"
            name="amount"
            type="text"
            inputmode="decimal"
            value="{{ old('amount') }}"
            required
            autocomplete="off"
            placeholder="{{ filled($financial['remainingSuggestedDepositDecimal'] ?? null) ? 'e.g. '.$financial['remainingSuggestedDepositDecimal'] : (filled($financial['remainingCollectableDepositDecimal'] ?? null) ? 'e.g. '.$financial['remainingCollectableDepositDecimal'] : '0.00') }}"
            class="h-9 w-full rounded-sm border-slate-300 bg-white text-sm font-semibold tabular-nums text-slate-950"
        >
        <label class="sr-only" for="deposit-method-{{ $repairOrder->repair_order_id }}">Method</label>
        <select id="deposit-method-{{ $repairOrder->repair_order_id }}" name="payment_method" required class="h-9 rounded-sm border-slate-300 bg-white text-sm font-semibold text-slate-700">
            <option value="" disabled @selected(old('payment_method') === null)>Method</option>
            @foreach ($financial['paymentMethods'] as $method)
                <option value="{{ $method->value }}" @selected(old('payment_method') === $method->value)>{{ $method->label() }}</option>
            @endforeach
        </select>
    </div>

    @error('amount')
        <p class="text-xs font-semibold text-red-700">{{ $message }}</p>
    @enderror
    @error('deposit_confirmed')
        <p class="text-xs font-semibold text-red-700">{{ $message }}</p>
    @enderror

    <label class="sr-only" for="deposit-reference-{{ $repairOrder->repair_order_id }}">Reference</label>
    <input
        id="deposit-reference-{{ $repairOrder->repair_order_id }}"
        name="reference"
        type="text"
        value="{{ old('reference') }}"
        placeholder="Reference / note (optional)"
        class="h-9 rounded-sm border-slate-300 bg-white text-sm text-slate-700"
    >
    <button
        id="deposit-record-button-{{ $repairOrder->repair_order_id }}"
        type="submit"
        class="inline-flex min-h-10 w-full items-center justify-center rounded-sm border border-amber-300 bg-white px-3 text-xs font-semibold text-amber-950 hover:border-amber-400"
    >
        Record deposit in ledger
    </button>
</form>
