@php
    $captureAttempts = $financial['paymentCaptureAttempts'] ?? [];
    $readiness = app(\App\Ark\Operations\Payments\Capture\PaymentCaptureReadinessProjection::class)->current();
    $ledgerKind = ($financial['canRecordPayment'] ?? false) ? 'payment' : 'deposit';
    $canLedger = ($financial['canRecordPayment'] ?? false) || ($financial['canRecordManualDeposit'] ?? false);
    $suggestedAmount = $ledgerKind === 'payment'
        ? (string) ($financial['settlementBalanceDueDecimal'] ?? $financial['balanceDueDecimal'] ?? '')
        : (string) ($financial['remainingSuggestedDepositDecimal'] ?? $financial['remainingCollectableDepositDecimal'] ?? '');
    $devices = $readiness['devices'] ?? [];
    $publicConfig = $readiness['public_config'] ?? null;
    $captureReady = (bool) ($readiness['ready'] ?? false);
    $supportsTerminal = $captureReady && ($readiness['supports_terminal'] ?? false) && $devices !== [];
    $supportsKeyed = $captureReady && ($readiness['supports_keyed'] ?? false);
    $defaultMethod = $supportsTerminal ? 'terminal' : ($supportsKeyed ? 'keyed' : 'cash');
    $defaultDevice = $devices[0]['device_ref'] ?? '';
    $terminalWait = null;
    foreach ($captureAttempts as $attempt) {
        if (($attempt['terminal']['blocks_form'] ?? false) === true) {
            $terminalWait = $attempt;
            break;
        }
    }
    $terminal = $terminalWait['terminal'] ?? null;
    $transactionBound = in_array($terminal['phase'] ?? '', ['presenting', 'waiting', 'processing', 'reconciliation'], true);
    $workspaceOpen = $transactionBound
        || $errors->has('amount')
        || $errors->has('capture')
        || $errors->has('deposit_confirmed')
        || $errors->has('payment_method');
    $manualMethods = \App\Ark\Operations\Financial\ManualPaymentMethods::current();
    $paidDateToday = now()->timezone(config('app.display_timezone'))->toDateString();
    $paidDateTodayLabel = now()->timezone(config('app.display_timezone'))->format('M j, Y');
    $paidOn = old('paid_at');
@endphp

<div
    data-payment-workspace
    data-payment-workspace-suggested="{{ $suggestedAmount }}"
    x-data="arkPaymentCapture({
        workspace: true,
        open: @js($workspaceOpen),
        suggestedAmount: @js($suggestedAmount),
        publicConfig: @js($publicConfig),
        defaultMethod: @js($defaultMethod),
        defaultDevice: @js($defaultDevice),
        manualMethods: @js($manualMethods),
        paidDateToday: @js($paidDateToday),
        paidDateTodayLabel: @js($paidDateTodayLabel),
        paidOn: @js(is_string($paidOn) ? $paidOn : ''),
        cardContainerId: 'ark-payment-capture-card-{{ $repairOrder->getKey() }}',
        terminal: @js($terminal),
    })"
>
    <button
        type="button"
        class="inline-flex min-h-10 w-full items-center justify-center rounded-sm bg-sky-700 px-3 text-xs font-bold text-white hover:bg-sky-800"
        data-payment-workspace-open
        @click="openWorkspace()"
    >
        Take payment
    </button>

    <div
        x-cloak
        class="ops-workspace-modal"
        data-payment-workspace-modal
        role="dialog"
        aria-modal="true"
        aria-labelledby="payment-workspace-title-{{ $repairOrder->getKey() }}"
        data-payment-workspace-dismiss="{{ $transactionBound ? 'locked' : 'free' }}"
        :data-payment-workspace-dismiss="transactionBound() ? 'locked' : 'free'"
        @keydown.escape.window="requestClose($event)"
        @keydown.window="pressAmountKey($event)"
    >
        <button
            type="button"
            class="ops-workspace-modal__backdrop"
            aria-label="Close"
            @click="requestClose($event)"
        ></button>
        <div class="ops-workspace-modal__dialog ops-payment-workspace__dialog" @click.stop>
            <header class="ops-workspace-modal__header">
                <h2 id="payment-workspace-title-{{ $repairOrder->getKey() }}" class="ops-workspace-modal__title">Take payment</h2>
                <button type="button" class="ops-workspace-modal__close" x-show="!transactionBound()" @click="requestClose($event)">
                    Close
                </button>
            </header>

            <div class="ops-payment-workspace__body">
                <div
                    class="ops-payment-workspace__terminal"
                    data-terminal-payment
                    data-terminal-payment-phase="{{ $terminal['phase'] ?? '' }}"
                    role="status"
                    aria-live="polite"
                    x-show="terminalPhase"
                    x-cloak
                    :data-terminal-payment-phase="terminalPhase"
                >
                    <p class="ops-terminal-payment__headline" data-terminal-payment-headline x-text="terminalHeadline">{{ $terminal['headline'] ?? '' }}</p>
                    <p class="ops-payment-workspace__terminal-amount" data-terminal-payment-amount x-text="terminalAmount || amount">{{ $terminal['amount'] ?? '' }}</p>
                    <div class="ops-payment-workspace__spinner" data-terminal-payment-spinner x-show="terminalSpinning" @unless($terminal['spinning'] ?? false) x-cloak @endunless></div>
                    <p class="text-sm font-semibold text-slate-700" x-show="terminalNote" x-text="terminalNote">{{ $terminal['note'] ?? '' }}</p>
                    <p class="text-sm text-slate-600" x-show="terminalDetail" x-text="terminalDetail">{{ $terminal['detail'] ?? '' }}</p>
                    <button
                        type="button"
                        class="inline-flex min-h-10 items-center rounded-sm border border-slate-400 bg-white px-3 text-xs font-bold text-slate-800 disabled:opacity-50"
                        data-payment-workspace-cancel
                        @if (filled($terminal['cancel_url'] ?? null)) data-payment-cancel-url="{{ $terminal['cancel_url'] }}" @endif
                        @unless (filled($terminal['cancel_url'] ?? null)) hidden @endunless
                        :disabled="busy || !terminalCancelUrl"
                        @click="cancelTerminal()"
                    >
                        Cancel terminal
                    </button>
                </div>

                <div x-show="!terminalPhase" @if ($terminal) x-cloak @endif>
                    <p class="text-xs font-semibold text-red-700" x-show="cardError" x-cloak x-text="cardError"></p>
                @unless ($terminal)
                    @if (! $captureReady && ($financial['canTakePaymentCapture'] ?? false))
                        <p class="text-xs font-semibold text-amber-900">
                            {{ $readiness['message'] ?? 'Card capture is not ready. Connect payments in Platform or use cash.' }}
                        </p>
                    @endif

                    @if ($ledgerKind === 'deposit')
                        @if (filled($financial['remainingSuggestedDeposit'] ?? null))
                            <p class="text-xs font-semibold text-slate-600">Up to {{ $financial['remainingSuggestedDeposit'] }} remaining on suggested deposit.</p>
                        @elseif (filled($financial['remainingCollectableDeposit'] ?? null))
                            <p class="text-xs font-semibold text-slate-600">Up to {{ $financial['remainingCollectableDeposit'] }} remaining on this repair.</p>
                        @endif
                    @endif

                    <div>
                        <p class="text-center text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Amount</p>
                        <div class="ops-payment-workspace__amount-row">
                            <span class="ops-payment-workspace__currency">$</span>
                            <input
                                class="ops-payment-workspace__amount"
                                data-payment-workspace-amount
                                inputmode="decimal"
                                autocomplete="off"
                                :value="amount"
                                @input="onAmountTyped($event)"
                            >
                        </div>
                    </div>

                    <div class="ops-payment-workspace__keypad" data-payment-workspace-keypad>
                        @foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9', '.', '0', 'backspace'] as $key)
                            <button type="button" class="ops-payment-workspace__key" @click="pressAmount(@js($key))">
                                {{ $key === 'backspace' ? 'Delete' : $key }}
                            </button>
                        @endforeach
                    </div>

                    <div class="ops-payment-workspace__methods">
                        @if ($supportsTerminal)
                            <button type="button" class="ops-payment-workspace__method" :class="method === 'terminal' && 'ops-payment-workspace__method--selected'" @click="method = 'terminal'">Reader</button>
                        @endif
                        @if ($supportsKeyed)
                            <button type="button" class="ops-payment-workspace__method" :class="method === 'keyed' && 'ops-payment-workspace__method--selected'" @click="openKeyed()">Keyed card</button>
                        @endif
                        @if ($canLedger)
                            <button type="button" class="ops-payment-workspace__method" :class="method === 'cash' && 'ops-payment-workspace__method--selected'" @click="method = 'cash'">Cash</button>
                        @endif
                    </div>

                    @if ($canLedger && $manualMethods !== [])
                        <div class="ops-payment-workspace__methods">
                            @foreach ($manualMethods as $manualMethod)
                                <button type="button" class="ops-payment-workspace__method" :class="method === @js($manualMethod['key']) && 'ops-payment-workspace__method--selected'" @click="method = @js($manualMethod['key'])">{{ $manualMethod['label'] }}</button>
                            @endforeach
                        </div>
                    @endif

                    <div class="ops-payment-workspace__date" @click.outside="closePaidDate()">
                        <button type="button" class="ops-payment-workspace__date-label" data-payment-paid-date @click="togglePaidDate()" :disabled="transactionBound()">
                            Paid <span x-text="paidDateLabel()">{{ $paidDateTodayLabel }}</span>
                        </button>
                        <div
                            class="ops-payment-workspace__calendar"
                            :class="paidDateOpen && 'ops-payment-workspace__calendar--open'"
                        >
                            <div class="ops-payment-workspace__calendar-nav">
                                <button type="button" aria-label="Previous month" @click="shiftCalendar(-1)">&lsaquo;</button>
                                <span x-text="calendarTitle()"></span>
                                <button type="button" aria-label="Next month" @click="shiftCalendar(1)" :disabled="!canShiftCalendar(1)">&rsaquo;</button>
                            </div>
                            <div class="ops-payment-workspace__calendar-week" aria-hidden="true">
                                <span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span>
                            </div>
                            <div class="ops-payment-workspace__calendar-grid">
                                <template x-for="(cell, index) in calendarCells()" :key="index">
                                    <button
                                        type="button"
                                        class="ops-payment-workspace__calendar-day"
                                        :class="cell.selected && 'ops-payment-workspace__calendar-day--selected'"
                                        :disabled="!cell.inMonth || cell.disabled"
                                        x-text="cell.label"
                                        @click="choosePaidDate(cell.iso)"
                                    ></button>
                                </template>
                            </div>
                        </div>
                    </div>

                    @if ($captureReady)
                        <form
                            method="POST"
                            action="{{ route('operations.repair-orders.payment-capture.store', $repairOrder) }}"
                            data-refresh-scope="rail"
                            x-show="method === 'terminal' || method === 'keyed'"
                            @submit.prevent="submitWorkspace($event)"
                        >
                            @csrf
                            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                            <input type="hidden" name="context_kind" value="{{ $ledgerKind }}">
                            <input type="hidden" name="amount" value="{{ $suggestedAmount }}">
                            <input type="hidden" name="paid_at" value="">
                            <input type="hidden" name="capture_method" x-bind:value="method === 'keyed' ? 'keyed' : 'terminal'">
                            <input type="hidden" name="device_ref" x-bind:value="method === 'terminal' ? deviceRef : ''">
                            <input type="hidden" name="source_token" x-bind:value="sourceToken">
                            @if (app()->environment('local', 'testing') && ($readiness['provider'] ?? null) !== 'square')
                                <input type="hidden" name="stub_scenario" value="{{ request('stub_scenario', 'immediate_success') }}">
                            @endif

                            <template x-if="method === 'terminal'">
                                <label class="block">
                                    <span class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Reader</span>
                                    <select class="mt-1 h-10 w-full rounded-sm border border-slate-300 px-2 text-sm" x-model="deviceRef">
                                        @foreach ($devices as $device)
                                            @if ($device['ready'] ?? true)
                                                <option value="{{ $device['device_ref'] }}">{{ $device['label'] }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </label>
                            </template>

                            <template x-if="method === 'keyed' && publicConfig">
                                <div class="mt-2">
                                    <div :id="cardContainerId" class="min-h-[48px] rounded-sm border border-slate-300 bg-white p-2"></div>
                                    <p class="mt-1 text-[11px] text-slate-500" x-show="cardError" x-text="cardError"></p>
                                </div>
                            </template>

                            <button
                                type="submit"
                                class="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-sm bg-sky-700 px-3 text-sm font-bold text-white hover:bg-sky-800 disabled:opacity-50"
                                :disabled="busy || !amountCanSubmit()"
                            >
                                <span x-show="method === 'terminal'" x-text="'Present $' + amount + ' to reader'">Present to reader</span>
                                <span x-show="method === 'keyed'" x-text="'Take $' + amount">Take payment</span>
                            </button>
                        </form>
                    @endif

                    @if ($canLedger)
                        <form
                            method="POST"
                            action="{{ ($financial['canRecordPayment'] ?? false) ? route('operations.repair-orders.payment.update', $repairOrder) : route('operations.repair-orders.deposit.update', $repairOrder) }}"
                            data-worksheet-continuity
                            data-refresh-scope="rail"
                            x-show="isLedgerMethod()"
                            @submit.prevent="submitWorkspace($event)"
                            class="grid gap-2"
                        >
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                            <input type="hidden" name="{{ App\Ark\Operations\Financial\FinancialSubmissionIntentGate::FIELD }}" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            @unless ($financial['canRecordPayment'] ?? false)
                                <input type="hidden" name="deposit_confirmed" value="0">
                            @endunless
                            <input type="hidden" name="amount" value="{{ $suggestedAmount }}">
                            <input type="hidden" name="payment_method" value="cash">
                            <input type="hidden" name="paid_at" value="">

                            <label class="sr-only" for="payment-reference-{{ $repairOrder->getKey() }}">Reference</label>
                            <input id="payment-reference-{{ $repairOrder->getKey() }}" name="reference" type="text" value="{{ old('reference') }}" placeholder="Reference / note (optional)" class="h-10 w-full rounded-sm border border-slate-300 text-sm">

                            <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-sm bg-slate-950 px-3 text-sm font-bold text-white disabled:opacity-50" :disabled="busy || !amountCanSubmit()">
                                @if ($financial['canRecordPayment'] ?? false)
                                    <span x-text="'Record $' + amount">Record Payment</span>
                                @else
                                    <span x-text="method === 'cash' ? ('Record $' + amount + ' cash') : ('Record $' + amount)">Record deposit in ledger</span>
                                @endif
                            </button>
                        </form>
                    @endif
                @endunless
                </div>

                @error('capture')
                    <p class="text-xs font-semibold text-red-700">{{ $message }}</p>
                @enderror
                @error('amount')
                    <p class="text-xs font-semibold text-red-700">{{ $message }}</p>
                @enderror
                @error('deposit_confirmed')
                    <p class="text-xs font-semibold text-red-700">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>
</div>
