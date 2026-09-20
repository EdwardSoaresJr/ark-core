function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export function arkMessengerLinkCustomer(config = {}) {
    return {
        open: false,
        query: '',
        searchLoading: false,
        selectedCustomerId: null,
        selectedCustomerName: '',
        linking: false,
        error: '',
        linkUrl: config.linkUrl ?? '',
        searchUrl: config.searchUrl ?? '',
        _searchAbort: null,
        _searchTimer: null,

        init() {
            this.$watch('query', (value) => {
                window.clearTimeout(this._searchTimer);
                this._searchTimer = window.setTimeout(() => this.search(value), 250);
            });

            this.$refs.results?.addEventListener('click', (event) => {
                const card = event.target.closest('[data-intake-customer-id]');

                if (! card) {
                    return;
                }

                const id = Number(card.dataset.intakeCustomerId);
                const name = card.querySelector('.ops-ro-vehicle')?.textContent?.trim() ?? 'Customer';

                this.selectCustomer(id, name);
            });

            this.$refs.results?.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }

                const card = event.target.closest('[data-intake-customer-id]');

                if (! card) {
                    return;
                }

                event.preventDefault();

                const id = Number(card.dataset.intakeCustomerId);
                const name = card.querySelector('.ops-ro-vehicle')?.textContent?.trim() ?? 'Customer';

                this.selectCustomer(id, name);
            });
        },

        toggle() {
            this.open = ! this.open;
            this.error = '';

            if (this.open) {
                this.$nextTick(() => this.$refs.searchInput?.focus());
            }
        },

        selectCustomer(id, name) {
            this.selectedCustomerId = id;
            this.selectedCustomerName = name;
            this.error = '';
        },

        clearSelection() {
            this.selectedCustomerId = null;
            this.selectedCustomerName = '';
        },

        async search(value = this.query) {
            const trimmed = String(value ?? '').trim();

            if (trimmed === '') {
                if (this.$refs.results) {
                    this.$refs.results.innerHTML = '';
                }

                return;
            }

            this._searchAbort?.abort();
            this._searchAbort = new AbortController();
            this.searchLoading = true;
            this.error = '';

            try {
                const url = new URL(this.searchUrl, window.location.origin);
                url.searchParams.set('q', trimmed);

                const response = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'text/html',
                    },
                    signal: this._searchAbort.signal,
                });

                if (! response.ok) {
                    this.error = 'Customer search failed.';

                    return;
                }

                if (this.$refs.results) {
                    this.$refs.results.innerHTML = await response.text();
                }
            } catch (error) {
                if (error?.name === 'AbortError') {
                    return;
                }

                this.error = 'Customer search failed.';
            } finally {
                this.searchLoading = false;
            }
        },

        async submitLink() {
            if (this.linking || this.selectedCustomerId === null || this.linkUrl === '') {
                return;
            }

            this.linking = true;
            this.error = '';

            const formData = new FormData();
            formData.append('customer_id', String(this.selectedCustomerId));
            formData.append('_token', csrfToken());

            try {
                const response = await fetch(this.linkUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                });

                if (response.ok) {
                    window.location.reload();

                    return;
                }

                const data = await response.json().catch(() => ({}));
                this.error = data?.message ?? 'Could not link Messenger thread.';
            } catch {
                this.error = 'Could not link Messenger thread.';
            } finally {
                this.linking = false;
            }
        },
    };
}
