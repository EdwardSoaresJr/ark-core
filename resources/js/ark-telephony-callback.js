function callbackUrl() {
    return document.querySelector('meta[name="ark-telephony-callback-url"]')?.content ?? '';
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export async function initiateTelephonyCallback({
    customerId = null,
    phone = null,
    repairOrderId = null,
    callSessionId = null,
    button = null,
} = {}) {
    const url = callbackUrl();

    if (url === '') {
        window.alert('Callback is not available right now.');

        return false;
    }

    if (! customerId && ! phone) {
        window.alert('A customer phone number is required for callback.');

        return false;
    }

    const originalLabel = button?.textContent ?? '';

    if (button) {
        button.disabled = true;
        button.textContent = 'Ringing…';
    }

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                customer_id: customerId,
                phone,
                repair_order_id: repairOrderId,
                call_session_id: callSessionId,
            }),
        });

        const payload = await response.json().catch(() => ({}));

        if (! response.ok) {
            window.alert(payload.message ?? 'Callback could not be started.');

            return false;
        }

        if (payload.message) {
            window.alert(payload.message);
        }

        document.dispatchEvent(new CustomEvent('ark:call-queue-refresh', { bubbles: true }));

        return true;
    } catch {
        window.alert('Callback could not be started. Try again in a moment.');

        return false;
    } finally {
        if (button) {
            button.disabled = false;
            button.textContent = originalLabel || 'Callback';
        }
    }
}

export function arkTelephonyCallback() {
    return {
        initiating: false,
        message: '',

        async callback(customerId = null, phone = null, repairOrderId = null, event = null) {
            if (this.initiating) {
                return;
            }

            this.initiating = true;
            this.message = '';

            const button = event?.currentTarget ?? null;
            const started = await initiateTelephonyCallback({
                customerId,
                phone,
                repairOrderId,
                button,
            });

            this.initiating = false;

            if (started) {
                this.message = 'Your phone is ringing. Answer to reach the customer.';
            }
        },
    };
}
