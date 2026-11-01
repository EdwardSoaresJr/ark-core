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
    $readers = array_values(array_map(
        static fn (array $device): array => [
            'device_ref' => $device['device_ref'],
            'label' => $device['label'],
            'available' => (bool) ($device['ready'] ?? true),
        ],
        $devices,
    ));
    $readerStorageKey = 'ark:payment-reader:'.(\App\Ark\Install\InstallationIdentity::read() ?? request()->getHost());
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
        readers: @js($readers),
        readerStorageKey: @js($readerStorageKey),
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
        class="btn btn-primary w-100"
        data-payment-workspace-open
        @click="openWorkspace()"
    >
        Take payment
    </button>

    {{-- ark-payment-capture.js shows and hides this overlay and raises the rail above the page while it is open. --}}
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
        <div class="card shadow-lg position-relative w-100" style="max-width: 44rem; max-height: calc(100dvh - 1rem)" @click.stop>
            <div class="card-header">
                <h2 id="payment-workspace-title-{{ $repairOrder->getKey() }}" class="card-title">Take payment</h2>
                <button type="button" class="btn-close ms-auto" aria-label="Close" x-show="!transactionBound()" @click="requestClose($event)"></button>
            </div>

            {{-- Bootstrap display utilities are !important and would defeat x-show, so shown/hidden blocks wrap their flex layout. --}}
            <div class="card-body d-flex flex-column gap-3 overflow-auto" style="min-height: 0">
                <div
                    data-terminal-payment
                    data-terminal-payment-phase="{{ $terminal['phase'] ?? '' }}"
                    role="status"
                    aria-live="polite"
                    x-show="terminalPhase"
                    x-cloak
                    :data-terminal-payment-phase="terminalPhase"
                >
                    <div class="d-flex flex-column align-items-center gap-3 py-4 text-center">
                    <p class="h3 mb-0" data-terminal-payment-headline x-text="terminalHeadline">{{ $terminal['headline'] ?? '' }}</p>
                    <p class="display-5 fw-bold ark-money mb-0" data-terminal-payment-amount x-text="terminalAmount || amount">{{ $terminal['amount'] ?? '' }}</p>
                    <div class="spinner-border text-primary" style="width: 3.5rem; height: 3.5rem" role="presentation" data-terminal-payment-spinner x-show="terminalSpinning" @unless($terminal['spinning'] ?? false) x-cloak @endunless></div>
                    <p class="fw-medium mb-0" x-show="terminalNote" x-text="terminalNote">{{ $terminal['note'] ?? '' }}</p>
                    <p class="text-secondary mb-0" x-show="terminalDetail" x-text="terminalDetail">{{ $terminal['detail'] ?? '' }}</p>
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        data-payment-workspace-cancel
                        @if (filled($terminal['cancel_url'] ?? null)) data-payment-cancel-url="{{ $terminal['cancel_url'] }}" @endif
                        @unless (filled($terminal['cancel_url'] ?? null)) hidden @endunless
                        :disabled="busy || !terminalCancelUrl"
                        @click="cancelTerminal()"
                    >
                        Cancel terminal
                    </button>
                    </div>
                </div>

                <div x-show="!terminalPhase" @if ($terminal) x-cloak @endif>
                <div class="d-flex flex-column gap-3">
                    <p class="small fw-medium text-danger mb-0" x-show="cardError" x-cloak x-text="cardError"></p>
                @unless ($terminal)
                    @if (! $captureReady && ($financial['canTakePaymentCapture'] ?? false) && filled($readiness['message'] ?? null))
                        <p class="small text-secondary mb-0" data-card-processing-notice>
                            {{ $readiness['message'] }}
                            @if ($canLedger)
                                Recorded payment methods still work.
                            @endif
                        </p>
                    @endif

                    @if ($ledgerKind === 'deposit')
                        @if (filled($financial['remainingSuggestedDeposit'] ?? null))
                            <p class="small text-secondary mb-0">Up to {{ $financial['remainingSuggestedDeposit'] }} remaining on suggested deposit.</p>
                        @elseif (filled($financial['remainingCollectableDeposit'] ?? null))
                            <p class="small text-secondary mb-0">Up to {{ $financial['remainingCollectableDeposit'] }} remaining on this repair.</p>
                        @endif
                    @endif

                    <div>
                        <label class="subheader d-block text-center" for="payment-workspace-amount-{{ $repairOrder->getKey() }}">Amount</label>
                        <div class="d-flex align-items-baseline justify-content-center gap-1">
                            <span class="fs-1 fw-bold">$</span>
                            <input
                                id="payment-workspace-amount-{{ $repairOrder->getKey() }}"
                                class="form-control-plaintext display-5 fw-bold ark-money text-center p-0"
                                data-payment-workspace-amount
                                inputmode="decimal"
                                autocomplete="off"
                                :value="amount"
                                @input="onAmountTyped($event)"
                            >
                        </div>
                    </div>

                    <div class="d-grid gap-2" style="grid-template-columns: repeat(3, minmax(0, 1fr))" data-payment-workspace-keypad>
                        @foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9', '.', '0', 'backspace'] as $key)
                            <button type="button" class="btn btn-outline-secondary btn-lg fs-3 fw-bold ark-money" @click="pressAmount(@js($key))">
                                {{ $key === 'backspace' ? 'Delete' : $key }}
                            </button>
                        @endforeach
                    </div>

                    <div class="d-flex flex-wrap gap-2" role="group" aria-label="Payment method">
                        @if ($supportsTerminal)
                            <button type="button" class="btn flex-fill" :class="method === 'terminal' ? 'btn-primary' : 'btn-outline-secondary'" :aria-pressed="method === 'terminal'" @click="method = 'terminal'">Reader</button>
                        @endif
                        @if ($supportsKeyed)
                            <button type="button" class="btn flex-fill" :class="method === 'keyed' ? 'btn-primary' : 'btn-outline-secondary'" :aria-pressed="method === 'keyed'" @click="openKeyed()">Keyed card</button>
                        @endif
                        @if ($canLedger)
                            <button type="button" class="btn flex-fill" :class="method === 'cash' ? 'btn-primary' : 'btn-outline-secondary'" :aria-pressed="method === 'cash'" @click="method = 'cash'">Cash</button>
                        @endif
                    </div>

                    @if ($canLedger && $manualMethods !== [])
                        <div class="d-flex flex-wrap gap-2" role="group" aria-label="Other payment methods">
                            @foreach ($manualMethods as $manualMethod)
                                <button type="button" class="btn flex-fill" :class="method === @js($manualMethod['key']) ? 'btn-primary' : 'btn-outline-secondary'" :aria-pressed="method === @js($manualMethod['key'])" @click="method = @js($manualMethod['key'])">{{ $manualMethod['label'] }}</button>
                            @endforeach
                        </div>
                    @endif

                    <div class="d-flex flex-column align-items-center" data-payment-paid-date-picker @click.outside="closePaidDate()">
                        <button type="button" class="btn btn-link btn-sm" data-payment-paid-date @click="togglePaidDate()" :disabled="transactionBound()" :aria-expanded="paidDateOpen">
                            Paid <span class="ms-1" x-text="paidDateLabel()">{{ $paidDateTodayLabel }}</span>
                        </button>
                        <div class="card card-sm mt-1" style="width: 16rem" x-show="paidDateOpen" x-cloak>
                            <div class="card-body p-2">
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                    <button type="button" class="btn btn-sm btn-icon btn-ghost-secondary" aria-label="Previous month" @click="shiftCalendar(-1)">
                                        <x-tabler.icon name="arrow-left" />
                                    </button>
                                    <span class="small fw-bold" x-text="calendarTitle()"></span>
                                    <button type="button" class="btn btn-sm btn-icon btn-ghost-secondary" aria-label="Next month" @click="shiftCalendar(1)" :disabled="!canShiftCalendar(1)">
                                        <x-tabler.icon name="arrow-right" />
                                    </button>
                                </div>
                                <div class="d-grid text-center subheader" style="grid-template-columns: repeat(7, minmax(0, 1fr))" aria-hidden="true">
                                    <span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span>
                                </div>
                                <div class="d-grid gap-1" style="grid-template-columns: repeat(7, minmax(0, 1fr))">
                                    <template x-for="(cell, index) in calendarCells()" :key="index">
                                        <button
                                            type="button"
                                            class="btn btn-sm px-0"
                                            :class="cell.selected ? 'btn-primary' : 'btn-ghost-secondary'"
                                            :disabled="!cell.inMonth || cell.disabled"
                                            x-text="cell.label"
                                            @click="choosePaidDate(cell.iso)"
                                        ></button>
                                    </template>
                                </div>
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
                                <label class="d-block">
                                    <span class="form-label">Reader</span>
                                    {{-- The transparent select over the display keeps native keyboard and touch selection; the display adds the device id line. --}}
                                    <span class="form-select position-relative d-block" data-payment-reader-control>
                                        <span class="d-block" aria-hidden="true" data-payment-reader-display>
                                            <span class="d-block text-truncate fw-medium" x-text="readerDisplay().primary">Choose a reader</span>
                                            <span class="d-block text-truncate small text-secondary font-monospace" x-text="readerDisplay().secondary"></span>
                                        </span>
                                        <select class="position-absolute top-0 start-0 w-100 h-100 opacity-0" style="cursor: pointer" x-model="deviceRef" @change="rememberReader()" data-payment-reader-select>
                                        <option value="" :selected="deviceRef === ''">Choose a reader</option>
                                        @foreach ($readers as $reader)
                                            @if ($reader['available'])
                                                <option value="{{ $reader['device_ref'] }}" data-payment-reader-label="{{ $reader['label'] }}">{{ $reader['label'] }}</option>
                                            @else
                                                <option value="{{ $reader['device_ref'] }}" data-payment-reader-label="{{ $reader['label'] }}" disabled>{{ $reader['label'] }} (offline)</option>
                                            @endif
                                        @endforeach
                                        </select>
                                    </span>
                                    <span class="d-block small text-warning" x-text="readerNotice()"></span>
                                </label>
                            </template>

                            <template x-if="method === 'keyed' && publicConfig">
                                <div>
                                    <div :id="cardContainerId" class="form-control" style="min-height: 48px"></div>
                                    <p class="small text-secondary mt-1 mb-0" x-show="cardError" x-text="cardError"></p>
                                </div>
                            </template>

                            <button
                                type="submit"
                                class="btn btn-primary btn-lg w-100 mt-3"
                                :disabled="busy || !amountCanSubmit() || (method === 'terminal' && !selectedReader())"
                            >
                                <span x-show="method === 'terminal'" x-text="presentToReaderLabel(amount)" data-payment-reader-button>Present to reader</span>
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

                            <label class="visually-hidden" for="payment-reference-{{ $repairOrder->getKey() }}">Reference</label>
                            <input id="payment-reference-{{ $repairOrder->getKey() }}" name="reference" type="text" value="{{ old('reference') }}" placeholder="Reference / note (optional)" class="form-control mb-2">

                            <button type="submit" class="btn btn-primary btn-lg w-100" :disabled="busy || !amountCanSubmit()">
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
                </div>

                @error('capture')
                    <p class="small fw-medium text-danger mb-0">{{ $message }}</p>
                @enderror
                @error('amount')
                    <p class="small fw-medium text-danger mb-0">{{ $message }}</p>
                @enderror
                @error('deposit_confirmed')
                    <p class="small fw-medium text-danger mb-0">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>
</div>
