function loadSquareSdk(url) {
    return new Promise((resolve, reject) => {
        if (window.Square) {
            resolve(window.Square);
            return;
        }

        const existing = document.querySelector('script[data-ark-payment-capture-sdk]');
        if (existing) {
            existing.addEventListener('load', () => resolve(window.Square));
            existing.addEventListener('error', reject);
            return;
        }

        const script = document.createElement('script');
        script.src = url;
        script.async = true;
        script.dataset.arkPaymentCaptureSdk = '1';
        script.onload = () => resolve(window.Square);
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

/**
 * Write the live capture fields onto the form before submit.
 * Alpine updates x-bind values on a later turn, so FormData would otherwise
 * post an empty card token on the first click.
 */
export function stampPaymentCaptureFields(form, state) {
    if (! form?.querySelector) {
        return;
    }

    const method = state?.method ?? '';
    const values = {
        capture_method: method,
        source_token: method === 'keyed' ? (state?.sourceToken ?? '') : '',
        device_ref: method === 'terminal' ? (state?.deviceRef ?? '') : '',
    };

    for (const [name, value] of Object.entries(values)) {
        const input = form.querySelector(`input[name="${name}"]`);

        if (input) {
            input.value = value;
        }
    }
}

const TERMINAL_SPINNING = new Set(['presenting', 'waiting', 'processing']);
const TERMINAL_BLOCKING = new Set(['presenting', 'waiting', 'processing', 'reconciliation']);
const TERMINAL_BOUND = new Set(['presenting', 'waiting', 'processing', 'reconciliation']);

export function paymentWorkspaceIsTransactionBound(phase) {
    return TERMINAL_BOUND.has(phase);
}

export function paymentCalendarMonth(iso) {
    return String(iso ?? '').slice(0, 7);
}

export function shiftPaymentCalendarMonth(month, delta, today) {
    const [year, monthNumber] = String(month).split('-').map((part) => Number.parseInt(part, 10));
    if (! year || ! monthNumber) {
        return paymentCalendarMonth(today);
    }

    const next = new Date(year, monthNumber - 1 + delta, 1);
    const shifted = `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}`;
    const cap = paymentCalendarMonth(today);

    return shifted > cap ? month : shifted;
}

export function paymentCalendarCells(month, today, selected) {
    const [year, monthNumber] = String(month).split('-').map((part) => Number.parseInt(part, 10));
    if (! year || ! monthNumber) {
        return [];
    }

    const firstWeekday = new Date(year, monthNumber - 1, 1).getDay();
    const daysInMonth = new Date(year, monthNumber, 0).getDate();
    const chosen = selected || today;
    const cells = [];

    for (let blank = 0; blank < firstWeekday; blank += 1) {
        cells.push({ iso: '', label: '', disabled: true, selected: false, inMonth: false });
    }

    for (let day = 1; day <= daysInMonth; day += 1) {
        const iso = `${year}-${String(monthNumber).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        cells.push({
            iso,
            label: String(day),
            disabled: iso > today,
            selected: iso === chosen,
            inMonth: true,
        });
    }

    return cells;
}

export function paymentCalendarTitle(month) {
    const [year, monthNumber] = String(month).split('-').map((part) => Number.parseInt(part, 10));
    if (! year || ! monthNumber) {
        return '';
    }

    return new Date(year, monthNumber - 1, 1).toLocaleDateString(undefined, {
        month: 'long',
        year: 'numeric',
    });
}

export function paymentPaidDateLabel(iso, today, todayLabel) {
    if (! iso || iso === today) {
        return todayLabel;
    }

    const [year, month, day] = iso.split('-').map((part) => Number.parseInt(part, 10));
    if (! year || ! month || ! day) {
        return todayLabel;
    }

    return new Date(year, month - 1, day).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

/**
 * Shape a typed amount for the existing payment fields.
 * This does not decide what the customer owes.
 */
export function normalizePaymentAmount(raw) {
    const cleaned = String(raw ?? '').replace(/[^0-9.]/g, '');
    const pieces = cleaned.split('.');
    let whole = pieces[0] ?? '';
    const fraction = pieces.length > 1 ? pieces.slice(1).join('').slice(0, 2) : null;

    whole = whole.replace(/^0+(?=\d)/, '');

    if (whole === '' && fraction !== null) {
        whole = '0';
    }

    if (fraction === null) {
        return whole;
    }

    return `${whole}.${fraction}`;
}

export function nextPaymentAmount(amount, key, replace = false) {
    const current = replace ? '' : String(amount ?? '');

    if (key === 'backspace') {
        return current.slice(0, -1);
    }

    if (key === '.') {
        if (current.includes('.')) {
            return current;
        }

        return current === '' ? '0.' : `${current}.`;
    }

    if (!/^\d$/.test(key)) {
        return current;
    }

    return normalizePaymentAmount(`${current}${key}`);
}

export function formatTerminalAmount(value) {
    const amount = Number.parseFloat(String(value ?? '').replace(/[^0-9.]/g, ''));

    if (! Number.isFinite(amount)) {
        return '';
    }

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(amount);
}

export function presentingTerminalPayment(amount) {
    return {
        phase: 'presenting',
        headline: 'Presenting to reader',
        note: 'Sending this payment to the reader.',
        detail: '',
        amount: formatTerminalAmount(amount),
        spinning: true,
        blocksForm: true,
        refreshUrl: '',
    };
}

export function applyTerminalPayment(payload) {
    if (! payload?.phase) {
        return null;
    }

    return {
        phase: payload.phase,
        headline: payload.headline ?? '',
        note: payload.note ?? '',
        detail: payload.detail ?? '',
        amount: payload.amount ?? '',
        spinning: payload.spinning ?? TERMINAL_SPINNING.has(payload.phase),
        blocksForm: payload.blocks_form ?? payload.blocksForm ?? TERMINAL_BLOCKING.has(payload.phase),
        refreshUrl: payload.refresh_url ?? payload.refreshUrl ?? '',
        cancelUrl: payload.cancel_url ?? payload.cancelUrl ?? '',
    };
}

/**
 * Take Payment rail - Terminal or Square Web Payments tokenized keyed entry.
 * Core never sees PAN/CVV; only source_token from Square.js.
 */
export function arkPaymentCapture(config = {}) {
    const initialTerminal = applyTerminalPayment(config.terminal);

    return {
        publicConfig: config.publicConfig ?? null,
        method: config.defaultMethod ?? 'keyed',
        deviceRef: config.defaultDevice ?? '',
        sourceToken: '',
        cardContainerId: config.cardContainerId ?? 'ark-payment-capture-card',
        card: null,
        cardReady: false,
        cardError: '',
        busy: false,
        terminalPhase: initialTerminal?.phase ?? '',
        terminalHeadline: initialTerminal?.headline ?? '',
        terminalNote: initialTerminal?.note ?? '',
        terminalDetail: initialTerminal?.detail ?? '',
        terminalAmount: initialTerminal?.amount ?? '',
        terminalSpinning: initialTerminal?.spinning ?? false,
        terminalBlocksForm: initialTerminal?.blocksForm ?? false,
        terminalRefreshUrl: initialTerminal?.refreshUrl ?? '',
        terminalCancelUrl: initialTerminal?.cancelUrl ?? '',
        workspace: Boolean(config.workspace),
        open: Boolean(config.open),
        suggestedAmount: String(config.suggestedAmount ?? ''),
        amount: String(config.suggestedAmount ?? ''),
        amountTouched: false,
        manualMethods: Array.isArray(config.manualMethods) ? config.manualMethods : [],
        paidDateToday: String(config.paidDateToday ?? ''),
        paidDateTodayLabel: String(config.paidDateTodayLabel ?? ''),
        paidOn: String(config.paidOn ?? ''),
        paidDateOpen: false,
        calendarMonth: paymentCalendarMonth(config.paidOn || config.paidDateToday),
        _terminalTimer: null,
        _terminalDestroyed: false,

        init() {
            this.$watch('open', () => this.syncPaymentLayer());
            this.syncPaymentLayer();

            this.syncCancelButton();

            if (this.terminalPhase === 'waiting' || this.terminalPhase === 'processing') {
                this.queueTerminalPoll();
            }
        },

        syncCancelButton() {
            const button = this.$root?.querySelector?.('[data-payment-workspace-cancel]');

            if (button) {
                const visible = this.terminalCancelUrl !== '';
                button.hidden = ! visible;
                button.style.display = visible ? '' : 'none';
            }
        },

        syncPaymentLayer() {
            const rail = this.$root?.closest?.('.ops-review-rail');

            rail?.classList.toggle('ops-review-rail--payment-open', this.open);

            const modal = this.$root?.querySelector?.('[data-payment-workspace-modal]');
            if (modal) {
                modal.style.setProperty('display', this.open ? 'flex' : 'none');
            }
        },

        transactionBound() {
            return paymentWorkspaceIsTransactionBound(this.terminalPhase);
        },

        openWorkspace() {
            if (! this.transactionBound()) {
                this.amount = this.suggestedAmount;
                this.amountTouched = false;
                this.paidOn = String(config.paidOn ?? '');
            }

            this.paidDateOpen = false;
            this.open = true;
            this.syncPaymentLayer();
        },

        pressAmount(key) {
            if (this.transactionBound()) {
                return;
            }

            const replace = ! this.amountTouched && key !== 'backspace';
            this.amount = nextPaymentAmount(this.amount, key, replace);
            this.amountTouched = true;
        },

        onAmountTyped(event) {
            if (this.transactionBound()) {
                return;
            }

            this.amountTouched = true;
            this.amount = normalizePaymentAmount(event?.target?.value ?? '');
        },

        pressAmountKey(event) {
            if (! this.open || this.transactionBound()) {
                return;
            }

            const tag = event.target?.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
                if (! event.target?.hasAttribute?.('data-payment-workspace-amount')) {
                    return;
                }
            }

            if (event.key === 'Backspace') {
                event.preventDefault();
                this.pressAmount('backspace');

                return;
            }

            if (event.key === '.' || event.key === 'Decimal') {
                event.preventDefault();
                this.pressAmount('.');

                return;
            }

            if (/^\d$/.test(event.key)) {
                event.preventDefault();
                this.pressAmount(event.key);
            }
        },

        requestClose(event) {
            if (this.transactionBound()) {
                event?.preventDefault?.();

                return;
            }

            this.paidDateOpen = false;
            this.open = false;
            this.syncPaymentLayer();
        },

        paidDateLabel() {
            return paymentPaidDateLabel(this.paidOn, this.paidDateToday, this.paidDateTodayLabel);
        },

        togglePaidDate() {
            if (this.transactionBound()) {
                return;
            }

            this.calendarMonth = paymentCalendarMonth(this.paidOn || this.paidDateToday);
            this.paidDateOpen = ! this.paidDateOpen;
        },

        closePaidDate() {
            this.paidDateOpen = false;
        },

        calendarTitle() {
            return paymentCalendarTitle(this.calendarMonth);
        },

        calendarCells() {
            return paymentCalendarCells(this.calendarMonth, this.paidDateToday, this.paidOn);
        },

        canShiftCalendar(delta) {
            return shiftPaymentCalendarMonth(this.calendarMonth, delta, this.paidDateToday) !== this.calendarMonth;
        },

        shiftCalendar(delta) {
            this.calendarMonth = shiftPaymentCalendarMonth(this.calendarMonth, delta, this.paidDateToday);
        },

        choosePaidDate(iso) {
            if (! iso || iso > this.paidDateToday) {
                return;
            }

            this.paidOn = iso === this.paidDateToday ? '' : iso;
            this.paidDateOpen = false;
        },

        amountCanSubmit() {
            const amount = Number.parseFloat(this.amount);

            return Number.isFinite(amount) && amount > 0;
        },

        isLedgerMethod() {
            return this.method === 'cash' || this.manualMethods.some((method) => method.key === this.method);
        },

        stampWorkspaceAmount(form) {
            const input = form?.querySelector?.('[name="amount"]');
            if (input) {
                input.value = this.amount;
            }

            const manual = form?.querySelector?.('[name="payment_method"]');
            if (manual && this.isLedgerMethod()) {
                manual.value = this.method;
            }

            const paidAt = form?.querySelector?.('[name="paid_at"]');
            if (paidAt) {
                paidAt.value = this.paidOn && this.paidOn !== this.paidDateToday ? this.paidOn : '';
            }
        },

        destroy() {
            this._terminalDestroyed = true;
            this.clearTerminalTimer();
            this.$root?.closest?.('.ops-review-rail')?.classList.remove('ops-review-rail--payment-open');
        },

        showTerminal(view) {
            if (! view) {
                this.clearTerminal();

                return;
            }

            this.terminalPhase = view.phase;
            this.terminalHeadline = view.headline;
            this.terminalNote = view.note;
            this.terminalDetail = view.detail;
            if (view.phase !== 'waiting' && view.phase !== 'processing') {
                this._cancelNotice = '';
            } else if (this._cancelNotice) {
                this.terminalDetail = this._cancelNotice;
            }
            this.terminalAmount = view.amount;
            this.terminalSpinning = view.spinning;
            this.terminalBlocksForm = view.blocksForm;
            this.terminalRefreshUrl = view.refreshUrl ?? '';
            this.terminalCancelUrl = view.cancelUrl ?? '';
            this.syncCancelButton();
            if (this.workspace && view.phase) {
                this.open = true;
                this.syncPaymentLayer();
            }
        },

        clearTerminal() {
            this.terminalPhase = '';
            this.terminalHeadline = '';
            this.terminalNote = '';
            this.terminalDetail = '';
            this.terminalAmount = '';
            this.terminalSpinning = false;
            this.terminalBlocksForm = false;
            this.terminalRefreshUrl = '';
            this.terminalCancelUrl = '';
            this._cancelNotice = '';
            this.syncCancelButton();
        },

        clearTerminalTimer() {
            if (this._terminalTimer) {
                clearTimeout(this._terminalTimer);
                this._terminalTimer = null;
            }
        },

        queueTerminalPoll() {
            if (this._terminalDestroyed) {
                return;
            }

            if (this.terminalPhase !== 'waiting' && this.terminalPhase !== 'processing') {
                return;
            }

            this.clearTerminalTimer();
            this._terminalTimer = setTimeout(() => {
                this.pollTerminal();
            }, 1500);
        },

        async pollTerminal() {
            if (this._terminalDestroyed || ! this.terminalRefreshUrl) {
                return;
            }

            try {
                const response = await fetch(this.terminalRefreshUrl, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                });

                if (this._terminalDestroyed) {
                    return;
                }

                if (! response.ok) {
                    this.queueTerminalPoll();

                    return;
                }

                const body = await response.json();
                const next = applyTerminalPayment(body?.terminal);

                if (! next) {
                    this.queueTerminalPoll();

                    return;
                }

                const stillOpen = next.phase === 'waiting' || next.phase === 'processing';
                this.showTerminal(next);

                if (stillOpen) {
                    this.queueTerminalPoll();

                    return;
                }

                this.clearTerminalTimer();

                if (next.phase === 'reconciliation') {
                    return;
                }

                await new Promise((resolve) => setTimeout(resolve, 1200));

                if (! this._terminalDestroyed) {
                    await this.refreshTerminalRail();
                }
            } catch {
                if (! this._terminalDestroyed) {
                    this.queueTerminalPoll();
                }
            }
        },

        async cancelTerminal() {
            const url = this.terminalCancelUrl;

            if (url === '' || this.busy) {
                return;
            }

            this.clearTerminalTimer();
            this.busy = true;

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                });
                const body = await response.json().catch(() => null);
                const next = applyTerminalPayment(body?.terminal);

                if (response.ok && next?.phase === 'canceled') {
                    this.showTerminal(next);
                    await new Promise((resolve) => setTimeout(resolve, 900));

                    if (! this._terminalDestroyed && this.terminalPhase === 'canceled') {
                        await this.restorePaymentWorkspace();
                    }

                    return;
                }

                this._cancelNotice = body?.message || 'The reader could not be canceled. The payment is still with the customer.';

                if (next) {
                    this.showTerminal(next);
                }

                this.terminalDetail = this._cancelNotice;

                this.queueTerminalPoll();
            } catch {
                this.queueTerminalPoll();
            } finally {
                this.busy = false;
            }
        },

        async restorePaymentWorkspace() {
            const current = this.$root;
            const formsPresent = current?.querySelector?.('[data-payment-workspace-keypad]');

            if (formsPresent) {
                this.clearTerminal();

                return;
            }

            if (! current?.isConnected) {
                return;
            }

            const response = await fetch(window.location.href, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'text/html' },
            });

            if (! response.ok) {
                this.clearTerminal();

                return;
            }

            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const fresh = doc.querySelector('[data-payment-workspace]');

            if (! fresh || ! current.isConnected) {
                this.clearTerminal();

                return;
            }

            window.Alpine?.destroyTree?.(current);
            const next = fresh.cloneNode(true);
            current.replaceWith(next);
            window.Alpine?.initTree?.(next);
        },

        async refreshTerminalRail() {
            const panel = this.$root?.querySelector?.('[data-terminal-payment]') ?? this.$root;
            let el = this.$root;

            while (el) {
                const data = window.Alpine?.$data?.(el);

                if (data && typeof data.refreshWorksheet === 'function') {
                    await data.refreshWorksheet(window.location.href, panel);

                    return;
                }

                el = el.parentElement;
            }
        },

        async openKeyed() {
            this.method = 'keyed';
            this.cardError = '';
            if (this.publicConfig) {
                await this.$nextTick();
                await this.initializeCard();
            }
        },

        async initializeCard() {
            if (! this.publicConfig?.application_id || ! this.publicConfig?.location_id) {
                this.cardError = 'Card entry is not configured.';
                return;
            }

            try {
                const Square = await loadSquareSdk(
                    this.publicConfig.web_payments_sdk_url
                        || 'https://sandbox.web.squarecdn.com/v1/square.js',
                );
                const payments = Square.payments(
                    this.publicConfig.application_id,
                    this.publicConfig.location_id,
                );
                if (this.card) {
                    try {
                        await this.card.destroy();
                    } catch {
                        // ignore
                    }
                    this.card = null;
                }
                this.card = await payments.card();
                await this.card.attach(`#${this.cardContainerId}`);
                this.cardReady = true;
            } catch (e) {
                this.cardError = 'Card form could not load.';
                this.cardReady = false;
            }
        },

        async submitWorkspace(event) {
            const form = event?.target instanceof HTMLFormElement
                ? event.target
                : event?.target?.closest?.('form');

            this.stampWorkspaceAmount(form);

            if (this.method === 'terminal' || this.method === 'keyed') {
                await this.prepareAndSubmit(event);

                return;
            }

            const confirmed = form?.querySelector?.('[name="deposit_confirmed"]');
            if (confirmed) {
                confirmed.value = '1';
            }

            this.busy = true;

            try {
                let el = event?.target;
                while (el) {
                    const data = window.Alpine?.$data?.(el);
                    if (data && data !== this && typeof data.submitWorksheetForm === 'function') {
                        const result = await data.submitWorksheetForm(event);
                        if (result) {
                            this.open = false;
                        }

                        return;
                    }
                    el = el.parentElement;
                }

                form?.submit();
            } finally {
                this.busy = false;
            }
        },

        async submitTerminalCapture(form) {
            if (! form?.action) {
                this.clearTerminal();
                this.cardError = 'The reader did not take the payment.';

                return;
            }

            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
            });
            const body = await response.json().catch(() => null);
            const next = applyTerminalPayment(body?.terminal);

            if (! response.ok || ! next) {
                this.clearTerminal();
                this.cardError = body?.message || 'The reader did not take the payment.';

                return;
            }

            this.showTerminal(next);

            if (next.phase === 'waiting' || next.phase === 'processing') {
                this.queueTerminalPoll();

                return;
            }

            if (next.phase === 'reconciliation') {
                return;
            }

            await new Promise((resolve) => setTimeout(resolve, 1200));

            if (! this._terminalDestroyed) {
                this.clearTerminal();
                await this.refreshTerminalRail();
            }
        },

        async prepareAndSubmit(event) {
            this.busy = true;
            this.cardError = '';
            const form = event?.target instanceof HTMLFormElement
                ? event.target
                : event?.target?.closest?.('form');

            if (this.method === 'terminal') {
                this.showTerminal(presentingTerminalPayment(form?.querySelector?.('input[name="amount"]')?.value ?? ''));
            }

            try {
                if (this.method === 'keyed' && this.publicConfig) {
                    if (! this.cardReady || ! this.card) {
                        await this.initializeCard();
                    }
                    if (! this.card) {
                        this.cardError = 'Card form is not ready.';
                        this.busy = false;
                        return;
                    }
                    const tokenResult = await this.card.tokenize();
                    if (tokenResult.status !== 'OK' || ! tokenResult.token) {
                        this.cardError = tokenResult.errors?.[0]?.message || 'Card could not be tokenized.';
                        this.busy = false;
                        return;
                    }
                    this.sourceToken = tokenResult.token;
                } else if (this.method === 'keyed' && ! this.publicConfig) {
                    // Stub / non-Square transport: placeholder token for local seam tests only.
                    this.sourceToken = this.sourceToken || 'tok_stub_phase1';
                } else {
                    this.sourceToken = '';
                }

                stampPaymentCaptureFields(form, this);

                if (this.method === 'terminal') {
                    await this.submitTerminalCapture(form);

                    return;
                }

                // Walk up to the RO worksheet Alpine scope (nested x-data).
                let el = event.target;
                let submitted = false;
                while (el) {
                    const data = window.Alpine?.$data?.(el);
                    if (data && typeof data.submitWorksheetForm === 'function') {
                        await data.submitWorksheetForm(event);
                        submitted = true;
                        break;
                    }
                    el = el.parentElement;
                }
                if (! submitted) {
                    event.target.submit();
                }
            } catch {
                this.cardError = 'Payment could not start.';
                if (this.method === 'terminal') {
                    this.clearTerminal();
                }
            } finally {
                this.busy = false;
            }
        },
    };
}
