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

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function isWaitingStatus(status) {
    return status === 'pending' || status === 'accepted';
}

/**
 * Take Payment rail — Terminal or Square Web Payments tokenized manual entry.
 * Does not go through the worksheet save overlay.
 */
export function arkPaymentCapture(config = {}) {
    return {
        publicConfig: config.publicConfig ?? null,
        method: config.defaultMethod ?? 'keyed',
        deviceRef: config.defaultDevice ?? '',
        sourceToken: '',
        cardContainerId: config.cardContainerId ?? 'ark-payment-capture-card',
        refreshUrlTemplate: config.refreshUrlTemplate ?? '',
        cancelUrlTemplate: config.cancelUrlTemplate ?? '',
        card: null,
        cardReady: false,
        cardError: '',
        statusMessage: '',
        busy: false,
        waitingOnTerminal: false,
        openAttemptId: config.openAttemptId ?? null,
        pollTimer: null,

        init() {
            if (this.openAttemptId) {
                this.waitingOnTerminal = true;
                this.statusMessage = 'Sent to the terminal.';
                this.startPolling(this.openAttemptId);
            }
        },

        destroy() {
            this.stopPolling();
        },

        attemptUrl(template, attemptId) {
            return String(template || '').replace('__ID__', String(attemptId));
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
                this.cardError = 'Manual entry is not configured.';
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

        async prepareAndSubmit(event) {
            if (this.busy || this.waitingOnTerminal) {
                return;
            }

            this.busy = true;
            this.cardError = '';
            this.statusMessage = '';

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
                    this.sourceToken = this.sourceToken || 'tok_stub_phase1';
                } else {
                    this.sourceToken = '';
                }

                const form = event.target;
                const body = new FormData(form);
                body.set('capture_method', this.method);
                body.set('device_ref', this.method === 'terminal' ? this.deviceRef : '');
                body.set('source_token', this.sourceToken);

                const response = await fetch(form.action, {
                    method: 'POST',
                    body,
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': body.get('_token') || csrfToken(),
                    },
                });

                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    this.cardError = payload.message
                        || payload.errors?.capture?.[0]
                        || 'Payment could not start.';
                    return;
                }

                const status = payload.attempt?.status ?? '';
                this.openAttemptId = payload.attempt?.id ?? null;
                this.statusMessage = payload.message || '';

                if (this.method === 'terminal' && isWaitingStatus(status) && this.openAttemptId) {
                    this.waitingOnTerminal = true;
                    this.statusMessage = payload.message || 'Sent to the terminal.';
                    this.startPolling(this.openAttemptId);
                } else {
                    this.waitingOnTerminal = false;
                    this.stopPolling();
                }

                await this.refreshFinancialRail();
            } catch {
                this.cardError = 'Payment could not start.';
            } finally {
                this.busy = false;
            }
        },

        startPolling(attemptId) {
            this.stopPolling();
            this.pollTimer = window.setInterval(() => {
                this.checkStatus(attemptId, { quiet: true });
            }, 2500);
        },

        stopPolling() {
            if (this.pollTimer !== null) {
                window.clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },

        async checkStatus(attemptId, options = {}) {
            const url = this.attemptUrl(this.refreshUrlTemplate, attemptId);
            if (! url) {
                return;
            }

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                });
                const payload = await response.json().catch(() => ({}));
                const status = payload.attempt?.status ?? '';

                if (! isWaitingStatus(status)) {
                    this.stopPolling();
                    this.waitingOnTerminal = false;
                    this.openAttemptId = null;
                    this.statusMessage = payload.message || '';
                    await this.refreshFinancialRail();
                    return;
                }

                if (! options.quiet) {
                    this.statusMessage = payload.message || 'Still waiting on the terminal.';
                }
            } catch {
                if (! options.quiet) {
                    this.cardError = 'Could not check payment status.';
                }
            }
        },

        async cancelAttempt(attemptId) {
            const url = this.attemptUrl(this.cancelUrlTemplate, attemptId);
            if (! url || this.busy) {
                return;
            }

            this.busy = true;
            this.cardError = '';

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                });
                const payload = await response.json().catch(() => ({}));
                const status = payload.attempt?.status ?? '';

                if (! response.ok) {
                    this.cardError = payload.message || 'Could not cancel the payment request.';
                    return;
                }

                if (! isWaitingStatus(status)) {
                    this.stopPolling();
                    this.waitingOnTerminal = false;
                    this.openAttemptId = null;
                }

                this.statusMessage = payload.message || 'Payment request cancelled.';
                await this.refreshFinancialRail();
            } catch {
                this.cardError = 'Could not cancel the payment request.';
            } finally {
                this.busy = false;
            }
        },

        async refreshFinancialRail() {
            let el = this.$el;

            while (el) {
                const data = window.Alpine?.$data?.(el);

                if (data && typeof data.refreshScope === 'function') {
                    await data.refreshScope('rail', { quiet: true });
                    return;
                }

                el = el.parentElement;
            }
        },
    };
}
