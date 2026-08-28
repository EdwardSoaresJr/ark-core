export function deliveryHttpErrorMessage(response, data = {}, fallback = 'Delivery could not be sent.') {
    if (response.status === 419) {
        return 'Your session expired. Refresh the page and try again.';
    }

    if (response.status === 403) {
        return data?.message ?? 'You do not have permission for this action.';
    }

    if (response.status === 422) {
        return data?.message ?? fallback;
    }

    if (response.status >= 500) {
        return 'The server could not complete this send. Try again in a moment.';
    }

    if (typeof data?.message === 'string' && data.message !== '') {
        return data.message;
    }

    return fallback;
}

export function deliveryPayload(delivery, customerEmail = '', extra = {}) {
    const payload = { delivery, ...extra };

    if (delivery === 'email' || delivery === 'both') {
        payload.email = customerEmail || null;
    }

    return payload;
}

export function deliveryChannelBlockReason(delivery, channels = {}) {
    const {
        canSms = false,
        canEmail = false,
        smsBlockReason = '',
        emailBlockReason = '',
    } = channels;

    if (delivery === 'sms') {
        return canSms ? null : (smsBlockReason || 'SMS is not available for this repair order.');
    }

    if (delivery === 'email') {
        return canEmail ? null : (emailBlockReason || 'Email is not available for this repair order.');
    }

    if (delivery === 'both') {
        if (! canSms && ! canEmail) {
            if (smsBlockReason !== '' && smsBlockReason === emailBlockReason) {
                return smsBlockReason;
            }

            return [
                smsBlockReason || 'SMS is not available.',
                emailBlockReason || 'Email is not available.',
            ].join(' ');
        }

        if (! canSms) {
            return smsBlockReason || 'SMS is not available for this repair order.';
        }

        if (! canEmail) {
            return emailBlockReason || 'Email is not available for this repair order.';
        }
    }

    return null;
}
