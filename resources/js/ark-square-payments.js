function loadSquareSdk(url) {
    return new Promise((resolve, reject) => {
        if (window.Square) {
            resolve(window.Square);

            return;
        }

        const existing = document.querySelector('script[data-ark-square-sdk]');

        if (existing) {
            existing.addEventListener('load', () => resolve(window.Square));
            existing.addEventListener('error', reject);

            return;
        }

        const script = document.createElement('script');
        script.src = url;
        script.async = true;
        script.dataset.arkSquareSdk = '1';
        script.onload = () => resolve(window.Square);
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

export function arkSquarePayments(config = {}) {
    const repairOrderId = config.repairOrderId;
    const estimateVersion = config.estimateVersion ?? '';
    const initiateUrl = config.initiateUrl ?? '';
    const pollUrlTemplate = config.pollUrlTemplate ?? '';
    const completeUrlTemplate = config.completeUrlTemplate ?? '';
    const cancelUrlTemplate = config.cancelUrlTemplate ?? '';
    const square = config.square ?? {};
    const balanceDueDecimal = config.balanceDueDecimal ?? '';
    const intent = config.intent === 'deposit' ? 'deposit' : 'payment';
    const cardContainerSelector = config.cardContainerSelector ?? '#ark-square-card-container';
    const isDeposit = intent === 'deposit';
    const labels = {
        startError: isDeposit ? 'Square deposit could not be started.' : 'Square payment could not be started.',
        unavailable: isDeposit ? 'Square deposit is unavailable right now.' : 'Square payment is unavailable right now.',
        recorded: isDeposit ? 'Square deposit recorded.' : 'Square payment recorded.',
        incomplete: isDeposit ? 'Square deposit did not complete.' : 'Square payment did not complete.',
        keyedFailed: isDeposit ? 'Square deposit failed.' : 'Square payment failed.',
        keyedUnavailable: isDeposit ? 'Square deposit could not be completed.' : 'Square payment could not be completed.',
    };

    return {
        amount: balanceDueDecimal,
        message: '',
        error: '',
        busy: false,
        polling: false,
        pollTimer: null,
        attempt: null,
        showKeyedForm: false,
        card: null,
        cardReady: false,

        async chargeTerminal() {
            await this.startAttempt('terminal');
        },

        async openKeyedForm() {
            this.showKeyedForm = true;
            this.error = '';
            this.message = '';

            if (! this.attempt || this.attempt.capture_surface !== 'keyed') {
                await this.startAttempt('keyed');
            }

            await this.$nextTick();
            await this.initializeCard();
        },

        closeKeyedForm() {
            this.showKeyedForm = false;
            this.error = '';
        },

        async startAttempt(surface) {
            this.busy = true;
            this.error = '';
            this.message = '';

            try {
                const response = await fetch(initiateUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        capture_surface: surface,
                        amount: this.amount,
                        opened_estimate_version: estimateVersion,
                    }),
                });

                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    this.error = payload.message || labels.startError;

                    return;
                }

                this.attempt = payload.attempt ?? null;
                this.message = payload.message || '';

                if (surface === 'terminal' && this.attempt?.id) {
                    this.startPolling(this.attempt.id);
                }
            } catch {
                this.error = labels.unavailable;
            } finally {
                this.busy = false;
            }
        },

        startPolling(attemptId) {
            this.stopPolling();
            this.polling = true;
            this.pollAttempt(attemptId);
            this.pollTimer = window.setInterval(() => this.pollAttempt(attemptId), 2000);
        },

        stopPolling() {
            this.polling = false;

            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },

        async pollAttempt(attemptId) {
            const url = pollUrlTemplate.replace('__ATTEMPT__', String(attemptId));

            try {
                const response = await fetch(url, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const payload = await response.json().catch(() => ({}));
                this.attempt = payload.attempt ?? this.attempt;

                if (this.attempt?.status === 'completed') {
                    this.stopPolling();
                    this.message = labels.recorded;
                    await this.refreshFinancialRail();
                }

                if (this.attempt?.status === 'failed' || this.attempt?.status === 'canceled') {
                    this.stopPolling();
                    this.error = this.attempt.failure_reason || labels.incomplete;
                }
            } catch {
                this.error = 'Unable to check Square reader status.';
                this.stopPolling();
            }
        },

        async cancelAttempt() {
            if (! this.attempt?.id) {
                return;
            }

            const url = cancelUrlTemplate.replace('__ATTEMPT__', String(this.attempt.id));

            try {
                await fetch(url, {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
            } finally {
                this.stopPolling();
                this.attempt = null;
                this.message = '';
            }
        },

        async initializeCard() {
            if (this.cardReady || this.card) {
                return;
            }

            if (! square.applicationId || ! square.locationId) {
                this.error = 'Square card entry is not configured.';

                return;
            }

            try {
                const payments = await loadSquareSdk(square.webPaymentsSdkUrl);
                const squareClient = payments.payments(square.applicationId, square.locationId);
                this.card = await squareClient.card();
                await this.card.attach(cardContainerSelector);
                this.cardReady = true;
            } catch {
                this.error = 'Square card form could not be loaded.';
            }
        },

        async submitKeyedPayment() {
            if (! this.card || ! this.attempt?.id) {
                return;
            }

            this.busy = true;
            this.error = '';

            try {
                const tokenResult = await this.card.tokenize();

                if (tokenResult.status !== 'OK') {
                    this.error = tokenResult.errors?.[0]?.message || 'Card could not be tokenized.';

                    return;
                }

                const url = completeUrlTemplate.replace('__ATTEMPT__', String(this.attempt.id));
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        source_id: tokenResult.token,
                        opened_estimate_version: estimateVersion,
                    }),
                });

                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    this.error = payload.message || labels.keyedFailed;

                    return;
                }

                this.attempt = payload.attempt ?? this.attempt;
                this.message = payload.message || labels.recorded;
                this.showKeyedForm = false;
                await this.refreshFinancialRail();
            } catch {
                this.error = labels.keyedUnavailable;
            } finally {
                this.busy = false;
            }
        },

        async refreshFinancialRail() {
            const root = this.$root.closest('[data-worksheet-root]');
            const worksheet = root?._x_dataStack?.[0];

            if (typeof worksheet?.refreshScope === 'function') {
                await worksheet.refreshScope('rail');

                return;
            }

            window.location.reload();
        },
    };
}

export function arkPortalInvoicePay(config = {}) {
    const initiateUrl = config.initiateUrl ?? '';
    const completeUrlTemplate = config.completeUrlTemplate ?? '';
    const square = config.square ?? {};

    return {
        attempt: null,
        message: '',
        error: '',
        busy: false,
        card: null,
        cardReady: false,
        cardMounting: false,

        async boot() {
            await this.startAttempt();
            await this.$nextTick();
            await this.initializeCard();
        },

        async startAttempt() {
            this.busy = true;

            try {
                const response = await fetch(initiateUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({}),
                });

                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    this.error = payload.message || 'Payment could not be started.';

                    return;
                }

                this.attempt = payload.attempt ?? null;
            } catch {
                this.error = 'Online payment is unavailable right now.';
            } finally {
                this.busy = false;
            }
        },

        async initializeCard() {
            if (this.cardMounting || this.cardReady || this.card) {
                return;
            }

            this.cardMounting = true;

            if (! square.applicationId || ! square.locationId) {
                this.error = 'Online card payment is not configured.';
                this.cardMounting = false;

                return;
            }

            const mount = this.$refs.cardMount;

            if (! mount) {
                this.cardMounting = false;

                return;
            }

            try {
                const payments = await loadSquareSdk(square.webPaymentsSdkUrl);
                const squareClient = payments.payments(square.applicationId, square.locationId);
                this.card = await squareClient.card();
                await this.card.attach(mount);
                this.cardReady = true;
            } catch {
                this.error = 'Card form could not be loaded.';
            } finally {
                this.cardMounting = false;
            }
        },

        async submitPayment() {
            if (! this.card || ! this.attempt?.id) {
                return;
            }

            this.busy = true;
            this.error = '';

            try {
                const tokenResult = await this.card.tokenize();

                if (tokenResult.status !== 'OK') {
                    this.error = tokenResult.errors?.[0]?.message || 'Card could not be verified.';

                    return;
                }

                const url = completeUrlTemplate.replace('__ATTEMPT__', String(this.attempt.id));
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        source_id: tokenResult.token,
                    }),
                });

                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    this.error = payload.message || 'Payment failed.';

                    return;
                }

                this.message = payload.message || 'Thank you — we received your payment.';
            } catch {
                this.error = 'Payment could not be completed.';
            } finally {
                this.busy = false;
            }
        },
    };
}

export function arkPortalEstimateDeposit(config = {}) {
    const initiateUrl = config.initiateUrl ?? '';
    const completeUrlTemplate = config.completeUrlTemplate ?? '';
    const approvalId = config.approvalId ?? null;
    const square = config.square ?? {};

    return {
        attempt: null,
        message: '',
        error: '',
        busy: false,
        card: null,
        cardReady: false,
        cardMounting: false,

        async boot() {
            await this.startAttempt();
            await this.$nextTick();
            await this.initializeCard();
        },

        async startAttempt() {
            if (! approvalId) {
                this.error = 'Authorization is required before collecting a deposit.';

                return;
            }

            this.busy = true;

            try {
                const response = await fetch(initiateUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        approval_id: approvalId,
                    }),
                });

                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    this.error = payload.message || 'Deposit could not be started.';

                    return;
                }

                this.attempt = payload.attempt ?? null;
            } catch {
                this.error = 'Online deposit is unavailable right now.';
            } finally {
                this.busy = false;
            }
        },

        async initializeCard() {
            if (this.cardMounting || this.cardReady || this.card) {
                return;
            }

            this.cardMounting = true;

            if (! square.applicationId || ! square.locationId) {
                this.error = 'Online card payment is not configured.';
                this.cardMounting = false;

                return;
            }

            const mount = this.$refs.cardMount;

            if (! mount) {
                this.cardMounting = false;

                return;
            }

            try {
                const payments = await loadSquareSdk(square.webPaymentsSdkUrl);
                const squareClient = payments.payments(square.applicationId, square.locationId);
                this.card = await squareClient.card();
                await this.card.attach(mount);
                this.cardReady = true;
            } catch {
                this.error = 'Card form could not be loaded.';
            } finally {
                this.cardMounting = false;
            }
        },

        async submitPayment() {
            if (! this.card || ! this.attempt?.id) {
                return;
            }

            this.busy = true;
            this.error = '';

            try {
                const tokenResult = await this.card.tokenize();

                if (tokenResult.status !== 'OK') {
                    this.error = tokenResult.errors?.[0]?.message || 'Card could not be verified.';

                    return;
                }

                const url = completeUrlTemplate.replace('__ATTEMPT__', String(this.attempt.id));
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        source_id: tokenResult.token,
                    }),
                });

                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    this.error = payload.message || 'Deposit failed.';

                    return;
                }

                this.message = payload.message || 'Thank you — we received your deposit.';
            } catch {
                this.error = 'Deposit could not be completed.';
            } finally {
                this.busy = false;
            }
        },
    };
}
