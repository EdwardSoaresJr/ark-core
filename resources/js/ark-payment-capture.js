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
 * Take Payment rail - Terminal or Square Web Payments tokenized keyed entry.
 * Core never sees PAN/CVV; only source_token from Square.js.
 */
export function arkPaymentCapture(config = {}) {
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

        async prepareAndSubmit(event) {
            this.busy = true;
            this.cardError = '';

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
            } finally {
                this.busy = false;
            }
        },
    };
}
