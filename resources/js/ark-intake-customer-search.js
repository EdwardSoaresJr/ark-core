export function arkIntakeCustomerSearch(config = {}) {
    return {
        query: config.initialQuery ?? '',
        loading: false,
        searchUrl: config.searchUrl,
        _debounceTimer: null,
        _searchAbort: null,

        init() {
            if (this.query.trim() !== '') {
                this.runSearch();
            }

            this.$watch('query', () => {
                clearTimeout(this._debounceTimer);
                this._debounceTimer = setTimeout(() => this.runSearch(), 300);
            });
        },

        async runSearch() {
            const trimmed = this.query.trim();

            if (trimmed === '') {
                this.$refs.results.innerHTML = '';
                this.loading = false;

                return;
            }

            this._searchAbort?.abort();
            this._searchAbort = new AbortController();
            this.loading = true;

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
                    return;
                }

                this.$refs.results.innerHTML = await response.text();
            } catch (error) {
                if (error?.name === 'AbortError') {
                    return;
                }

                throw error;
            } finally {
                this.loading = false;
            }
        },
    };
}
