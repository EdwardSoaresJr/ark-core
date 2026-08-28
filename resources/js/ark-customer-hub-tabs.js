export function arkCustomerHubTabs(initialTab = 'vehicles') {
    return {
        tab: initialTab,

        init() {
            this.applyLocation();
            window.addEventListener('hashchange', () => this.applyLocation());
            document.addEventListener('ark:focus-comms-composer', () => {
                this.tab = 'comms';
            }, { capture: true });
            this.scrollToVehicleFromQuery();
            this.scrollToCommsAnchor();
            this.focusCommsComposerFromQuery();
        },

        focusCommsComposerFromQuery() {
            if (new URLSearchParams(window.location.search).get('compose') !== 'text') {
                return;
            }

            this.tab = 'comms';

            this.$nextTick(() => {
                document.dispatchEvent(new CustomEvent('ark:focus-comms-composer'));
            });
        },

        scrollToVehicleFromQuery() {
            const vehicleId = new URLSearchParams(window.location.search).get('vehicle');

            if (! vehicleId) {
                return;
            }

            this.tab = 'vehicles';

            this.$nextTick(() => {
                document.getElementById(`vehicle-${vehicleId}`)?.scrollIntoView({
                    block: 'nearest',
                    behavior: 'smooth',
                });
            });
        },

        scrollToCommsAnchor() {
            const hash = window.location.hash || '';

            if (! hash.startsWith('#comms-') && hash !== '#customer-communication') {
                return;
            }

            this.tab = 'comms';

            this.$nextTick(() => {
                document.getElementById('customer-communication')?.scrollIntoView({
                    block: 'nearest',
                    behavior: 'smooth',
                });
            });
        },

        applyLocation() {
            const hash = window.location.hash || '';

            if (hash === '#open-repair-orders' || hash === '#customer-work') {
                this.tab = 'work';

                return;
            }

            if (hash === '#customer-communication' || hash.startsWith('#comms-')) {
                this.tab = 'comms';
                this.$nextTick(() => {
                    document.getElementById('customer-communication')?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                });

                return;
            }

            if (hash === '#customer-timeline') {
                this.tab = 'visits';

                return;
            }

            if (hash === '#customer-documents') {
                this.tab = 'documents';

                return;
            }

            if (/^#vehicle-\d+$/.test(hash)) {
                this.tab = 'vehicles';
                this.$nextTick(() => {
                    document.querySelector(hash)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                });
            }
        },

        selectTab(name) {
            this.tab = name;
        },

        tabClass(name) {
            return this.tab === name
                ? 'ops-report-tab ops-report-tab--active'
                : 'ops-report-tab';
        },
    };
}
