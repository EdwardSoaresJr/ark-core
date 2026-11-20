import { loadSquareSdk } from './ark-payment-capture';

/**
 * Staff AR receive-payment Alpine scope.
 * Production keyed capture mounts Square Web Payments SDK and tokenizes before submit.
 * Local/testing may fall back to a stub token when Platform public config is absent.
 */
export function arReceivePayment(config = {}) {
    const invoices = config.invoices || [];

    return {
        paymentAmount: '',
        method: config.initialMethod || 'check',
        keyedEnabled: !!config.keyedEnabled,
        terminalEnabled: !!config.terminalEnabled,
        publicConfig: config.publicConfig || null,
        cardContainerId: config.cardContainerId || 'ar-receive-payment-card',
        allowStubToken: !!config.allowStubToken,
        stubToken: config.stubToken || 'tok_stub_ar',
        storeCreditCents: Number(config.storeCreditCents || 0),
        dispositionDefault: config.dispositionDefault || 'ask',
        disposition: config.initialDisposition || '',
        sourceToken: '',
        cardError: '',
        card: null,
        cardReady: false,
        busy: false,
        rows: invoices.map((invoice) => ({
            invoice_id: invoice.invoice_id,
            balance_cents: invoice.balance_cents,
            selected: false,
            amount: '',
        })),
        isCash() {
            return this.method === 'cash';
        },
        isStoreCredit() {
            return this.method === 'store_credit';
        },
        isCardMethod() {
            return this.method === 'ark_card_keyed' || this.method === 'ark_card_terminal';
        },
        cardCaptureMethod() {
            return this.method === 'ark_card_terminal' ? 'terminal' : 'keyed';
        },
        allowsKeepCredit() {
            return ! this.isCardMethod() && ! this.isStoreCredit();
        },
        allowsReturnChange() {
            return this.isCash();
        },
        allowsRefund() {
            return ! this.isCash() && ! this.isCardMethod() && ! this.isStoreCredit();
        },
        showsExcess() {
            return ! this.isCardMethod() && ! this.isStoreCredit() && this.remainingCents() > 0;
        },
        init() {
            this.$watch('method', async (value) => {
                if (value === 'ark_card_keyed') {
                    this.cardError = '';
                    if (this.publicConfig) {
                        await this.$nextTick();
                        await this.initializeCard();
                    } else if (this.allowStubToken && ! this.sourceToken) {
                        this.sourceToken = this.stubToken;
                    }
                } else {
                    this.sourceToken = '';
                    await this.destroyCard();
                }
                if (value === 'store_credit' && this.storeCreditCents > 0) {
                    this.paymentAmount = (this.storeCreditCents / 100).toFixed(2);
                    this.autoAllocate();
                }
                this.syncDispositionForMethod();
            });
            this.syncDispositionForMethod();
            if (this.isStoreCredit() && this.storeCreditCents > 0 && ! this.paymentAmount) {
                this.paymentAmount = (this.storeCreditCents / 100).toFixed(2);
                this.autoAllocate();
            }
        },
        async destroyCard() {
            if (! this.card) {
                return;
            }
            try {
                await this.card.destroy();
            } catch {
                // ignore
            }
            this.card = null;
            this.cardReady = false;
        },
        async initializeCard() {
            if (! this.publicConfig?.application_id || ! this.publicConfig?.location_id) {
                this.cardError = 'Card entry is not configured.';
                this.cardReady = false;
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
                await this.destroyCard();
                this.card = await payments.card();
                await this.card.attach(`#${this.cardContainerId}`);
                this.cardReady = true;
                this.cardError = '';
            } catch {
                this.cardError = 'Card form could not load.';
                this.cardReady = false;
            }
        },
        async prepareKeyedSubmit(event) {
            if (this.method !== 'ark_card_keyed') {
                return true;
            }

            this.busy = true;
            this.cardError = '';

            try {
                if (this.publicConfig) {
                    if (! this.cardReady || ! this.card) {
                        await this.initializeCard();
                    }
                    if (! this.card) {
                        this.cardError = 'Card form is not ready.';
                        return false;
                    }
                    const tokenResult = await this.card.tokenize();
                    if (tokenResult.status !== 'OK' || ! tokenResult.token) {
                        this.cardError = tokenResult.errors?.[0]?.message || 'Card could not be tokenized.';
                        return false;
                    }
                    this.sourceToken = tokenResult.token;
                } else if (this.allowStubToken) {
                    this.sourceToken = this.sourceToken || this.stubToken;
                } else {
                    this.cardError = 'Card entry is not configured.';
                    return false;
                }

                const form = event?.target instanceof HTMLFormElement
                    ? event.target
                    : event?.target?.closest?.('form');
                const input = form?.querySelector?.('input[name="source_token"]');
                if (input) {
                    input.value = this.sourceToken;
                    input.disabled = false;
                }

                return true;
            } finally {
                this.busy = false;
            }
        },
        async onSubmit(event) {
            if (! this.isCardMethod() || this.method !== 'ark_card_keyed') {
                return;
            }
            const ok = await this.prepareKeyedSubmit(event);
            if (! ok) {
                event.preventDefault();
            }
        },
        syncDispositionForMethod() {
            if (this.isCardMethod() || this.isStoreCredit()) {
                this.disposition = '';
                return;
            }
            const allowed = this.isCash()
                ? ['keep_credit', 'return_change']
                : ['keep_credit', 'refund'];
            if (this.disposition && ! allowed.includes(this.disposition)) {
                this.disposition = '';
            }
            if (! this.disposition && this.dispositionDefault !== 'ask' && allowed.includes(this.dispositionDefault)) {
                this.disposition = this.dispositionDefault;
            }
        },
        remainingCents() {
            if (this.isCardMethod()) {
                return 0;
            }
            const payment = Math.round(parseFloat(this.paymentAmount || '0') * 100);
            return (Number.isFinite(payment) ? payment : 0) - this.allocatedCents();
        },
        autoAllocate() {
            if (this.isCardMethod()) {
                return;
            }
            let remaining = Math.round(parseFloat(this.paymentAmount || '0') * 100);
            if (! Number.isFinite(remaining) || remaining <= 0) {
                this.rows.forEach((row) => { row.selected = false; row.amount = ''; });
                return;
            }

            this.rows.forEach((row) => {
                if (remaining <= 0) {
                    row.selected = false;
                    row.amount = '';
                    return;
                }

                const apply = Math.min(row.balance_cents, remaining);
                row.selected = apply > 0;
                row.amount = apply > 0 ? (apply / 100).toFixed(2) : '';
                remaining -= apply;
            });
        },
        syncSelected(index) {
            const row = this.rows[index];
            if (! row.selected) {
                row.amount = '';
                return;
            }
            if (! row.amount) {
                row.amount = (row.balance_cents / 100).toFixed(2);
            }
        },
        allocatedCents() {
            return this.rows.reduce((sum, row) => {
                if (! row.selected) return sum;
                const cents = Math.round(parseFloat(row.amount || '0') * 100);
                return sum + (Number.isFinite(cents) && cents > 0 ? cents : 0);
            }, 0);
        },
        allocatedDollars() {
            return (this.allocatedCents() / 100).toFixed(2);
        },
        paymentDollars() {
            const payment = Math.round(parseFloat(this.paymentAmount || '0') * 100);
            return ((Number.isFinite(payment) ? payment : 0) / 100).toFixed(2);
        },
        remainingDollars() {
            return (Math.max(0, this.remainingCents()) / 100).toFixed(2);
        },
        excessLabel() {
            if (this.disposition === 'keep_credit') return ' (account credit)';
            if (this.disposition === 'return_change') return ' (cash change)';
            if (this.disposition === 'refund') return ' (pending refund)';
            return '';
        },
        previewCopy() {
            const allocated = this.allocatedDollars();
            const excess = this.remainingDollars();
            if (this.disposition === 'keep_credit') {
                return '$' + allocated + ' pays the invoice. $' + excess + ' remains available toward future services.';
            }
            if (this.disposition === 'return_change') {
                return '$' + allocated + ' pays the invoice. $' + excess + ' is returned as cash change.';
            }
            if (this.disposition === 'refund') {
                return '$' + allocated + ' pays the invoice. $' + excess + ' is held as a pending refund/return until confirmed — not treated as returned yet.';
            }
            return 'Choose how to handle the $' + excess + ' excess before recording the payment.';
        },
    };
}

/**
 * Portal multi-invoice pay keyed capture.
 */
export function portalAccountPay(config = {}) {
    return {
        sourceToken: '',
        publicConfig: config.publicConfig || null,
        cardContainerId: config.cardContainerId || 'portal-ar-card',
        allowStubToken: !!config.allowStubToken,
        stubToken: config.stubToken || 'tok_stub_portal_ar',
        cardError: '',
        card: null,
        cardReady: false,
        busy: false,
        rows: (config.rows || []).map((row) => ({ ...row })),
        async init() {
            if (this.publicConfig) {
                await this.$nextTick();
                await this.initializeCard();
            } else if (this.allowStubToken) {
                this.sourceToken = this.stubToken;
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
                }
                this.card = await payments.card();
                await this.card.attach(`#${this.cardContainerId}`);
                this.cardReady = true;
            } catch {
                this.cardError = 'Card form could not load.';
                this.cardReady = false;
            }
        },
        async onSubmit(event) {
            this.busy = true;
            this.cardError = '';
            try {
                if (this.publicConfig) {
                    if (! this.cardReady || ! this.card) {
                        await this.initializeCard();
                    }
                    if (! this.card) {
                        this.cardError = 'Card form is not ready.';
                        event.preventDefault();
                        return;
                    }
                    const tokenResult = await this.card.tokenize();
                    if (tokenResult.status !== 'OK' || ! tokenResult.token) {
                        this.cardError = tokenResult.errors?.[0]?.message || 'Card could not be tokenized.';
                        event.preventDefault();
                        return;
                    }
                    this.sourceToken = tokenResult.token;
                } else if (this.allowStubToken) {
                    this.sourceToken = this.sourceToken || this.stubToken;
                } else {
                    this.cardError = 'Card entry is not configured.';
                    event.preventDefault();
                    return;
                }

                const form = event.target;
                const input = form?.querySelector?.('input[name="source_token"]');
                if (input) {
                    input.value = this.sourceToken;
                }
            } finally {
                this.busy = false;
            }
        },
    };
}
