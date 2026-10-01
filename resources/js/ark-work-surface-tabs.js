const VALID_TABS = new Set(['overview', 'next', 'queues']);

export function arkWorkSurfaceTabs(initialTab = 'overview') {
    const normalizedInitial = VALID_TABS.has(initialTab) ? initialTab : 'overview';

    return {
        tab: normalizedInitial,

        init() {
            this.applyQueryTab();
            window.addEventListener('popstate', () => this.applyQueryTab());
        },

        applyQueryTab() {
            const fromQuery = new URLSearchParams(window.location.search).get('tab');

            if (fromQuery && VALID_TABS.has(fromQuery)) {
                this.tab = fromQuery;
            }
        },

        tabClass(name) {
            return this.tab === name
                ? 'ops-report-tab ops-report-tab--active'
                : 'ops-report-tab';
        },

        selectTab(name) {
            if (! VALID_TABS.has(name)) {
                return;
            }

            this.tab = name;

            const url = new URL(window.location.href);
            url.searchParams.set('tab', name);
            window.history.replaceState({}, '', url);
        },
    };
}
