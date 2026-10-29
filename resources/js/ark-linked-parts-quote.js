function isPanelPayload(payload) {
    return Boolean(payload)
        && Object.prototype.hasOwnProperty.call(payload, 'current')
        && Array.isArray(payload.history);
}

function copyPanel(payload) {
    return {
        available: payload.available,
        provider_label: payload.provider_label,
        open_note: payload.open_note,
        current: payload.current,
        history: payload.history,
    };
}

export function arkLinkedPartsQuote(config = {}) {
    return {
        config,
        panel: isPanelPayload(config.panel) ? copyPanel(config.panel) : null,
        candidates: [],
        choosing: false,
        open: false,
        notice: '',
        cached: false,
        loading: false,
        invokeEl: null,

        init() {
            if (this.panel) {
                return;
            }

            this.load();
        },

        destroy() {
            if (this.open) {
                this.releaseScrollLock();
            }
        },

        summaryTitle() {
            const provider = this.panel?.provider_label || 'Catalog';
            const supplier = this.panel?.current?.supplier;

            if (supplier) {
                return `${provider} · ${supplier}`;
            }

            return `${provider} Quote`;
        },

        summaryDetail() {
            if (! this.panel?.current) {
                return 'No quote linked';
            }

            return this.quoteMeta(this.panel.current);
        },

        quoteMeta(quote) {
            const count = Number(quote?.item_count ?? 0);
            const items = `${count} item${count === 1 ? '' : 's'}`;
            const purchaseOrder = quote?.purchase_order ? `PO ${quote.purchase_order}` : 'No purchase order';
            const status = this.statusLabel(quote?.status);
            const tail = quote?.reference_tail ? `···${quote.reference_tail}` : '';

            return [purchaseOrder, items, status, tail].filter(Boolean).join(' · ');
        },

        statusLabel(value) {
            const text = String(value ?? '').trim();

            if (text === '') {
                return '';
            }

            return text.charAt(0).toUpperCase() + text.slice(1).toLowerCase();
        },

        openManage(event) {
            this.invokeEl = typeof HTMLElement !== 'undefined' && event?.currentTarget instanceof HTMLElement
                ? event.currentTarget
                : null;
            this.open = true;
            this.lockScroll();
            this.$nextTick?.(() => this.focusDialog());
        },

        close() {
            if (! this.open) {
                return;
            }

            this.open = false;
            this.choosing = false;
            this.releaseScrollLock();

            const invokeEl = this.invokeEl;
            this.invokeEl = null;

            this.$nextTick?.(() => {
                if (invokeEl instanceof HTMLElement && document.contains(invokeEl)) {
                    invokeEl.focus();
                }
            });
        },

        onEscape(event) {
            if (! this.open) {
                return;
            }

            event.stopPropagation();
            this.close();
        },

        focusDialog() {
            const dialog = this.$refs?.dialog;

            if (dialog instanceof HTMLElement) {
                dialog.focus();
            }
        },

        focusables() {
            const dialog = this.$refs?.dialog;

            if (! (dialog instanceof HTMLElement)) {
                return [];
            }

            const selector = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

            return [...dialog.querySelectorAll(selector)].filter((el) => el.offsetParent !== null);
        },

        trapFocus(event) {
            if (! this.open || event.key !== 'Tab') {
                return;
            }

            const items = this.focusables();

            if (items.length === 0) {
                event.preventDefault();
                this.focusDialog();

                return;
            }

            const first = items[0];
            const last = items[items.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (! event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },

        lockScroll() {
            if (typeof document !== 'undefined') {
                document.body?.classList?.add('overflow-y-hidden');
            }
        },

        releaseScrollLock() {
            if (typeof document !== 'undefined') {
                document.body?.classList?.remove('overflow-y-hidden');
            }
        },

        async load() {
            const payload = await this.request(this.config.showUrl, 'GET');

            if (this.isPanel(payload)) {
                this.applyPanel(payload);
                this.cached = false;
            }
        },

        async refresh() {
            if (! this.panel?.current) {
                return;
            }

            const payload = await this.request(this.config.refreshUrl, 'POST');

            if (! payload) {
                return;
            }

            this.notice = payload.message || '';

            if (payload.ok === true) {
                await this.load();
                this.notice = payload.message || this.notice;

                return;
            }

            this.cached = true;
        },

        async choose() {
            this.choosing = true;
            const payload = await this.request(this.config.candidatesUrl, 'GET');

            if (! payload) {
                return;
            }

            if (payload.message) {
                this.notice = payload.message;
            }

            if (payload.ok === true && Array.isArray(payload.candidates)) {
                this.candidates = payload.candidates;

                return;
            }

            this.cached = true;
        },

        async link(externalId) {
            const payload = await this.request(this.config.linkUrl, 'POST', { external_id: externalId });

            if (! payload) {
                return;
            }

            this.notice = payload.message || '';

            if (payload.ok !== true) {
                this.cached = true;

                return;
            }

            await this.load();
            this.notice = payload.message || this.notice;
            this.choosing = false;
            this.candidates = [];
        },

        async makeCurrent(linkId) {
            const payload = await this.request(this.config.currentUrl, 'POST', { link_id: linkId });

            if (! payload) {
                return;
            }

            this.notice = payload.message || '';

            if (payload.ok !== true) {
                this.cached = true;

                return;
            }

            await this.load();
            this.notice = payload.message || this.notice;
        },

        async request(url, method, body) {
            this.loading = true;

            try {
                const response = await fetch(url, {
                    method,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.config.csrf,
                    },
                    body: method === 'GET' ? undefined : JSON.stringify(body || {}),
                });

                return await response.json();
            } catch (error) {
                this.notice = 'The linked quote could not be loaded.';
                this.cached = true;

                return null;
            } finally {
                this.loading = false;
            }
        },

        isPanel(payload) {
            return isPanelPayload(payload);
        },

        applyPanel(payload) {
            this.panel = copyPanel(payload);
        },

        quantity(value) {
            const text = String(value ?? '');

            return text.endsWith('.00') ? text.slice(0, -3) : text;
        },
    };
}
