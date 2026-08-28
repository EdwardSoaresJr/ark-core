/**
 * US phone display formatting for the public lead intake form.
 * Storage authority remains PhoneNumber::normalize() on the server.
 */

export function usPhoneDigits(value) {
    let digits = String(value ?? '').replace(/\D/g, '');

    if (digits.length === 11 && digits.startsWith('1')) {
        digits = digits.slice(1);
    }

    return digits.slice(0, 10);
}

export function formatUsPhoneDisplay(value) {
    const digits = usPhoneDigits(value);

    if (digits.length === 0) {
        return '';
    }

    if (digits.length <= 3) {
        return `(${digits}`;
    }

    if (digits.length <= 6) {
        return `(${digits.slice(0, 3)}) ${digits.slice(3)}`;
    }

    return `(${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6)}`;
}

function phoneFieldMixin(initial = '') {
    return {
        phone: formatUsPhoneDisplay(initial),

        formatPhone() {
            this.phone = formatUsPhoneDisplay(this.phone);
        },

        phoneDigitCount() {
            return usPhoneDigits(this.phone).length;
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('publicLeadPhoneField', (initial = '') => ({
        ...phoneFieldMixin(initial),

        init() {
            this.phone = formatUsPhoneDisplay(this.phone);
        },
    }));

    window.Alpine.data('publicLeadPhoneVerify', (config) => ({
        ...phoneFieldMixin(config.initialPhone ?? ''),

        code: '',
        codeSent: false,
        verified: false,
        sending: false,
        checking: false,
        error: '',
        trustedPhoneDigits: usPhoneDigits(config.trustedPhone ?? ''),

        init() {
            this.phone = formatUsPhoneDisplay(this.phone);
            this.syncTrustedVerification();
        },

        formatPhone() {
            this.phone = formatUsPhoneDisplay(this.phone);
            this.syncTrustedVerification();
        },

        syncTrustedVerification() {
            if (this.trustedPhoneDigits.length !== 10) {
                return;
            }

            if (this.isTrustedPhone()) {
                this.verified = true;
                this.error = '';
                this.codeSent = false;
            } else if (this.verified && ! this.codeSent) {
                // Lost account-phone trust after editing — require a fresh code.
                this.verified = false;
            }
        },

        isTrustedPhone() {
            return this.trustedPhoneDigits.length === 10
                && this.phoneDigitCount() === 10
                && usPhoneDigits(this.phone) === this.trustedPhoneDigits;
        },

        canSendCode() {
            return this.phoneDigitCount() >= 10;
        },

        canCheckCode() {
            return this.canSendCode() && String(this.code ?? '').trim().length >= 4;
        },

        async sendCode() {
            this.error = '';
            this.sending = true;

            try {
                const response = await fetch(config.sendUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                    },
                    body: JSON.stringify({ phone: this.phone }),
                    credentials: 'same-origin',
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(data.message || 'Could not send code.');
                }

                this.codeSent = true;
            } catch (exception) {
                this.error = exception.message || 'Could not send code.';
            } finally {
                this.sending = false;
            }
        },

        async checkCode() {
            this.error = '';
            this.checking = true;

            try {
                const response = await fetch(config.checkUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                    },
                    body: JSON.stringify({ phone: this.phone, code: this.code }),
                    credentials: 'same-origin',
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok || !data.verified) {
                    throw new Error(data.message || 'Invalid code.');
                }

                this.verified = true;
                this.error = '';
            } catch (exception) {
                this.verified = false;
                this.error = exception.message || 'Invalid code.';
            } finally {
                this.checking = false;
            }
        },
    }));

    window.Alpine.data('publicLeadStagedForm', (config = {}) => ({
        step: Number(config.initialStep ?? 1),
        concern: '',

        continueToContact() {
            const field = document.getElementById('concern');
            const value = String(field?.value ?? '').trim();

            if (value === '') {
                field?.focus();
                field?.reportValidity?.();

                return;
            }

            this.concern = value;
            this.step = 2;
        },
    }));
});
