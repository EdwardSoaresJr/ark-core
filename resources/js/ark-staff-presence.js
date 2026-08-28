function heartbeatUrl() {
    return document.querySelector('meta[name="ark-staff-presence-heartbeat-url"]')?.content ?? '';
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export function initStaffPresenceHeartbeat() {
    const url = heartbeatUrl();

    if (url === '') {
        return;
    }

    const pulse = async () => {
        try {
            await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
            });
        } catch {
            // Presence is best-effort when the browser is offline or throttled.
        }
    };

    pulse();
    window.setInterval(pulse, 60000);
}
