export function arkIntakeCustomerCreate(config = {}) {
    return {
        firstName: config.initialFirstName ?? '',
        lastName: config.initialLastName ?? '',
        phone: config.initialPhone ?? '',
        email: config.initialEmail ?? '',
        loading: false,
        checkUrl: config.checkUrl,
        _debounceTimer: null,
        _checkAbort: null,

        init() {
            this.$watch('firstName', () => this.scheduleCheck());
            this.$watch('lastName', () => this.scheduleCheck());
            this.$watch('phone', () => this.scheduleCheck());
            this.$watch('email', () => this.scheduleCheck());

            this.scheduleCheck();
        },

        scheduleCheck() {
            clearTimeout(this._debounceTimer);
            this._debounceTimer = setTimeout(() => this.runCheck(), 350);
        },

        hasCheckableInput() {
            return this.firstName.trim().length >= 2
                || this.lastName.trim().length >= 2
                || this.phone.replace(/\D/g, '').length >= 7
                || (this.email.includes('@') && this.email.trim().length >= 5);
        },

        async runCheck() {
            if (! this.hasCheckableInput()) {
                this.$refs.duplicates.innerHTML = '';
                this.loading = false;

                return;
            }

            this._checkAbort?.abort();
            this._checkAbort = new AbortController();
            this.loading = true;

            try {
                const url = new URL(this.checkUrl, window.location.origin);
                url.searchParams.set('first_name', this.firstName.trim());
                url.searchParams.set('last_name', this.lastName.trim());
                url.searchParams.set('phone', this.phone.trim());
                url.searchParams.set('email', this.email.trim());

                const response = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'text/html',
                    },
                    signal: this._checkAbort.signal,
                });

                if (! response.ok) {
                    return;
                }

                this.$refs.duplicates.innerHTML = await response.text();
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
