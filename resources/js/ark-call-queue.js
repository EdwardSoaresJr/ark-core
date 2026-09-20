import { getArkEcho, arkEchoEnabled } from './ark-echo';
import { initiateTelephonyCallback } from './ark-telephony-callback';
import { markCallSessionHandled } from './ark-mark-call-handled';

function queueUrl() {
    return document.querySelector('meta[name="ark-call-queue-url"]')?.content ?? '';
}

function markWorkedUrlTemplate() {
    return document.querySelector('meta[name="ark-call-queue-mark-worked-url"]')?.content ?? '';
}

function claimUrlTemplate() {
    return document.querySelector('meta[name="ark-call-queue-claim-url"]')?.content ?? '';
}

function markReadUrlTemplate() {
    return document.querySelector('meta[name="ark-comms-mark-read-url"]')?.content ?? '';
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function notifyQueueChanged(payload = null) {
    document.dispatchEvent(new CustomEvent('ark:call-queue-changed', {
        bubbles: true,
        detail: payload,
    }));
}

function readCallQueueBootstrap() {
    const node = document.getElementById('ark-call-queue-bootstrap');

    if (! node || node.textContent.trim() === '') {
        return {};
    }

    try {
        return JSON.parse(node.textContent);
    } catch {
        return {};
    }
}

function normalizeQueuePayload(data = {}) {
    const calls = Array.isArray(data.calls) ? data.calls : [];
    const messages = Array.isArray(data.messages) ? data.messages : [];
    const summary = normalizeSummary(data.summary);
    const items = Array.isArray(data.items)
        ? data.items
        : [...calls, ...messages];
    const count = Number(
        data.nav_pressure_count
        ?? data.count
        ?? summary.count
        ?? (summary.call_count + summary.message_count)
        ?? items.length,
    );

    return {
        calls,
        messages,
        items,
        count,
        nav_pressure_count: Number(data.nav_pressure_count ?? count),
        workboard_counts: data.workboard_counts ?? {},
        summary: {
            ...summary,
            count,
        },
        queue_url: data.queue_url ?? '',
    };
}

function normalizeSummary(summary = {}) {
    return {
        count: Number(summary.count ?? 0),
        call_count: Number(summary.call_count ?? 0),
        message_count: Number(summary.message_count ?? 0),
        since_last_shift_count: Number(summary.since_last_shift_count ?? 0),
        has_live_calls: Boolean(summary.has_live_calls),
        urgency: summary.urgency ?? 'idle',
        breakdown_label: summary.breakdown_label ?? '',
        trigger_label: summary.trigger_label ?? '',
    };
}

export function arkCallQueue(bootstrap = null) {
    const initial = normalizeQueuePayload(bootstrap ?? readCallQueueBootstrap());

    return {
        open: false,
        calls: initial.calls,
        items: initial.items,
        count: initial.count,
        summary: initial.summary,
        queueUrl: initial.queue_url,
        loading: false,
        pollTimer: null,
        panelStyle: '',

        init() {
            this.bindItemActions();
            this.refresh();
            this.pollTimer = window.setInterval(() => this.refresh(), 5000);
            this.bindRealtime();

            document.addEventListener('ark:call-queue-refresh', () => {
                this.refresh();
            });

            window.addEventListener('resize', () => {
                if (this.open) {
                    this.positionPanel();
                }
            });
        },

        bindRealtime() {
            if (! arkEchoEnabled()) {
                return;
            }

            const echo = getArkEcho();

            if (! echo) {
                return;
            }

            echo.private('operations.comms-interrupts')
                .listen('.comms.interrupt', () => {
                    this.refresh();
                });

            echo.private('operations.incoming-calls')
                .listen('.call.updated', () => {
                    this.refresh();
                });

            echo.private('operations.conversations')
                .listen('.conversation.message.received', () => {
                    this.refresh();
                });
        },

        positionPanel() {
            const trigger = this.$refs.trigger;

            if (! trigger) {
                return;
            }

            const rect = trigger.getBoundingClientRect();
            const right = Math.max(12, window.innerWidth - rect.right);
            const top = rect.bottom + 6;

            this.panelStyle = `top:${top}px;right:${right}px;`;
        },

        toggle() {
            this.open = ! this.open;

            if (! this.open) {
                return;
            }

            this.$nextTick(() => {
                this.positionPanel();
                this.refresh();
            });
        },

        async refresh() {
            const url = queueUrl();

            if (url === '') {
                return;
            }

            this.loading = true;

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

                const data = await response.json();
                const payload = normalizeQueuePayload(data);
                this.calls = payload.calls;
                this.items = payload.items;
                this.summary = payload.summary;
                this.count = payload.count;
                this.queueUrl = payload.queue_url;

                if (typeof payload.html === 'string' && this.$refs.itemsRoot) {
                    this.$refs.itemsRoot.innerHTML = payload.html;
                }

                notifyQueueChanged(payload);
            } catch {
                // Queue polling is a calm backup when realtime misses.
            } finally {
                this.loading = false;
            }
        },

        bindItemActions() {
            const root = this.$refs.itemsRoot;

            if (! root) {
                return;
            }

            root.addEventListener('click', (event) => {
                const claimButton = event.target.closest('[data-call-queue-claim]');

                if (claimButton) {
                    event.preventDefault();
                    this.claimCall(Number(claimButton.dataset.callQueueClaim));

                    return;
                }

                const workedButton = event.target.closest('[data-call-queue-mark-worked]');

                if (workedButton) {
                    event.preventDefault();
                    this.markWorked(Number(workedButton.dataset.callQueueMarkWorked));

                    return;
                }

                const readButton = event.target.closest('[data-call-queue-mark-read]');

                if (readButton) {
                    event.preventDefault();
                    this.markRead(Number(readButton.dataset.callQueueMarkRead));
                }
            });
        },

        itemKey(item) {
            if (item.kind === 'call') {
                return `call-${item.call_session_id}`;
            }

            return `message-${item.conversation_message_id ?? item.conversation_id}`;
        },

        actionUrl(template, id) {
            return template.replace('__CALL_SESSION__', String(id)).replace('__CONVERSATION__', String(id));
        },

        markWorkedUrl(callSessionId) {
            return this.actionUrl(markWorkedUrlTemplate(), callSessionId);
        },

        claimUrl(callSessionId) {
            return this.actionUrl(claimUrlTemplate(), callSessionId);
        },

        markReadUrl(conversationId) {
            return this.actionUrl(markReadUrlTemplate(), conversationId);
        },

        async callbackCustomer(customerId, phone, callSessionId = null, event = null) {
            const started = await initiateTelephonyCallback({
                customerId: customerId || null,
                phone: phone || null,
                callSessionId: callSessionId || null,
                button: event?.currentTarget ?? null,
            });

            if (started && callSessionId) {
                this.removeCallerFromQueue(callSessionId);
            }
        },

        touchCall(callSessionId) {
            void markCallSessionHandled(callSessionId);
            this.removeCallerFromQueue(callSessionId);
        },

        async claimCall(callSessionId) {
            const url = this.claimUrl(callSessionId);

            if (url === '' || url.includes('__CALL_SESSION__')) {
                return;
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
                    keepalive: true,
                });

                if (! response.ok) {
                    return;
                }

                this.removeCallerFromQueue(callSessionId);

                await this.refresh();
            } catch {
                // Advisor can retry from the queue row.
            }
        },

        removeCallerFromQueue(callSessionId) {
            const call = this.items.find((row) => Number(row.call_session_id) === Number(callSessionId));
            const calleeKey = call?.normalized_from ?? '';

            if (calleeKey !== '') {
                this.items = this.items.filter((row) => row.normalized_from !== calleeKey);
            } else {
                this.items = this.items.filter((row) => Number(row.call_session_id) !== Number(callSessionId));
            }

            this.calls = this.items.filter((row) => row.kind === 'call');
            this.count = this.items.length;
            notifyQueueChanged();
        },

        removeConversationFromQueue(conversationId) {
            this.items = this.items.filter((row) => Number(row.conversation_id) !== Number(conversationId));
            this.count = this.items.length;
            notifyQueueChanged();
        },

        async markWorked(callSessionId) {
            const url = this.markWorkedUrl(callSessionId);

            if (url === '' || url.includes('__CALL_SESSION__')) {
                return;
            }

            this.removeCallerFromQueue(callSessionId);

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
                    keepalive: true,
                });

                if (! response.ok) {
                    await this.refresh();
                }
            } catch {
                await this.refresh();
            }
        },

        async markRead(conversationId) {
            const url = this.markReadUrl(conversationId);

            if (url === '' || url.includes('__CONVERSATION__')) {
                return;
            }

            this.removeConversationFromQueue(conversationId);

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
                    keepalive: true,
                });

                if (! response.ok) {
                    await this.refresh();
                }
            } catch {
                await this.refresh();
            }
        },

        countToneClass() {
            if (this.summary.has_live_calls) {
                return 'ops-call-queue__count--live';
            }

            if (this.summary.since_last_shift_count > 0) {
                return 'ops-call-queue__count--shift';
            }

            if (this.count > 0) {
                return 'ops-call-queue__count--attention';
            }

            return '';
        },

        triggerTitle() {
            if (this.count === 0) {
                return 'Attention — nothing needs attention';
            }

            const parts = [this.summary.trigger_label, this.summary.breakdown_label]
                .filter((part) => part !== '');

            return `Attention — ${parts.join(' · ')}`;
        },

        get urgency() {
            return this.summary.urgency ?? (this.count > 0 ? 'attention' : 'idle');
        },
    };
}
