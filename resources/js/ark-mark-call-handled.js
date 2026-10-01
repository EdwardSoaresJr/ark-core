function markWorkedUrlTemplate() {
    return document.querySelector('meta[name="ark-call-queue-mark-worked-url"]')?.content ?? '';
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export function markCallSessionHandled(callSessionId) {
    const id = Number(callSessionId);

    if (id <= 0) {
        return Promise.resolve(false);
    }

    const template = markWorkedUrlTemplate();

    if (template === '' || template.includes('__CALL_SESSION__')) {
        return Promise.resolve(false);
    }

    const url = template.replace('__CALL_SESSION__', String(id));

    document.dispatchEvent(new CustomEvent('ark:call-queue-refresh', { bubbles: true }));

    return fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        credentials: 'same-origin',
        keepalive: true,
    })
        .then((response) => response.ok)
        .catch(() => false);
}
