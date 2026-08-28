import { withWorksheetBusy, worksheetBusyLabelForTab } from './ark-worksheet-busy';

const hashAliases = {
    builder: 'builder',
    'estimate-details': 'builder',
    'estimate-lines': 'builder',
    'communication-rail': 'comms',
    'send-rail': 'comms',
    'send': 'comms',
    'portal-rail': 'portal',
    'authorization-rail': 'auth',
    'parts-rail': 'parts',
    'inspect-rail': 'inspect',
    findings: 'inspect',
    'customer-communication': 'comms',
};

export function arkRoWorkspaceTabs(config = {}) {
    return {
        tab: config.defaultTab ?? 'builder',
        storageKey: config.storageKey ?? 'ark:ro-workspace-tab',
        tabs: config.tabs ?? ['builder', 'inspect', 'comms', 'portal', 'auth', 'parts', 'history'],
        lazyTabs: config.lazyTabs ?? [],
        tabUrl: config.tabUrl ?? null,
        workspaceMode: config.workspaceMode ?? 'review',
        loadedTabs: {},
        tabLoading: {},
        tabErrors: {},

        init() {
            this.initWorkspaceTabs();
        },

        initWorkspaceTabs() {
            if (this.workspaceMode === 'builder' || this.tabs.length === 1) {
                this.tab = 'builder';

                return;
            }

            if (this.applyHash()) {
                this.queueTabLoad(this.tab);

                return;
            }

            try {
                const stored = localStorage.getItem(this.storageKey);

                if (stored && this.tabs.includes(stored)) {
                    this.tab = stored;
                }
            } catch {
                // Private browsing or blocked storage — keep server default.
            }

            this.queueTabLoad(this.tab);
        },

        queueTabLoad(name) {
            if (! this.lazyTabs.includes(name)) {
                return;
            }

            queueMicrotask(() => this.refreshTab(name));
        },

        hashTab() {
            const hash = (window.location.hash || '').replace(/^#/, '');

            if (! hash) {
                return null;
            }

            if (hash.startsWith('finding-') && this.tabs.includes('inspect')) {
                return 'inspect';
            }

            if (hash === 'inspection-notes' && this.tabs.includes('inspect')) {
                return 'inspect';
            }

            const mapped = hashAliases[hash] ?? hash;

            return this.tabs.includes(mapped) ? mapped : null;
        },

        applyHash() {
            const fromHash = this.hashTab();

            if (! fromHash) {
                return false;
            }

            this.tab = fromHash;

            return true;
        },

        selectTab(name) {
            if (! this.tabs.includes(name)) {
                return;
            }

            const switching = this.tab !== name;

            this.tab = name;

            try {
                localStorage.setItem(this.storageKey, name);
            } catch {
                // Ignore storage failures.
            }

            const url = new URL(window.location.href);
            url.hash = name;
            window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}${url.hash}`);

            if (switching) {
                this.refreshTab(name);
            }
        },

        tabClass(name) {
            return this.tab === name
                ? 'ops-ro-workspace-tab ops-ro-workspace-tab--active'
                : 'ops-ro-workspace-tab';
        },

        panelShellClass(name) {
            return this.tabLoading[name]
                ? 'ops-ro-workspace-tab-panel-shell ops-ro-workspace-tab-panel-shell--loading'
                : 'ops-ro-workspace-tab-panel-shell';
        },

        async refreshTab(name) {
            if (! this.lazyTabs.includes(name) || ! this.tabUrl) {
                return;
            }

            await this.loadTab(name);
        },

        async loadTab(name) {
            if (! this.tabUrl || ! this.lazyTabs.includes(name)) {
                return;
            }

            this.tabLoading[name] = true;
            delete this.tabErrors[name];

            const requestId = Symbol();
            this.tabRequestIds = this.tabRequestIds ?? {};
            this.tabRequestIds[name] = requestId;

            try {
                await withWorksheetBusy(worksheetBusyLabelForTab(name), async () => {
                    const url = new URL(`${this.tabUrl}/${name}`, window.location.origin);
                    url.searchParams.set('mode', this.workspaceMode);

                    if (name === 'inspect') {
                        const pageUrl = new URL(window.location.href);

                        for (const key of ['capture', 'concern']) {
                            if (pageUrl.searchParams.has(key)) {
                                url.searchParams.set(key, pageUrl.searchParams.get(key));
                            }
                        }
                    }

                    const response = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            Accept: 'text/html',
                        },
                        credentials: 'same-origin',
                        cache: 'no-store',
                    });

                    if (! response.ok) {
                        throw new Error(`Tab load failed (${response.status})`);
                    }

                    if (this.tabRequestIds[name] !== requestId) {
                        return;
                    }

                    const html = await response.text();
                    const panel = this.$root.querySelector(`[data-workspace-tab-panel="${name}"]`);

                    if (! panel || this.tabRequestIds[name] !== requestId) {
                        return;
                    }

                    if (window.Alpine?.destroyTree) {
                        window.Alpine.destroyTree(panel);
                    }

                    panel.innerHTML = html;
                    window.Alpine?.initTree(panel);
                    this.loadedTabs[name] = true;

                    if (name === 'inspect') {
                        this.scrollInspectTarget();
                    }
                }, this.$root);
            } catch {
                if (this.tabRequestIds[name] === requestId) {
                    this.tabErrors[name] = true;
                }
            } finally {
                if (this.tabRequestIds[name] === requestId) {
                    this.tabLoading[name] = false;
                }
            }
        },

        async reloadTab(name) {
            return this.loadTab(name);
        },

        scrollInspectTarget() {
            const hash = (window.location.hash || '').replace(/^#/, '');

            if (! hash.startsWith('finding-') && hash !== 'inspection-notes') {
                return;
            }

            requestAnimationFrame(() => {
                document.getElementById(hash)?.scrollIntoView({ block: 'start' });
            });
        },
    };
}

export function arkReloadRepairOrderWorkspaceTab(tab) {
    const root = document.getElementById('repair-order-workspace-tabs');

    if (! root || ! window.Alpine) {
        return Promise.resolve();
    }

    const data = window.Alpine.$data(root);

    if (typeof data?.reloadTab !== 'function') {
        return Promise.resolve();
    }

    return data.reloadTab(tab);
}

/** Open a workspace tab from outside the Alpine root (e.g. Inspection entry CTA). */
export function arkSelectRepairOrderWorkspaceTab(tab) {
    const root = document.getElementById('repair-order-workspace-tabs');

    if (! root || ! window.Alpine?.$data) {
        window.location.hash = tab;

        return;
    }

    const data = window.Alpine.$data(root);

    if (typeof data?.selectTab === 'function') {
        data.selectTab(tab);
        root.scrollIntoView({ block: 'nearest' });

        return;
    }

    window.location.hash = tab;
}

// Backward compatibility for any cached references.
export const arkRoRailTabs = arkRoWorkspaceTabs;
