import { arkEchoEnabled, getArkEcho } from './ark-echo';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export function arkCustomerHubComms(config = {}) {
    const initialFilter = config.initialFilter ?? 'all';
    const counts = config.counts ?? {};

    return {
        filter: initialFilter,
        counts,
        customerId: config.customerId ?? null,
        updatesUrl: config.updatesUrl ?? '',
        messagesListId: config.messagesListId ?? 'conversation-messages-relationship',
        latestMessageId: 0,
        pollTimer: null,

        init() {
            this.refreshLatestMessageId();
            this.syncRowVisibility();
            this.bindRealtime();
            this.bindComposerIngest();
            this.startPolling();
        },

        setFilter(name) {
            this.filter = name;
            this.syncRowVisibility();
        },

        isActive(name) {
            return this.filter === name;
        },

        filterClass(name) {
            return this.isActive(name)
                ? 'ops-state-pill border-slate-800 bg-slate-900 text-white'
                : 'ops-state-pill border-slate-300 bg-white text-slate-700 hover:border-slate-400';
        },

        matches(rowFilter) {
            return this.filter === 'all' || this.filter === rowFilter;
        },

        showComposer() {
            return true;
        },

        isFilterEmpty() {
            if (this.filter === 'all') {
                return false;
            }

            const list = document.getElementById(this.messagesListId);

            if (list) {
                const hasVisibleRow = [...list.querySelectorAll('[data-conversation-row]')].some((row) => {
                    const rowFilter = row.getAttribute('data-filter') ?? 'logged';

                    return rowFilter === this.filter && row.style.display !== 'none';
                });

                if (hasVisibleRow) {
                    return false;
                }
            }

            return (this.counts[this.filter] ?? 0) === 0;
        },

        emptyLabel() {
            if (this.filter === 'call') {
                return 'No calls in this timeline yet.';
            }

            if (this.filter === 'text') {
                return 'No text messages yet. Use the composer below or wait for an inbound SMS.';
            }

            if (this.filter === 'email') {
                return 'No email logged yet. Outbound estimate and invoice sends appear here when mail is recorded.';
            }

            if (this.filter === 'portal') {
                return 'No portal activity yet. Customer estimate opens appear here when they view the approval link.';
            }

            if (this.filter === 'logged') {
                return 'No logged contact notes yet.';
            }

            return 'No communications recorded for this customer yet.';
        },

        refreshLatestMessageId() {
            const list = document.getElementById(this.messagesListId);

            if (! list) {
                return;
            }

            const ids = [...list.querySelectorAll('[data-conversation-message-id]')]
                .map((node) => Number(node.getAttribute('data-conversation-message-id')))
                .filter((id) => ! Number.isNaN(id) && id > 0);

            this.latestMessageId = ids.length > 0 ? Math.max(...ids) : 0;
        },

        syncRowVisibility() {
            const list = document.getElementById(this.messagesListId);

            if (! list) {
                return;
            }

            list.querySelectorAll('[data-conversation-row]').forEach((row) => {
                const rowFilter = row.getAttribute('data-filter') ?? 'logged';
                row.style.display = this.matches(rowFilter) ? '' : 'none';
            });
        },

        bindRealtime() {
            if (! arkEchoEnabled() || this.customerId === null) {
                return;
            }

            const echo = getArkEcho();

            if (! echo) {
                return;
            }

            echo.private('operations.conversations')
                .listen('.conversation.message.received', (payload) => {
                    if (Number(payload?.customer_id) !== Number(this.customerId)) {
                        return;
                    }

                    this.ingestTimelineItem({
                        message_id: payload?.message_id ?? payload?.message?.id,
                        filter: payload?.hub_filter ?? 'text',
                        html: payload?.html,
                    });
                });
        },

        bindComposerIngest() {
            document.addEventListener('ark:hub-comms-ingest', (event) => {
                const detail = event.detail ?? {};

                this.ingestTimelineItem({
                    message_id: detail.message_id,
                    filter: detail.filter ?? 'text',
                });
            });
        },

        startPolling() {
            if (this.updatesUrl === '') {
                return;
            }

            const poll = async () => {
                if (! this.$el.isConnected) {
                    return;
                }

                try {
                    const url = new URL(this.updatesUrl, window.location.origin);
                    url.searchParams.set('since_message_id', String(this.latestMessageId));

                    const response = await fetch(url.toString(), {
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
                    const items = Array.isArray(data?.items) ? data.items : [];

                    for (const item of items.reverse()) {
                        this.ingestTimelineItem(item);
                    }
                } catch {
                    // Polling backs up websocket delivery when Reverb is unavailable.
                }
            };

            poll();
            this.pollTimer = window.setInterval(poll, 2500);
        },

        ingestTimelineItem(item) {
            const messageId = Number(item?.message_id ?? 0);
            const html = item?.html ?? '';
            const rowFilter = item?.filter ?? 'text';

            if (messageId <= 0) {
                if (rowFilter !== this.filter && this.filter !== 'all') {
                    return;
                }

                this.bumpCounts(rowFilter);

                return;
            }

            if (html === '') {
                return;
            }

            if (messageId <= this.latestMessageId) {
                return;
            }

            this.prependTimelineRow(html, messageId, rowFilter);
            this.latestMessageId = Math.max(this.latestMessageId, messageId);
        },

        prependTimelineRow(html, messageId, rowFilter) {
            const list = document.getElementById(this.messagesListId);

            if (! list) {
                return;
            }

            if (list.querySelector(`[data-conversation-message-id="${messageId}"]`)) {
                return;
            }

            const emptyState = list.querySelector('[data-conversation-empty]');

            if (emptyState) {
                emptyState.remove();
            }

            const wrapper = document.createElement('div');
            wrapper.setAttribute('data-conversation-row', '');
            wrapper.setAttribute('data-filter', rowFilter);
            wrapper.style.display = this.matches(rowFilter) ? '' : 'none';
            wrapper.innerHTML = html;

            list.insertAdjacentElement('afterbegin', wrapper);

            this.bumpCounts(rowFilter);
        },

        bumpCounts(rowFilter) {
            if (this.counts.all !== undefined) {
                this.counts.all += 1;
            }

            if (this.counts[rowFilter] !== undefined) {
                this.counts[rowFilter] += 1;
            }
        },
    };
}
