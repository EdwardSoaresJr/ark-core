import { arkEchoEnabled, getArkEcho } from './ark-echo';

const POLL_MS = 5000;

const LANE_SECTIONS = [
    ['calls', 'ops-comms-lane-calls'],
    ['new', 'ops-comms-lane-new'],
    ['needs_shop', 'ops-comms-lane-needs-shop'],
    ['waiting_customer', 'ops-comms-lane-waiting-customer'],
    ['recently_resolved', 'ops-comms-lane-recently-resolved'],
];

function fragmentUrl() {
    return document.getElementById('ops-comms-workboard-live')?.dataset.fragmentUrl ?? '';
}

function syncActionableCount(count) {
    const title = document.getElementById('ops-comms-workboard');

    if (! title) {
        return;
    }

    let countEl = title.querySelector('[data-workboard-actionable-count]');
    const value = Number(count ?? 0);

    if (value > 0) {
        if (! countEl) {
            countEl = document.createElement('span');
            countEl.className = 'ops-pressure-count ops-pressure-count--inline';
            countEl.dataset.workboardActionableCount = '';
            title.appendChild(countEl);
        }

        countEl.textContent = `(${value})`;
        countEl.hidden = false;
    } else if (countEl) {
        countEl.textContent = '';
        countEl.hidden = true;
    }
}

function replaceLaneSection(sectionId, html) {
    const section = document.getElementById(sectionId);

    if (! section || typeof html !== 'string' || html === '') {
        return;
    }

    section.outerHTML = html;
}

export function initCommsWorkboard() {
    const root = document.getElementById('ops-comms-workboard-live');
    const url = fragmentUrl();

    if (! root || url === '') {
        return;
    }

    let inflight = false;
    let lastSignature = '';

    const applyPayload = (payload) => {
        if (! payload || typeof payload.lanes !== 'object') {
            return;
        }

        for (const [laneKey, sectionId] of LANE_SECTIONS) {
            replaceLaneSection(sectionId, payload.lanes[laneKey] ?? '');
        }

        syncActionableCount(payload.counts?.total_actionable ?? 0);
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
            // Polling backup when realtime misses — stay quiet.
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

        const channels = [
            echo.private('operations.conversations'),
            echo.private('operations.comms-interrupts'),
        ];

        for (const channel of channels) {
            channel
                .listen('.conversation.message.received', refresh)
                .listen('.comms.interrupt', refresh);
        }
    };

    document.addEventListener('ark:call-queue-changed', refresh);

    bindRealtime();
    window.setInterval(refresh, POLL_MS);
    refresh();
}
