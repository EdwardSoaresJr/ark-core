const POLL_MS = 15000;

function fragmentUrl() {
    return document.getElementById('ops-work-recent-activity')?.dataset.fragmentUrl ?? '';
}

function activitySignature(rows) {
    if (! Array.isArray(rows)) {
        return '';
    }

    return rows.map((row) => [
        row.kind ?? '',
        row.call_session_id ?? '',
        row.conversation_message_id ?? '',
        row.conversation_id ?? '',
        row.state ?? '',
        row.age_label ?? '',
        row.snippet ?? '',
    ].join(':')).join('|');
}

export function initWorkRecentActivity() {
    const root = document.getElementById('ops-work-recent-activity');

    if (! root) {
        return;
    }

    const list = root.querySelector('[data-recent-activity-list]');
    const countNode = root.querySelector('[data-recent-activity-count]');
    const url = fragmentUrl();

    if (! list || url === '') {
        return;
    }

    let inflight = false;
    let lastSignature = '';

    const applyPayload = (payload) => {
        if (! payload || typeof payload.html !== 'string') {
            return;
        }

        list.innerHTML = payload.html;

        if (countNode) {
            const count = Number(payload.count ?? 0);

            if (count > 0) {
                countNode.textContent = `(${count})`;
                countNode.hidden = false;
            } else {
                countNode.textContent = '';
                countNode.hidden = true;
            }
        }
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
            const signature = activitySignature(payload.recent_activity ?? []);

            if (signature === lastSignature && lastSignature !== '') {
                return;
            }

            lastSignature = signature;
            applyPayload(payload);
        } catch {
            // Polling backup when realtime misses — stay quiet.
        } finally {
            inflight = false;
        }
    };

    document.addEventListener('ark:call-queue-changed', refresh);

    window.setInterval(refresh, POLL_MS);
    refresh();
}
