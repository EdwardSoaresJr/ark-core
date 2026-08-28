@php
    $squareIntent = $squareIntent ?? 'payment';
    $squareEnabled = $squareIntent === 'deposit'
        ? ($financial['canChargeDepositWithSquare'] ?? false)
        : ($financial['canChargeWithSquare'] ?? false);
    $square = $financial['square'] ?? [];
    $pollUrl = route('operations.repair-orders.square-payments.show', [$repairOrder, '__ATTEMPT__']);
    $completeUrl = route('operations.repair-orders.square-payments.complete', [$repairOrder, '__ATTEMPT__']);
    $cancelUrl = route('operations.repair-orders.square-payments.destroy', [$repairOrder, '__ATTEMPT__']);
    $initiateUrl = $squareIntent === 'deposit'
        ? route('operations.repair-orders.square-deposits.store', $repairOrder)
        : route('operations.repair-orders.square-payments.store', $repairOrder);
    $defaultAmount = $squareIntent === 'deposit'
        ? ($financial['remainingSuggestedDepositDecimal'] ?? $financial['remainingCollectableDepositDecimal'] ?? $financial['suggestedDepositDecimal'] ?? '')
        : ($financial['settlementBalanceDueDecimal'] ?? $financial['balanceDueDecimal'] ?? '');
    $cardContainerId = 'ark-square-card-container-'.$squareIntent.'-'.$repairOrder->repair_order_id;
    $amountInputId = 'square-'.$squareIntent.'-amount-'.$repairOrder->repair_order_id;
    $heading = $squareIntent === 'deposit' ? 'Square deposit' : 'Square';
    $amountLabel = $squareIntent === 'deposit' ? 'Deposit amount' : 'Charge amount';
@endphp

@if ($squareEnabled)
    <div
        class="grid gap-2 border border-slate-200 bg-white p-3"
        x-data="arkSquarePayments({
            repairOrderId: @js($repairOrder->repair_order_id),
            estimateVersion: @js($estimateVersion),
            initiateUrl: @js($initiateUrl),
            pollUrlTemplate: @js($pollUrl),
            completeUrlTemplate: @js($completeUrl),
            cancelUrlTemplate: @js($cancelUrl),
            square: @js($square),
            balanceDueDecimal: @js($defaultAmount),
            intent: @js($squareIntent),
            cardContainerSelector: @js('#'.$cardContainerId),
        })"
    >
        <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-400">{{ $heading }}</p>

        <div class="flex flex-wrap gap-2">
            @if ($square['terminalEnabled'] ?? false)
                <button
                    type="button"
                    class="inline-flex min-h-10 flex-1 items-center justify-center rounded-sm bg-slate-950 px-3 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-60"
                    :disabled="busy || polling"
                    @click="chargeTerminal()"
                >
                    <span x-show="! busy && ! polling">{{ $squareIntent === 'deposit' ? 'Collect deposit on reader' : 'Charge card on reader' }}</span>
                    <span x-show="busy && ! polling" x-cloak>Starting…</span>
                    <span x-show="polling" x-cloak>Waiting on reader…</span>
                </button>
            @endif

            @if ($square['keyedEnabled'] ?? false)
                <button
                    type="button"
                    class="inline-flex min-h-10 flex-1 items-center justify-center rounded-sm border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-800 hover:border-slate-400 disabled:opacity-60"
                    :disabled="busy"
                    @click="openKeyedForm()"
                >
                    Enter card
                </button>
            @endif
        </div>

        <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_8rem]">
            <label class="sr-only" for="{{ $amountInputId }}">Amount</label>
            <input
                id="{{ $amountInputId }}"
                x-model="amount"
                type="text"
                inputmode="decimal"
                class="h-9 w-full rounded-sm border-slate-300 text-sm font-semibold tabular-nums text-slate-950"
            >
            <p class="self-center text-[11px] font-semibold leading-4 text-slate-500">{{ $amountLabel }}</p>
        </div>

        <p x-show="message" x-text="message" x-cloak class="text-xs font-semibold text-emerald-800" aria-live="polite"></p>
        <p x-show="error" x-text="error" x-cloak class="text-xs font-semibold text-red-700" aria-live="polite"></p>

        <div x-show="polling" x-cloak>
            <button
                type="button"
                class="inline-flex min-h-9 w-full items-center justify-center rounded-sm border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:border-slate-400"
                @click="cancelAttempt()"
            >
                Cancel reader checkout
            </button>
        </div>

        <div x-show="showKeyedForm" x-cloak class="grid gap-2 border-t border-slate-200 pt-3">
            <p class="text-xs font-semibold text-slate-700">Keyed card entry</p>
            <div id="{{ $cardContainerId }}" class="min-h-12 rounded-sm border border-slate-200 bg-slate-50 p-2"></div>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="inline-flex min-h-10 flex-1 items-center justify-center rounded-sm bg-slate-950 px-3 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-60"
                    :disabled="busy"
                    @click="submitKeyedPayment()"
                >
                    <span x-show="! busy">{{ $squareIntent === 'deposit' ? 'Collect deposit' : 'Charge card' }}</span>
                    <span x-show="busy" x-cloak>Processing…</span>
                </button>
                <button
                    type="button"
                    class="inline-flex min-h-10 items-center justify-center rounded-sm border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:border-slate-400"
                    @click="closeKeyedForm()"
                >
                    Cancel
                </button>
            </div>
        </div>
    </div>
@endif
