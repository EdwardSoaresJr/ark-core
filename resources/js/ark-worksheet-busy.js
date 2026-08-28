const TAB_BUSY_LABELS = {
    builder: 'Loading estimate…',
    inspect: 'Loading inspection…',
    comms: 'Refreshing communications…',
    portal: 'Refreshing portal…',
    auth: 'Refreshing authorization…',
    parts: 'Refreshing parts…',
    history: 'Loading history…',
};

export function worksheetRootFrom(anchor = null) {
    if (anchor instanceof Element) {
        return anchor.closest('[data-worksheet-root]');
    }

    return document.querySelector('[data-worksheet-root]');
}

export function worksheetStateFrom(anchor = null) {
    const root = worksheetRootFrom(anchor);

    if (! root) {
        return null;
    }

    return window.Alpine?.$data?.(root) ?? root._x_dataStack?.[0] ?? null;
}

export function worksheetBusyLabelForTab(tab) {
    return TAB_BUSY_LABELS[tab] ?? 'Working…';
}

export function beginWorksheetBusy(label = 'Working…', anchor = null) {
    const worksheet = worksheetStateFrom(anchor);

    if (typeof worksheet?.beginWorksheetBusy !== 'function') {
        return null;
    }

    worksheet.beginWorksheetBusy(label);

    return worksheet;
}

export function endWorksheetBusy(anchor = null) {
    const worksheet = worksheetStateFrom(anchor);

    if (typeof worksheet?.endWorksheetBusy === 'function') {
        worksheet.endWorksheetBusy();
    }
}

export async function withWorksheetBusy(label, fn, anchor = null) {
    beginWorksheetBusy(label, anchor);

    try {
        return await fn();
    } finally {
        endWorksheetBusy(anchor);
    }
}

export function portalSendBusyLabel(delivery) {
    if (delivery === 'sms') {
        return 'Sending portal link via SMS…';
    }

    if (delivery === 'email') {
        return 'Sending portal link via email…';
    }

    if (delivery === 'both') {
        return 'Sending portal link via SMS and email…';
    }

    return 'Sending portal link…';
}

export function estimateDeliveryBusyLabel(delivery) {
    if (delivery === 'sms') {
        return 'Sending estimate via SMS…';
    }

    if (delivery === 'email') {
        return 'Sending estimate via email…';
    }

    if (delivery === 'both') {
        return 'Sending estimate via SMS and email…';
    }

    return 'Sending estimate…';
}

export function paymentDeliveryBusyLabel(delivery) {
    if (delivery === 'sms') {
        return 'Sending payment link via SMS…';
    }

    if (delivery === 'email') {
        return 'Sending payment link via email…';
    }

    if (delivery === 'both') {
        return 'Sending payment link via SMS and email…';
    }

    return 'Sending payment link…';
}

export function depositDeliveryBusyLabel(delivery) {
    if (delivery === 'sms') {
        return 'Sending deposit request via SMS…';
    }

    if (delivery === 'email') {
        return 'Sending deposit request via email…';
    }

    if (delivery === 'both') {
        return 'Sending deposit request via SMS and email…';
    }

    return 'Sending deposit request…';
}

export function conversationSendBusyLabel(channel = 'sms') {
    if (channel === 'messenger') {
        return 'Sending Messenger message…';
    }

    return 'Sending text…';
}
