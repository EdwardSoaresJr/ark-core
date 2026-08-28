import { arkEchoEnabled, getArkEcho } from './ark-echo';

const POLL_MS = 15000;

function fragmentUrl() {
    return document.getElementById('ops-workboard-triage-live')?.dataset.fragmentUrl ?? '';
}

function syncQueueCount(count) {
    const root = document.getElementById('ops-workboard-triage-live');

    if (! root) {
        return;
    }

    root.dataset.queueCount = String(count ?? 0);
}

export function initOpsWorkboardTriage() {
    const root = document.getElementById('ops-workboard-triage-live');
    const url = fragmentUrl();

    if (! root || url === '') {
        return;
    }

    let inflight = false;
    let lastSignature = '';

    const applyPayload = (payload) => {
        if (! payload || typeof payload.html !== 'string' || payload.html === '') {
            return;
        }

        root.innerHTML = payload.html;
        syncQueueCount(payload.queue_count ?? 0);
    };

    const refresh = async () => {
        if (inflight || document.hidden) {
            return;
        }

        inflight = true;

        try {
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (! response.ok) {
                return;
            }

            const payload = await response.json();
            const signature = String(payload.signature ?? '');

            if (signature !== '' && signature === lastSignature) {
                return;
            }

            lastSignature = signature;
            applyPayload(payload);
        } catch {
            // Polling backup — stay quiet.
        } finally {
            inflight = false;
        }
    };

    const bindRealtime = () => {
        if (! arkEchoEnabled()) {
            return;
        }

        const echo = getArkEcho();

        if (! echo) {
            return;
        }

        echo.private('operations.conversations')
            .listen('.conversation.message.received', refresh);
    };

    bindRealtime();
    window.setInterval(refresh, POLL_MS);
    refresh();
}
