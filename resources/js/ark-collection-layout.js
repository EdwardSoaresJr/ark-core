/**
 * Shared List | Cards switcher for record indexes (Customers, Vehicles, Repair Orders).
 * Uses ark-ui-layout-preference (tenant + user + module). Does not rewrite ?view=.
 */
import {
    MD_UP,
    UiLayoutModule,
    normalizeLayout,
    readExplicitLayout,
    readViewQueryParam,
    scopeFromDocument,
    stripViewQueryParam,
    viewportDefault,
    writeExplicitLayout,
} from './ark-ui-layout-preference';

const MODULE_BY_ATTR = Object.freeze({
    customers: UiLayoutModule.Customers,
    vehicles: UiLayoutModule.Vehicles,
    repair_orders: UiLayoutModule.RepairOrders,
});

function collectionScope(root) {
    const docScope = scopeFromDocument(document);
    const moduleAttr = root.getAttribute('data-collection-module') || '';
    const module = MODULE_BY_ATTR[moduleAttr] || moduleAttr;

    return {
        tenant: root.getAttribute('data-ark-ui-tenant') || docScope.tenant,
        userId: root.getAttribute('data-ark-ui-user') || docScope.userId,
        module,
        cookieDomain: root.getAttribute('data-collection-cookie-domain') || null,
    };
}

function syncHiddenInputs(root, layout) {
    root.querySelectorAll('[data-collection-view-input]').forEach((input) => {
        input.value = layout;
        input.disabled = false;
    });
}

function syncToggle(root, layout) {
    root.querySelectorAll('[data-collection-layout-option]').forEach((btn) => {
        const option = btn.getAttribute('data-collection-layout-option');
        const active = option === layout;
        btn.classList.toggle('active', active);
        if (active) {
            btn.setAttribute('aria-current', 'true');
        } else {
            btn.removeAttribute('aria-current');
        }
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
}

function applyLayout(root, layout, { persist = false, mode = null, scope = null } = {}) {
    if (persist && scope) {
        writeExplicitLayout(scope, layout, { cookieDomain: scope.cookieDomain });
        root.setAttribute('data-collection-layout', layout);
        root.setAttribute('data-collection-layout-explicit', '1');
    } else if (mode) {
        root.setAttribute('data-collection-layout', mode);
    }

    root.setAttribute('data-collection-effective-layout', layout);
    syncToggle(root, layout);
    syncHiddenInputs(root, layout);
}

function initCollection(root) {
    if (root.dataset.collectionLayoutReady === '1') {
        return;
    }
    root.dataset.collectionLayoutReady = '1';

    const scope = collectionScope(root);
    const fromQuery = readViewQueryParam();
    const fromStore = readExplicitLayout(scope);
    const modeAttr = root.getAttribute('data-collection-layout') || 'auto';
    const serverExplicit = normalizeLayout(modeAttr);

    let mode = 'auto';
    let layout = viewportDefault();

    if (fromQuery) {
        writeExplicitLayout(scope, fromQuery, { cookieDomain: scope.cookieDomain });
        mode = fromQuery;
        layout = fromQuery;
        stripViewQueryParam();
    } else if (fromStore) {
        mode = fromStore;
        layout = fromStore;
    } else if (serverExplicit) {
        mode = serverExplicit;
        layout = serverExplicit;
        writeExplicitLayout(scope, serverExplicit, { cookieDomain: scope.cookieDomain });
    }

    root.setAttribute('data-collection-layout-explicit', mode === 'auto' ? '0' : '1');
    applyLayout(root, layout, { persist: false, mode });

    root.querySelectorAll('[data-collection-layout-option]').forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            const next = normalizeLayout(btn.getAttribute('data-collection-layout-option'));
            if (!next) {
                return;
            }
            applyLayout(root, next, { persist: true, scope });
        });
    });

    const media = window.matchMedia(MD_UP);
    const onViewportChange = () => {
        if (root.getAttribute('data-collection-layout-explicit') === '1') {
            applyLayout(root, root.getAttribute('data-collection-layout') === 'cards' ? 'cards' : 'list', {
                persist: false,
                mode: root.getAttribute('data-collection-layout'),
            });
            return;
        }
        applyLayout(root, viewportDefault(), { persist: false, mode: 'auto' });
    };
    if (typeof media.addEventListener === 'function') {
        media.addEventListener('change', onViewportChange);
    } else if (typeof media.addListener === 'function') {
        media.addListener(onViewportChange);
    }
}

export function initCollectionLayout(root = document) {
    root.querySelectorAll('[data-collection]').forEach((node) => initCollection(node));
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initCollectionLayout());
    } else {
        initCollectionLayout();
    }
}
