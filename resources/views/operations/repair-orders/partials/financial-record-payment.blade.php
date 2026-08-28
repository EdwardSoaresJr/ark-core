<form
    method="POST"
    action="{{ route('operations.repair-orders.payment.update', $repairOrder) }}"
    data-refresh-scope="rail"
    data-continuity-focus="#payment-amount-{{ $repairOrder->repair_order_id }}"
    @submit.prevent="submitWorksheetForm($event)"
    class="grid gap-2 border border-slate-200 bg-white p-3"
>
    @csrf
    @method('PATCH')
    <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
    <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-400">Record Payment</p>
    <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_8rem]">
        <label class="sr-only" for="payment-amount-{{ $repairOrder->repair_order_id }}">Amount</label>
        <input
            id="payment-amount-{{ $repairOrder->repair_order_id }}"
            name="amount"
            type="text"
            inputmode="decimal"
            value="{{ old('amount', $financial['settlementBalanceDueDecimal'] ?? $financial['balanceDueDecimal']) }}"
            required
            class="h-9 rounded-sm border-slate-300 text-sm font-semibold tabular-nums text-slate-950"
        >
        <label class="sr-only" for="payment-method-{{ $repairOrder->repair_order_id }}">Method</label>
        <select id="payment-method-{{ $repairOrder->repair_order_id }}" name="payment_method" required class="h-9 rounded-sm border-slate-300 text-sm font-semibold text-slate-700">
            @foreach ($financial['paymentMethods'] as $method)
                <option value="{{ $method->value }}" @selected(old('payment_method') === $method->value)>{{ $method->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_8rem]">
        <div>
            <label class="sr-only" for="payment-paid-at-{{ $repairOrder->repair_order_id }}">Paid date</label>
            <input
                id="payment-paid-at-{{ $repairOrder->repair_order_id }}"
                name="paid_at"
                type="date"
                value="{{ old('paid_at') }}"
                max="{{ now()->timezone(config('app.display_timezone'))->toDateString() }}"
                class="h-9 w-full rounded-sm border-slate-300 text-sm text-slate-700"
            >
        </div>
        <p class="self-center text-[11px] font-semibold leading-4 text-slate-500 sm:col-span-1">
            Paid date — blank for today
        </p>
    </div>
    @error('paid_at')
        <p class="text-xs font-semibold text-red-700">{{ $message }}</p>
    @enderror
    <label class="sr-only" for="payment-reference-{{ $repairOrder->repair_order_id }}">Reference</label>
    <input
        id="payment-reference-{{ $repairOrder->repair_order_id }}"
        name="reference"
        type="text"
        value="{{ old('reference') }}"
        placeholder="Reference / note (optional)"
        class="h-9 rounded-sm border-slate-300 text-sm text-slate-700"
    >
    <p class="text-[11px] font-semibold leading-4 text-slate-500">
        Cash above balance due is treated as change given — not store credit. Prefer the exact amount due.
    </p>
    <button type="submit" class="inline-flex min-h-10 w-full items-center justify-center rounded-sm border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-800 hover:border-slate-400">
        Record Payment
    </button>
</form>
