/**
 * ARK v2 operational workspace tabs — entity multitasking (desktop).
 * Behavior ported from ARK-SMS workspace-manager.js; clean ES module implementation.
 */

import {
    appendIntakeWorkspaceId,
    createIntakeWorkspaceId,
    hasIncomingCallPhoneParam,
    INTAKE_LAUNCHER_ENTITY_ID,
    isExplicitIntakeResetRoute,
    intakeWorkspaceIdFromSearch,
    isValidIntakeWorkspaceId,
    prepareIntakeNavigation,
    shouldSkipIntakePersist,
} from './ark-intake-workspace-memory';
import {
    keysToEvictForNewTab,
    pickAdjacentNeighborKey,
    pickOldestInactiveEvictionCandidate,
} from './ark-workspace-tab-eviction';

const cfg = typeof window !== 'undefined' ? window.__ARK_WORKSPACE__ : null;

const CONFIG_MAX_TABS = Math.max(1, cfg?.maxTabs || 12);
const TAB_MIN_WIDTH = Math.max(160, cfg?.tabMinWidth || 176);
const TAB_GAP = 4;
const TAB_SCROLL_PAD = 8;
const DESKTOP_MIN = cfg?.desktopMinWidth || 1024;
const STORAGE_KEY = cfg?.storageKey || 'ark_ws_v2_guest';
const STORAGE_VERSION = 2;
const SCROLL_PREFIX = `${STORAGE_KEY}_scroll_`;
const CONTEXT_PREFIX = `${STORAGE_KEY}_ctx_`;
const DIRTY_PREFIX = `${STORAGE_KEY}_dirty_`;
const ACTIVITY_POLL_MS = 60_000;
const INTAKE_WORKSPACE_KEY = 'intake:service';

const excludedWorkspaceKeys = () => new Set(
    (Array.isArray(cfg?.excludedWorkspaceKeys) ? cfg.excludedWorkspaceKeys : ['report:operations'])
        .map((key) => String(key)),
);

function isExcludedWorkspaceKey(key) {
    return excludedWorkspaceKeys().has(String(key || ''));
}

function pruneExcludedWorkspaceTabs() {
    const excluded = excludedWorkspaceKeys();
    if (excluded.size === 0) {
        return;
    }

    const before = state.tabs.length;
    state.tabs = state.tabs.filter((tab) => !excluded.has(tab.key));

    if (state.activeKey && excluded.has(state.activeKey)) {
        state.activeKey = state.tabs.length > 0 ? state.tabs[state.tabs.length - 1].key : null;
    }

    if (state.tabs.length !== before) {
        saveState();
    }
}

function workspaceKeepLockSvg(locked = false) {
    const body = locked
        ? '<path d="M5.25 9.25h9.5a1 1 0 011 1v5.5a1 1 0 01-1 1h-9.5a1 1 0 01-1-1v-5.5a1 1 0 011-1z" fill="currentColor" stroke="currentColor" stroke-width="1.15"/>'
        : '<path d="M5.25 9.25h9.5a1 1 0 011 1v5.5a1 1 0 01-1 1h-9.5a1 1 0 01-1-1v-5.5a1 1 0 011-1z" stroke="currentColor" stroke-width="1.55"/>';

    const shackle = locked
        ? '<path d="M6.5 9.25V6.75a3.5 3.5 0 117 0v2.5" stroke="currentColor" stroke-width="1.55" stroke-linecap="round"/>'
        : '<path d="M6.5 9.25V6.75a3.5 3.5 0 017 0" stroke="currentColor" stroke-width="1.55" stroke-linecap="round"/>';

    return `<svg class="ops-workspace-lock" viewBox="0 0 20 20" fill="none" aria-hidden="true">${shackle}${body}</svg>`;
}

/** @type {{ activeKey: string|null, tabs: object[] }} */
const state = {
    activeKey: null,
    tabs: [],
};

let barEl = null;
let draggingTabKey = null;
let activityTimer = null;
let activityPollTimer = null;

function contextStorageKey(key) {
    return CONTEXT_PREFIX + key;
}

function isEnabled() {
    return !!(cfg && cfg.enabled);
}

function isDesktop() {
    return window.matchMedia(`(min-width: ${DESKTOP_MIN}px)`).matches;
}

function saveState() {
    if (!cfg?.persistLocal) {
        return;
    }

    try {
        localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify({
                version: STORAGE_VERSION,
                activeKey: state.activeKey,
                tabs: state.tabs,
            }),
        );
    } catch {
        /* ignore */
    }
}

function clearPersistedWorkspaceState() {
    try {
        localStorage.removeItem(STORAGE_KEY);
        Object.keys(localStorage).forEach((key) => {
            if (key.startsWith(SCROLL_PREFIX) || key.startsWith(DIRTY_PREFIX)) {
                localStorage.removeItem(key);
            }
        });
        Object.keys(sessionStorage).forEach((key) => {
            if (key.startsWith(DIRTY_PREFIX)) {
                sessionStorage.removeItem(key);
            }
        });
    } catch {
        /* ignore */
    }
}

function loadState() {
    if (!cfg?.persistLocal) {
        return;
    }

    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) {
            return;
        }

        const parsed = JSON.parse(raw);
        if (!parsed || !Array.isArray(parsed.tabs)) {
            clearPersistedWorkspaceState();

            return;
        }

        if (parsed.version != null && Number(parsed.version) > STORAGE_VERSION) {
            clearPersistedWorkspaceState();

            return;
        }

        state.tabs = parsed.tabs.map((tab) => sanitizeTab(tab, { restored: true })).filter(Boolean).slice(0, CONFIG_MAX_TABS);
        pruneExcludedWorkspaceTabs();
        state.activeKey = parsed.activeKey || null;

        if (state.activeKey && !findTab(state.activeKey)) {
            state.activeKey = state.tabs.length ? state.tabs[0].key : null;
        }

        ensureDockTabOrdering();
    } catch {
        clearPersistedWorkspaceState();
    }
}

function normalizeStoredRoute(entityType, entityId, route) {
    if (entityType === 'repair_order' && entityId) {
        return normalizeRepairOrderRoute(entityId, route);
    }

    if (entityType === 'intake') {
        return normalizeIntakeRoute(route, entityId);
    }

    if (entityType === 'report' && entityId) {
        return normalizeReportRoute(entityId, route);
    }

    return route ? String(route) : '';
}

function makeKey(type, id) {
    return `${String(type)}:${String(id)}`;
}

function defaultTitle(type, id) {
    switch (type) {
        case 'repair_order':
            return `RO #${id}`;
        case 'customer':
            return `Customer #${id}`;
        case 'vehicle':
            return `Vehicle #${id}`;
        case 'intake':
            return 'Check In';
        case 'inbox':
            return `Inbox #${id}`;
        case 'report':
            return id === 'operations' ? 'Operations' : `Report: ${id}`;
        default:
            return `${type} #${id}`;
    }
}

function sanitizeActivity(raw) {
    if (!raw || typeof raw !== 'object') {
        return undefined;
    }

    const out = {};

    if (typeof raw.unread === 'number' && raw.unread > 0) {
        out.unread = raw.unread;
    }

    if (raw.urgency === 'low' || raw.urgency === 'medium' || raw.urgency === 'high') {
        out.urgency = raw.urgency;
    }

    if (raw.stale) {
        out.stale = true;
    }

    return Object.keys(out).length ? out : undefined;
}

function sanitizeSeen(raw) {
    if (!raw || typeof raw !== 'object') {
        return undefined;
    }

    const out = {};

    if (typeof raw.estimateVersion === 'number' && raw.estimateVersion > 0) {
        out.estimateVersion = raw.estimateVersion;
    } else if (raw.estimateVersion != null) {
        const version = Number.parseInt(String(raw.estimateVersion), 10);
        if (!Number.isNaN(version) && version > 0) {
            out.estimateVersion = version;
        }
    }

    if (typeof raw.movementAt === 'string' && raw.movementAt !== '') {
        out.movementAt = raw.movementAt;
    }

    if (typeof raw.operationalState === 'string' && raw.operationalState !== '') {
        out.operationalState = raw.operationalState;
    }

    return Object.keys(out).length ? out : undefined;
}

function captureSeenSignalsFromPage(key) {
    if (isRepairOrderKey(key)) {
        const input = document.querySelector('input[name="opened_estimate_version"]');
        const version = Number.parseInt(String(input?.value ?? ''), 10);

        if (!Number.isNaN(version) && version > 0) {
            return { estimateVersion: version };
        }
    }

    const boot = cfg?.boot;
    if (boot?.key === key && boot.signals) {
        return sanitizeSeen(boot.signals);
    }

    return null;
}

function markActiveTabSeen(signals) {
    const tab = findTab(state.activeKey);
    if (!tab) {
        return;
    }

    const pageSeen = captureSeenSignalsFromPage(tab.key);
    tab.seen = sanitizeSeen({ ...tab.seen, ...signals, ...pageSeen });
    tab.activity = undefined;
}

function patchActivity(key, activity) {
    const tab = findTab(key);
    if (!tab) {
        return;
    }

    tab.activity = sanitizeActivity(activity);
    saveState();
    renderBar();
}

function applyActivityPatches(patches) {
    if (!Array.isArray(patches) || patches.length === 0) {
        return;
    }

    let changed = false;

    patches.forEach((patch) => {
        const tab = findTab(String(patch?.key || ''));
        if (!tab) {
            return;
        }

        tab.activity = sanitizeActivity(patch.activity);
        changed = true;
    });

    if (changed) {
        saveState();
        renderBar();
    }
}

function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');

    if (meta?.content) {
        return meta.content;
    }

    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function refreshWorkspaceActivity() {
    if (!cfg?.activityUrl || !isDesktop() || state.tabs.length < 2) {
        return;
    }

    try {
        const response = await fetch(cfg.activityUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                activeKey: state.activeKey,
                tabs: state.tabs.map((tab) => ({
                    key: tab.key,
                    seen: tab.seen || {},
                })),
            }),
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        applyActivityPatches(data.patches || []);
    } catch {
        /* ignore */
    }
}

function scheduleActivityRefresh() {
    if (activityTimer) {
        window.clearTimeout(activityTimer);
    }

    activityTimer = window.setTimeout(() => {
        activityTimer = null;
        refreshWorkspaceActivity();
    }, 300);
}

function activitySignalMarkup(tab) {
    const unread = tab.activity?.unread || 0;

    if (unread <= 0) {
        return '';
    }

    const dots = unread >= 2 ? '••' : '•';

    return `<span class="ops-workspace-tab__signal" title="Something changed">${dots}</span>`;
}

function normalizeRoute(route) {
    try {
        const url = new URL(route, window.location.origin);

        return url.pathname + url.search;
    } catch {
        return route;
    }
}

function normalizeRepairOrderRoute(entityId, route) {
    const fallback = `/app/repair-orders/${entityId}`;

    if (!route) {
        return fallback;
    }

    try {
        const url = new URL(String(route), window.location.origin);
        const match = url.pathname.match(/^\/app\/repair-orders\/(\d+)(?:\/edit|\/estimate-review)?$/);

        if (match && match[1] === String(entityId)) {
            // Working-set tabs always land on canonical RO show — authoring lives in workspace modals.
            return fallback;
        }
    } catch {
        /* ignore */
    }

    return fallback;
}

function normalizeIntakeRoute(route, entityId = null) {
    const fallback = '/app/intake/new';

    if (!route) {
        return entityId && isValidIntakeWorkspaceId(entityId)
            ? appendIntakeWorkspaceId(fallback, entityId)
            : fallback;
    }

    try {
        const url = new URL(String(route), window.location.origin);

        if (url.pathname === '/app/intake/new' || url.pathname === '/app/intake') {
            if (entityId && isValidIntakeWorkspaceId(entityId)) {
                url.pathname = '/app/intake/new';
                url.searchParams.set('ws', entityId);
            }

            return url.pathname + url.search;
        }
    } catch {
        /* ignore */
    }

    return entityId && isValidIntakeWorkspaceId(entityId)
        ? appendIntakeWorkspaceId(fallback, entityId)
        : fallback;
}

function normalizeReportRoute(entityId, route) {
    const fallback = `/app/reports/${entityId}`;

    if (!route) {
        return fallback;
    }

    try {
        const url = new URL(String(route), window.location.origin);
        const match = url.pathname.match(/^\/app\/reports\/([^/]+)$/);

        if (match && match[1] === String(entityId)) {
            return url.pathname + url.search;
        }
    } catch {
        /* ignore */
    }

    return fallback;
}

function parseEntityFromLocation(loc) {
    const path = (loc.pathname || '').replace(/^\//, '');
    const patterns = cfg?.pathPatterns || [];

    for (const rule of patterns) {
        if (!rule?.pattern) {
            continue;
        }

        let pat = String(rule.pattern);
        if (pat.startsWith('#')) {
            pat = pat.slice(1, pat.lastIndexOf('#'));
        }

        const match = path.match(new RegExp(pat));
        if (!match) {
            continue;
        }

        if (rule.type === 'intake') {
            const params = new URLSearchParams(loc.search || '');
            const wsId = params.get('ws');
            const id = isValidIntakeWorkspaceId(wsId) ? wsId : INTAKE_LAUNCHER_ENTITY_ID;

            return {
                type: 'intake',
                id,
                key: makeKey('intake', id),
                route: loc.pathname + loc.search,
            };
        }

        const idGroup = rule.id_group || 1;
        const id = match[idGroup];
        if (!id) {
            continue;
        }

        if (rule.type === 'customer') {
            const params = new URLSearchParams(loc.search || '');
            const vehicleId = params.get('vehicle');
            if (vehicleId) {
                return {
                    type: 'vehicle',
                    id: String(vehicleId),
                    key: makeKey('vehicle', vehicleId),
                    route: loc.pathname + loc.search,
                };
            }

            const hashMatch = (loc.hash || '').match(/^#vehicle-(\d+)$/);
            if (hashMatch) {
                return {
                    type: 'vehicle',
                    id: String(hashMatch[1]),
                    key: makeKey('vehicle', hashMatch[1]),
                    route: `${loc.pathname}?vehicle=${hashMatch[1]}`,
                };
            }
        }

        return {
            type: rule.type,
            id: String(id),
            key: makeKey(rule.type, id),
            route: loc.pathname + loc.search,
        };
    }

    return null;
}

function findTab(key) {
    return state.tabs.find((tab) => tab.key === key) || null;
}

function touchTab(key) {
    const now = Date.now();
    const tab = findTab(key);
    if (tab) {
        tab.focusedAt = now;
        tab.lastActiveAt = now;
    }
}

function sanitizeTab(tab, meta = {}) {
    if (!tab) {
        return null;
    }

    const entityType = String(tab.entityType || tab.type || '');
    const entityId = String(tab.entityId ?? tab.id ?? '');
    const route = normalizeStoredRoute(entityType, entityId, tab.route ? String(tab.route) : '');
    const key = tab.key ? String(tab.key) : entityType && entityId ? makeKey(entityType, entityId) : '';

    if (!key || !entityType || !entityId || !route) {
        return null;
    }

    const focusedAt = Number(tab.focusedAt || tab.lastActiveAt || 0);
    const openedAt = Number(tab.openedAt || focusedAt || Date.now());

    return {
        key,
        entityType,
        entityId,
        type: entityType,
        id: entityId,
        route,
        title: tabTitleForKey(key, entityType, entityId, tab.title),
        subtitle: String(tab.subtitle || ''),
        customerName: String(tab.customerName || tab.customer_name || ''),
        dirty: !!tab.dirty,
        activity: sanitizeActivity(tab.activity),
        seen: sanitizeSeen(tab.seen),
        openedAt,
        focusedAt,
        lastActiveAt: focusedAt,
        restored: !!(meta.restored || tab.restored),
        pinned: isLeftDockKey(key) ? true : (isRightDockKey(key) ? false : !!tab.pinned),
    };
}

function upsertTab(tab) {
    const clean = sanitizeTab(tab);
    if (!clean) {
        return null;
    }

    const existing = findTab(clean.key);
    const now = Date.now();

    if (existing) {
        existing.route = clean.route;
        if (clean.title) {
            existing.title = tabTitleForKey(clean.key, clean.entityType, clean.entityId, clean.title);
        }
        if (clean.subtitle) {
            existing.subtitle = clean.subtitle;
        }
        if (clean.customerName) {
            existing.customerName = clean.customerName;
        }
        if (clean.activity) {
            existing.activity = clean.activity;
        }
        if (clean.seen) {
            existing.seen = sanitizeSeen({ ...existing.seen, ...clean.seen });
        }
        if (isLeftDockKey(clean.key)) {
            existing.pinned = true;
        } else if (isRightDockKey(clean.key)) {
            existing.pinned = false;
        }
        existing.focusedAt = now;
        existing.lastActiveAt = now;

        return existing;
    }

    clean.openedAt = clean.openedAt || now;
    clean.focusedAt = now;
    clean.lastActiveAt = now;
    clean.pinned = isLeftDockKey(clean.key)
        ? true
        : (isRightDockKey(clean.key) ? false : !!clean.pinned);
    state.tabs.push(clean);
    ensureDockTabOrdering();

    return clean;
}

function ensurePermanentPinnedTabs() {
    if (!Array.isArray(cfg?.permanentPinned)) {
        return;
    }

    cfg.permanentPinned.forEach((spec) => {
        const key = String(spec.key);
        const existing = findTab(key);

        if (existing) {
            existing.pinned = true;
            existing.title = tabTitleForKey(
                key,
                String(spec.entityType),
                String(spec.entityId),
                String(spec.title || existing.title),
            );

            return;
        }

        upsertTab({
            key,
            entityType: String(spec.entityType),
            entityId: String(spec.entityId),
            route: String(spec.route),
            title: String(spec.title || defaultTitle(spec.entityType, spec.entityId)),
            subtitle: String(spec.subtitle || ''),
            pinned: true,
        });
    });
}

function ensureDockedContextualTabs() {
    if (!Array.isArray(cfg?.dockedContextual)) {
        return;
    }

    cfg.dockedContextual.forEach((spec) => {
        const key = String(spec.key);
        const existing = findTab(key);

        if (existing) {
            existing.pinned = false;
            existing.title = tabTitleForKey(
                key,
                String(spec.entityType),
                String(spec.entityId),
                String(spec.title || existing.title),
            );

            return;
        }

        upsertTab({
            key,
            entityType: String(spec.entityType),
            entityId: String(spec.entityId),
            route: String(spec.route),
            title: String(spec.title || defaultTitle(spec.entityType, spec.entityId)),
            subtitle: String(spec.subtitle || ''),
            pinned: false,
        });
    });
}

function ensureDockTabs() {
    ensurePermanentPinnedTabs();
    ensureDockedContextualTabs();
    ensureDockTabOrdering();
}

function ensureDockTabOrdering() {
    const leftDock = (cfg?.permanentPinned || [])
        .map((spec) => findTab(String(spec.key)))
        .filter(Boolean);
    const rightDock = (cfg?.dockedContextual || [])
        .map((spec) => findTab(String(spec.key)))
        .filter(Boolean);
    const dockedKeys = new Set([
        ...leftDock.map((tab) => tab.key),
        ...rightDock.map((tab) => tab.key),
    ]);
    const rest = state.tabs.filter((tab) => !dockedKeys.has(tab.key));

    leftDock.forEach((tab) => {
        tab.pinned = true;
    });

    rightDock.forEach((tab) => {
        tab.pinned = false;
    });

    state.tabs = [...leftDock, ...rest, ...rightDock];
}

function getBarAvailableWidth() {
    if (barEl) {
        return Math.max(0, barEl.getBoundingClientRect().width - TAB_SCROLL_PAD);
    }

    const sidebar = 56;

    return Math.max(0, window.innerWidth - sidebar - TAB_SCROLL_PAD);
}

function getEffectiveMaxTabs() {
    return CONFIG_MAX_TABS;
}

function openWorkspaceTabCount() {
    return state.tabs.filter((tab) => !isDockedTabKey(tab.key)).length;
}

function isAtOpenTabLimit() {
    return openWorkspaceTabCount() >= CONFIG_MAX_TABS;
}

function makeRoomForNewTab(excludeKeys = []) {
    const removeKeys = keysToEvictForNewTab({
        tabs: state.tabs,
        activeKey: state.activeKey,
        excludeKeys,
        maxOpen: CONFIG_MAX_TABS,
        isDocked: isDockedTabKey,
        isDirty: keyHasUnsavedChanges,
    });

    removeKeys.forEach((key) => {
        removeTab(key);
    });

    return removeKeys;
}

function getRightDockReserve(tabCount) {
    if (tabCount <= 0) {
        return 0;
    }

    const slotWidth = TAB_MIN_WIDTH + TAB_GAP;

    return tabCount * slotWidth + 9;
}

function partitionTabsForDisplay(tabs = state.tabs) {
    const rightDock = tabs.filter((tab) => isRightDockKey(tab.key));
    const leftDock = tabs.filter((tab) => isLeftDockKey(tab.key));
    // Preserve state.tabs order — do not resort by focus/recency (tabs stay put when activated).
    const middle = tabs.filter((tab) => !isLeftDockKey(tab.key) && !isRightDockKey(tab.key));

    leftDock.sort((a, b) => leftDockSlotOrder(a.key) - leftDockSlotOrder(b.key));
    rightDock.sort((a, b) => rightDockSlotOrder(a.key) - rightDockSlotOrder(b.key));

    return {
        pinned: leftDock,
        rightDock,
        visible: middle,
        overflow: [],
    };
}

function isRepairOrderKey(key) {
    return String(key || '').startsWith('repair_order:');
}

function isIntakeKey(key) {
    return String(key || '').startsWith('intake:');
}

function isConfiguredPermanentPinnedKey(key) {
    return Array.isArray(cfg?.permanentPinned)
        && cfg.permanentPinned.some((spec) => String(spec.key) === String(key || ''));
}

function isRightDockKey(key) {
    if (Array.isArray(cfg?.dockedContextual)) {
        return cfg.dockedContextual.some((spec) => String(spec.key) === String(key || ''));
    }

    return isIntakeKey(key);
}

function isLeftDockKey(key) {
    return isConfiguredPermanentPinnedKey(key);
}

function isDockedTabKey(key) {
    return isLeftDockKey(key) || isRightDockKey(key);
}

function leftDockSlotOrder(key) {
    if (Array.isArray(cfg?.permanentPinned)) {
        const index = cfg.permanentPinned.findIndex((spec) => String(spec.key) === String(key));

        if (index >= 0) {
            return index;
        }
    }

    return 100;
}

function rightDockSlotOrder(key) {
    if (Array.isArray(cfg?.dockedContextual)) {
        const index = cfg.dockedContextual.findIndex((spec) => String(spec.key) === String(key));

        if (index >= 0) {
            return index;
        }
    }

    if (isIntakeKey(key)) {
        return 0;
    }

    return 100;
}

function tabTitleForKey(key, entityType, entityId, fallbackTitle = '') {
    if (isIntakeKey(key) && !isDockedTabKey(key)) {
        const title = String(fallbackTitle || '').trim();

        if (title && title !== 'Check In') {
            return title;
        }

        if (/^Check In \(\d+\)$/.test(title)) {
            return title;
        }

        return 'Check In';
    }

    if (isConfiguredPermanentPinnedKey(key)) {
        const spec = cfg.permanentPinned.find((entry) => String(entry.key) === String(key));

        if (spec?.title) {
            return String(spec.title);
        }
    }

    return String(fallbackTitle || defaultTitle(entityType, entityId));
}

function saveContextForKey(key) {
    if (!key) {
        return;
    }

    const payload = {
        scrollX: window.scrollX || 0,
        scrollY: window.scrollY || 0,
        href: window.location.pathname + window.location.search + window.location.hash,
    };

    if (isRepairOrderKey(key)) {
        if (window.ARK?.roWorkspaceMemory?.persist) {
            window.ARK.roWorkspaceMemory.persist();
        }

        const tab = findTab(key);
        if (tab) {
            const pageSeen = captureSeenSignalsFromPage(key);
            if (pageSeen) {
                tab.seen = sanitizeSeen({ ...tab.seen, ...pageSeen });
            }

            tab.route = normalizeRepairOrderRoute(
                key.split(':')[1],
                window.location.pathname + window.location.search,
            );
            saveState();
        }

        return;
    }

    if (isIntakeKey(key)) {
        if (!shouldSkipIntakePersist() && window.ARK?.intakeWorkspaceMemory?.persist) {
            window.ARK.intakeWorkspaceMemory.persist();
        }

        const tab = findTab(key);
        if (tab) {
            tab.route = normalizeIntakeRoute(window.location.pathname + window.location.search, key.split(':')[1]);
            tab.pinned = false;
            saveState();
        }

        return;
    }

    if (key.startsWith('vehicle:')) {
        payload.vehicleAnchor = `vehicle-${key.split(':')[1]}`;
    }

    if (key.startsWith('customer:')) {
        payload.openDetails = [...document.querySelectorAll('details[open]')].map((el) => el.id).filter(Boolean);
    }

    try {
        sessionStorage.setItem(SCROLL_PREFIX + key, JSON.stringify({
            x: payload.scrollX,
            y: payload.scrollY,
        }));
        sessionStorage.setItem(contextStorageKey(key), JSON.stringify(payload));
    } catch {
        /* ignore */
    }
}

function restoreContextForKey(key) {
    if (!key || isRepairOrderKey(key) || isIntakeKey(key)) {
        return;
    }

    const runRestore = () => {
        try {
            const scrollRaw = sessionStorage.getItem(SCROLL_PREFIX + key);
            const ctxRaw = sessionStorage.getItem(contextStorageKey(key));

            if (key.startsWith('vehicle:')) {
                const vehicleId = key.split(':')[1];
                const anchor = document.getElementById(`vehicle-${vehicleId}`);
                if (anchor) {
                    anchor.scrollIntoView({ block: 'start', behavior: 'instant' });

                    return;
                }
            }

            if (scrollRaw) {
                const pos = JSON.parse(scrollRaw);
                if (pos && typeof pos.y === 'number') {
                    window.scrollTo(pos.x || 0, pos.y);
                }
            }

            if (ctxRaw) {
                const ctx = JSON.parse(ctxRaw);
                if (Array.isArray(ctx.openDetails)) {
                    ctx.openDetails.forEach((id) => {
                        const el = document.getElementById(id);
                        if (el && el.tagName === 'DETAILS') {
                            el.open = true;
                        }
                    });
                }
            }
        } catch {
            /* ignore */
        }
    };

    requestAnimationFrame(() => requestAnimationFrame(runRestore));
}

function isDirtyKey(key) {
    try {
        return sessionStorage.getItem(DIRTY_PREFIX + key) === '1';
    } catch {
        return false;
    }
}

/**
 * Prefer live form comparison for the active tab so sticky session flags
 * do not scare advisors after they revert edits.
 */
function keyHasUnsavedChanges(key) {
    if (!key) {
        return false;
    }

    if (key === state.activeKey && typeof window.ARK?.workspace?.syncDirty === 'function') {
        return !!window.ARK.workspace.syncDirty();
    }

    return isDirtyKey(key);
}

function setDirtyKey(key, dirty) {
    if (!key) {
        return;
    }

    const next = !!dirty;
    const previous = isDirtyKey(key);

    try {
        if (next) {
            sessionStorage.setItem(DIRTY_PREFIX + key, '1');
        } else {
            sessionStorage.removeItem(DIRTY_PREFIX + key);
        }
    } catch {
        /* ignore */
    }

    const tab = findTab(key);
    if (tab) {
        const tabChanged = !!tab.dirty !== next;
        tab.dirty = next;
        if (tabChanged || previous !== next) {
            saveState();
            renderBar();
        }
    }

    if (previous !== next) {
        document.dispatchEvent(new CustomEvent('ark:workspace-dirty', { detail: { key, dirty: next }, bubbles: true }));
    }
}

function removeTab(key) {
    state.tabs = state.tabs.filter((tab) => tab.key !== key);

    try {
        sessionStorage.removeItem(SCROLL_PREFIX + key);
        sessionStorage.removeItem(CONTEXT_PREFIX + key);
        sessionStorage.removeItem(DIRTY_PREFIX + key);
    } catch {
        /* ignore */
    }
}

function escapeHtml(value) {
    const el = document.createElement('div');
    el.textContent = value == null ? '' : String(value);

    return el.innerHTML;
}

function tabTooltip(tab) {
    const parts = [tab.title].filter(Boolean);
    const customerName = String(tab.customerName || '').trim();
    const subtitle = String(tab.subtitle || '').trim();

    if (tab.entityType === 'repair_order') {
        if (customerName && customerName !== subtitle) {
            parts.push(customerName);
        }
        if (subtitle && subtitle !== customerName) {
            parts.push(subtitle);
        }
    } else if (subtitle && subtitle !== tab.title) {
        parts.push(subtitle);
    }

    return parts.join(' — ');
}

function iconForType(type) {
    switch (type) {
        case 'repair_order':
            return 'RO';
        case 'customer':
            return 'CU';
        case 'vehicle':
            return 'VH';
        case 'inbox':
            return 'IN';
        default:
            return 'WS';
    }
}

function confirmClose(tab, key, onConfirm) {
    const dirty = keyHasUnsavedChanges(key);

    if (!dirty) {
        onConfirm();

        return;
    }

    if (window.confirm('This workspace has unsaved changes. Close without saving?')) {
        onConfirm();
    }
}

function confirmNavigateAway(fromKey, onConfirm) {
    if (!fromKey || !keyHasUnsavedChanges(fromKey)) {
        onConfirm();

        return;
    }

    const tab = findTab(fromKey);
    const label = tab?.title ? `"${tab.title}"` : 'This workspace';

    if (window.confirm(`${label} has unsaved changes. Switch without saving?`)) {
        onConfirm();
    }
}

function navigateTo(route, key, samePage, departingKey = null, options = {}) {
    prepareIntakeNavigation(route);

    const fromKey = departingKey ?? state.activeKey;
    const skipDirtyConfirm = !!options.skipDirtyConfirm;
    const freshIntake = !!options.freshIntake;

    if (fromKey) {
        saveContextForKey(fromKey);
    }

    if (samePage && !(freshIntake && isIntakeKey(key))) {
        if (isIntakeKey(key) && isExplicitIntakeResetRoute(route)) {
            state.activeKey = key;
            saveState();
            renderBar();
            window.location.assign(route);

            return;
        }

        touchTab(key);
        state.activeKey = key;
        saveState();
        renderBar();
        restoreContextForKey(key);

        return;
    }

    if (key && isIntakeKey(key)) {
        const tab = findTab(key);
        if (tab) {
            tab.route = normalizeIntakeRoute(route, key.split(':')[1]);
            tab.pinned = false;
            saveState();
        }
    } else if (fromKey && isIntakeKey(fromKey)) {
        const tab = findTab(fromKey);
        if (tab) {
            tab.route = normalizeIntakeRoute(window.location.pathname + window.location.search, fromKey.split(':')[1]);
            tab.pinned = false;
            saveState();
        }
    }

    const performNavigate = () => {
        state.activeKey = key;
        saveState();
        window.location.assign(route);
    };

    if (!skipDirtyConfirm && !freshIntake && fromKey && fromKey !== key && keyHasUnsavedChanges(fromKey)) {
        confirmNavigateAway(fromKey, performNavigate);

        return;
    }

    performNavigate();
}

function closeOverflowMenus() {
    if (!barEl) {
        return;
    }

    barEl.querySelectorAll('.ops-workspace-tabs__overflow-menu.is-open').forEach((menu) => menu.classList.remove('is-open'));
    barEl.querySelectorAll('.ops-workspace-tabs__overflow.is-open').forEach((btn) => btn.classList.remove('is-open'));
}

function toggleTabPin(key) {
    if (isDockedTabKey(key)) {
        return;
    }

    const tab = findTab(key);
    if (!tab) {
        return;
    }

    tab.pinned = !tab.pinned;
    saveState();
    renderBar();
}

function reorderTabs(dragKey, targetKey) {
    if (!dragKey || !targetKey || dragKey === targetKey || isDockedTabKey(dragKey) || isDockedTabKey(targetKey)) {
        return;
    }

    const keys = state.tabs.map((tab) => tab.key);
    const fromIndex = keys.indexOf(dragKey);
    const toIndex = keys.indexOf(targetKey);

    if (fromIndex < 0 || toIndex < 0) {
        return;
    }

    keys.splice(fromIndex, 1);
    keys.splice(toIndex, 0, dragKey);

    const map = Object.fromEntries(state.tabs.map((tab) => [tab.key, tab]));
    state.tabs = keys.map((tabKey) => map[tabKey]).filter(Boolean);
    ensureDockTabOrdering();
    saveState();
    renderBar();
}

function attachTabDragHandle(handle, tabKey) {
    if (!handle || isDockedTabKey(tabKey)) {
        return;
    }

    handle.setAttribute('draggable', 'true');

    handle.addEventListener('dragstart', (event) => {
        draggingTabKey = tabKey;
        handle.closest('.ops-workspace-tab')?.classList.add('ops-workspace-tab--dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', tabKey);
    });

    handle.addEventListener('dragend', () => {
        draggingTabKey = null;
        barEl?.querySelectorAll('.ops-workspace-tab--dragging, .ops-workspace-tab--drop-target').forEach((el) => {
            el.classList.remove('ops-workspace-tab--dragging', 'ops-workspace-tab--drop-target');
        });
    });
}

function attachTabDropTarget(tabEl, tabKey) {
    tabEl.addEventListener('dragover', (event) => {
        if (!draggingTabKey || draggingTabKey === tabKey) {
            return;
        }

        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        tabEl.classList.add('ops-workspace-tab--drop-target');
    });

    tabEl.addEventListener('dragleave', () => {
        tabEl.classList.remove('ops-workspace-tab--drop-target');
    });

    tabEl.addEventListener('drop', (event) => {
        event.preventDefault();
        tabEl.classList.remove('ops-workspace-tab--drop-target');
        const dragKey = event.dataTransfer.getData('text/plain') || draggingTabKey;
        reorderTabs(dragKey, tabKey);
    });
}

function createTabElement(tab) {
    const isActive = tab.key === state.activeKey;
    const dirty = tab.dirty || isDirtyKey(tab.key);
    const act = tab.activity || {};
    const isDock = isDockedTabKey(tab.key);
    const isRepairOrder = isRepairOrderKey(tab.key);
    const tabEl = document.createElement('div');
    tabEl.className = [
        'ops-workspace-tab',
        isActive ? 'ops-workspace-tab--active' : '',
        isDock ? 'ops-workspace-tab--dock' : '',
        isLeftDockKey(tab.key) ? 'ops-workspace-tab--dock-left' : '',
        isRightDockKey(tab.key) ? 'ops-workspace-tab--dock-right' : '',
        isIntakeKey(tab.key) ? 'ops-workspace-tab--intake' : '',
        isRepairOrder ? 'ops-workspace-tab--repair-order' : '',
        act.urgency ? `ops-workspace-tab--urgency-${act.urgency}` : '',
        act.stale ? 'ops-workspace-tab--stale' : '',
    ].filter(Boolean).join(' ');
    tabEl.setAttribute('role', 'tab');
    tabEl.setAttribute('tabindex', isActive ? '0' : '-1');
    tabEl.setAttribute('data-workspace-key', tab.key);
    tabEl.setAttribute('aria-selected', isActive ? 'true' : 'false');
    tabEl.setAttribute('aria-current', isActive ? 'page' : 'false');
    tabEl.setAttribute('title', tabTooltip(tab));

    const customerName = String(tab.customerName || '').trim();
    const subtitle = String(tab.subtitle || '').trim();
    let titleHtml = `<span class="ops-workspace-tab__title">${escapeHtml(tab.title)}</span>`;
    let subtitleHtml = '';

    if (!isDock && isRepairOrder) {
        const primary = customerName
            ? `${escapeHtml(tab.entityId)} · ${escapeHtml(customerName)}`
            : escapeHtml(String(tab.title || '').replace(/^RO\s*#/i, '').trim() || tab.entityId);
        titleHtml = `<span class="ops-workspace-tab__title">${primary}</span>`;
        if (subtitle) {
            subtitleHtml = `<span class="ops-workspace-tab__subtitle">${escapeHtml(subtitle)}</span>`;
        }
    } else if (!isDock && subtitle) {
        subtitleHtml = `<span class="ops-workspace-tab__subtitle">${escapeHtml(subtitle)}</span>`;
    }

    tabEl.innerHTML = `
        ${isDock ? '' : `<span class="ops-workspace-tab__icon" data-entity-type="${escapeHtml(tab.entityType || tab.type)}" aria-hidden="true"></span>`}
        <span class="ops-workspace-tab__label${!isDock && (isRepairOrder || subtitleHtml) ? ' ops-workspace-tab__label--stack' : ''}">
            ${titleHtml}
            ${subtitleHtml}
        </span>
        ${dirty ? '<span class="ops-workspace-tab__dirty" title="Unsaved changes">●</span>' : ''}
        ${isDock ? '' : '<button type="button" class="ops-workspace-tab__close" aria-label="Close workspace">&times;</button>'}
    `;

    tabEl.addEventListener('click', (event) => {
        if (event.target.closest('.ops-workspace-tab__close')) {
            return;
        }
        focusWorkspace(tab.key);
    });

    const closeBtn = tabEl.querySelector('.ops-workspace-tab__close');
    if (closeBtn) {
        closeBtn.addEventListener('click', (event) => {
            event.stopPropagation();
            closeWorkspace(tab.key);
        });
    }

    return tabEl;
}

function createOverflowControl(overflowTabs) {
    const wrap = document.createElement('div');
    wrap.className = 'ops-workspace-tabs__overflow-wrap';

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'ops-workspace-tabs__overflow';
    btn.setAttribute('aria-haspopup', 'true');
    btn.textContent = `+${overflowTabs.length}`;

    const menu = document.createElement('ul');
    menu.className = 'ops-workspace-tabs__overflow-menu';
    menu.setAttribute('role', 'menu');

    overflowTabs.forEach((tab) => {
        const item = document.createElement('li');
        item.setAttribute('role', 'none');

        const menuBtn = document.createElement('button');
        menuBtn.type = 'button';
        menuBtn.className = 'ops-workspace-tabs__overflow-item' + (tab.key === state.activeKey ? ' is-active' : '');
        menuBtn.setAttribute('role', 'menuitem');
        menuBtn.setAttribute('title', tabTooltip(tab));
        menuBtn.innerHTML = `
            ${tab.pinned ? `<span class="ops-workspace-tabs__overflow-item-lock">${workspaceKeepLockSvg(true)}</span>` : ''}
            <span class="ops-workspace-tabs__overflow-item-label">${escapeHtml(tab.title)}</span>
            ${(tab.dirty || isDirtyKey(tab.key)) ? '<span class="ops-workspace-tab__dirty">●</span>' : ''}
        `;
        menuBtn.addEventListener('click', (event) => {
            event.stopPropagation();
            closeOverflowMenus();
            focusWorkspace(tab.key);
        });

        item.appendChild(menuBtn);
        menu.appendChild(item);
    });

    btn.addEventListener('click', (event) => {
        event.stopPropagation();
        const willOpen = !menu.classList.contains('is-open');
        closeOverflowMenus();
        if (willOpen) {
            menu.classList.add('is-open');
            btn.classList.add('is-open');
        }
    });

    wrap.appendChild(btn);
    wrap.appendChild(menu);

    return wrap;
}

function syncWorkspaceChromeOffset() {
    const root = document.documentElement;
    const bar = document.getElementById('ops-workspace-tabs');

    if (!bar || !bar.classList.contains('is-visible')) {
        root.style.removeProperty('--ops-workspace-tabs-height');

        return;
    }

    const measured = Math.max(36, Math.ceil(bar.getBoundingClientRect().height) || 0);
    root.style.setProperty('--ops-workspace-tabs-height', `${measured}px`);
}

function renderBar() {
    barEl = document.getElementById('ops-workspace-tabs');
    if (!barEl) {
        return;
    }

    const previousScrollLeft = barEl.querySelector('.ops-workspace-tabs__scroll')?.scrollLeft ?? 0;

    if (!isDesktop() || state.tabs.length === 0) {
        barEl.setAttribute('aria-hidden', 'true');
        barEl.classList.remove('is-visible');
        document.body.classList.remove('ops-workspace-tabs-active');
        barEl.innerHTML = '';
        syncWorkspaceChromeOffset();

        return;
    }

    barEl.setAttribute('aria-hidden', 'false');
    barEl.classList.add('is-visible');
    document.body.classList.add('ops-workspace-tabs-active');

    const parts = partitionTabsForDisplay();
    const scroll = document.createElement('div');
    scroll.className = 'ops-workspace-tabs__scroll';
    scroll.setAttribute('role', 'tablist');

    if (parts.pinned.length > 0) {
        const pinnedZone = document.createElement('div');
        pinnedZone.className = 'ops-workspace-tabs__pinned-zone';
        parts.pinned.forEach((tab) => pinnedZone.appendChild(createTabElement(tab)));
        scroll.appendChild(pinnedZone);
    }

    if (parts.pinned.length > 0 && (parts.visible.length > 0 || parts.overflow.length > 0)) {
        const divider = document.createElement('div');
        divider.className = 'ops-workspace-tabs__divider';
        divider.setAttribute('aria-hidden', 'true');
        scroll.appendChild(divider);
    }

    if (parts.visible.length > 0) {
        const contextualZone = document.createElement('div');
        contextualZone.className = 'ops-workspace-tabs__contextual-zone';
        parts.visible.forEach((tab) => contextualZone.appendChild(createTabElement(tab)));
        scroll.appendChild(contextualZone);
    }

    if (parts.overflow.length > 0) {
        scroll.appendChild(createOverflowControl(parts.overflow));
    }

    barEl.innerHTML = '';
    barEl.appendChild(scroll);

    if (parts.rightDock.length > 0) {
        const rightRail = document.createElement('div');
        rightRail.className = 'ops-workspace-tabs__right-rail';

        const hasLeftTabs = parts.pinned.length > 0 || parts.visible.length > 0 || parts.overflow.length > 0;

        if (hasLeftTabs) {
            const divider = document.createElement('div');
            divider.className = 'ops-workspace-tabs__divider ops-workspace-tabs__divider--rail';
            divider.setAttribute('aria-hidden', 'true');
            rightRail.appendChild(divider);
        }

        const rightZone = document.createElement('div');
        rightZone.className = 'ops-workspace-tabs__right-zone';
        rightZone.setAttribute('role', 'presentation');
        parts.rightDock.forEach((tab) => rightZone.appendChild(createTabElement(tab)));
        rightRail.appendChild(rightZone);
        barEl.appendChild(rightRail);
    }

    const scrollEl = barEl.querySelector('.ops-workspace-tabs__scroll');
    if (scrollEl) {
        ensureScrollWheel(scrollEl);
        scrollEl.scrollLeft = previousScrollLeft;
        const activeTabEl = state.activeKey
            ? scrollEl.querySelector(`.ops-workspace-tab[data-workspace-key="${CSS.escape(state.activeKey)}"]`)
            : null;
        if (activeTabEl && typeof activeTabEl.scrollIntoView === 'function') {
            activeTabEl.scrollIntoView({ inline: 'nearest', block: 'nearest', behavior: 'instant' });
        }
    }

    syncWorkspaceChromeOffset();
}

function ensureScrollWheel(scrollEl) {
    if (!scrollEl || scrollEl.dataset.arkWheelBound === '1') {
        return;
    }

    scrollEl.dataset.arkWheelBound = '1';
    scrollEl.addEventListener('wheel', (event) => {
        if (scrollEl.scrollWidth <= scrollEl.clientWidth) {
            return;
        }

        if (Math.abs(event.deltaY) <= Math.abs(event.deltaX)) {
            return;
        }

        event.preventDefault();
        scrollEl.scrollLeft += event.deltaY;
    }, { passive: false });
}

function normalizeOpenOpts(opts) {
    if (!opts) {
        return opts;
    }

    const entityType = String(opts.entityType || opts.type || '');
    const entityId = String(opts.entityId ?? opts.id ?? '');

    if (entityType && entityId) {
        opts.entityType = entityType;
        opts.entityId = entityId;
        opts.type = entityType;
        opts.id = entityId;
    }

    return opts;
}

function nextIntakeTabTitle() {
    const activeIntakes = state.tabs.filter(
        (tab) => isIntakeKey(tab.key) && tab.key !== INTAKE_WORKSPACE_KEY,
    ).length;

    return activeIntakes > 0 ? `Check In (${activeIntakes + 1})` : 'Check In';
}

function intakeOpenSubtitle(route) {
    const search = route.includes('?') ? route.slice(route.indexOf('?')) : '';

    return hasIncomingCallPhoneParam(search) ? 'Incoming call' : 'Recognize customer';
}

function resolveIntakeWorkspaceKey(workspaceId = null) {
    const wsId = workspaceId
        || intakeWorkspaceIdFromSearch(window.location.search)
        || (isIntakeKey(state.activeKey) && state.activeKey !== INTAKE_WORKSPACE_KEY
            ? state.activeKey.split(':')[1]
            : null);

    return isValidIntakeWorkspaceId(wsId) ? makeKey('intake', wsId) : null;
}

function updateIntakeTab({ title, subtitle, route, workspaceId = null } = {}) {
    const key = resolveIntakeWorkspaceKey(workspaceId);

    if (!key) {
        return;
    }

    const tab = findTab(key);

    if (!tab) {
        return;
    }

    if (title !== undefined && title !== null) {
        const nextTitle = String(title).trim();

        tab.title = nextTitle || 'Check In';
        tab.customerName = tab.title !== 'Check In' && !/^Check In \(\d+\)$/.test(tab.title)
            ? tab.title
            : '';
    }

    if (subtitle !== undefined) {
        tab.subtitle = String(subtitle || '');
    }

    if (route) {
        tab.route = normalizeIntakeRoute(route, key.split(':')[1]);
    }

    saveState();
    renderBar();
}

function spawnFreshIntakeWorkspace(sourceRoute = '/app/intake/new') {
    openWorkspace({
        entityType: 'intake',
        entityId: INTAKE_LAUNCHER_ENTITY_ID,
        type: 'intake',
        id: INTAKE_LAUNCHER_ENTITY_ID,
        route: sourceRoute,
        title: nextIntakeTabTitle(),
        subtitle: intakeOpenSubtitle(sourceRoute),
    });
}

function closeIntakeWorkspaceById(workspaceId) {
    if (!isValidIntakeWorkspaceId(workspaceId)) {
        return;
    }

    const key = makeKey('intake', workspaceId);
    window.ARK?.intakeWorkspaceMemory?.clearFor?.(workspaceId);

    if (!findTab(key)) {
        return;
    }

    if (state.activeKey === key) {
        state.activeKey = null;
    }

    removeTab(key);
    setDirtyKey(key, false);
    saveState();
    renderBar();
}

function openWorkspace(opts) {
    opts = normalizeOpenOpts(opts || {});

    if (!isDesktop()) {
        if (opts.route) {
            window.location.assign(opts.route);
        }

        return;
    }

    const type = String(opts.entityType || opts.type || '');
    let id = String(opts.entityId ?? opts.id ?? '');
    let route = opts.route || '';

    if (!type || !id || !route) {
        return;
    }

    let targetRoute = normalizeStoredRoute(type, id, route);
    let freshIntake = false;

    if (type === 'intake') {
        freshIntake = isExplicitIntakeResetRoute(targetRoute) || id === INTAKE_LAUNCHER_ENTITY_ID;

        if (freshIntake) {
            id = createIntakeWorkspaceId();
            targetRoute = appendIntakeWorkspaceId(targetRoute, id);
            route = targetRoute;
            opts.title = opts.title || nextIntakeTabTitle();
            opts.subtitle = opts.subtitle || intakeOpenSubtitle(targetRoute);
        }
    }

    const key = makeKey(type, id);
    if (isExcludedWorkspaceKey(key)) {
        if (opts.route) {
            window.location.assign(opts.route);
        }

        return;
    }

    const departingKey = state.activeKey;
    const existing = freshIntake ? null : findTab(key);
    const samePage = !freshIntake
        && normalizeRoute(targetRoute) === normalizeRoute(window.location.pathname + window.location.search);

    const commitOpen = () => {
        if (existing) {
            existing.route = targetRoute;

            touchTab(key);
            saveState();
            renderBar();
            navigateTo(targetRoute, key, samePage, departingKey, {
                skipDirtyConfirm: true,
                freshIntake,
            });

            return;
        }

        makeRoomForNewTab([key]);

        upsertTab({
            key,
            entityType: type,
            entityId: id,
            route: targetRoute,
            title: opts.title || defaultTitle(type, id),
            subtitle: opts.subtitle || '',
            customerName: opts.customerName ? String(opts.customerName) : '',
        });

        saveState();
        renderBar();
        navigateTo(targetRoute, key, samePage, departingKey, {
            skipDirtyConfirm: true,
            freshIntake,
        });
    };

    if (existing) {
        commitOpen();

        return;
    }

    if (departingKey && departingKey !== key && keyHasUnsavedChanges(departingKey) && !freshIntake) {
        confirmNavigateAway(departingKey, commitOpen);

        return;
    }

    commitOpen();
}

function focusWorkspace(key) {
    if (key === INTAKE_WORKSPACE_KEY) {
        spawnFreshIntakeWorkspace('/app/intake/new');

        return;
    }

    const tab = findTab(key);
    if (!tab) {
        return;
    }

    const departingKey = state.activeKey;
    const samePage = normalizeRoute(tab.route) === normalizeRoute(window.location.pathname + window.location.search);

    const commitFocus = () => {
        state.activeKey = key;
        touchTab(key);
        saveState();
        renderBar();
        navigateTo(tab.route, key, samePage, departingKey, { skipDirtyConfirm: true });
    };

    if (departingKey && departingKey !== key && keyHasUnsavedChanges(departingKey)) {
        confirmNavigateAway(departingKey, commitFocus);

        return;
    }

    commitFocus();
}

function closeWorkspace(key, force = false) {
    if (isDockedTabKey(key)) {
        return;
    }

    const tab = findTab(key);
    if (!tab) {
        return;
    }

    const performClose = () => {
        if (!force && isDirtyKey(key)) {
            setDirtyKey(key, false);
        }

        const wasActive = state.activeKey === key;
        const middleKeys = state.tabs
            .filter((tab) => !isDockedTabKey(tab.key))
            .map((tab) => tab.key);
        const focusedAtByKey = Object.fromEntries(
            state.tabs.map((tab) => [tab.key, Number(tab.focusedAt || tab.lastActiveAt || 0)]),
        );
        const neighborKey = wasActive
            ? pickAdjacentNeighborKey({
                orderedKeys: middleKeys,
                closedKey: key,
                focusedAtByKey,
            })
            : null;

        if (isIntakeKey(key) && key !== INTAKE_WORKSPACE_KEY) {
            window.ARK?.intakeWorkspaceMemory?.clearFor?.(key.split(':')[1]);
        }

        removeTab(key);
        saveState();

        if (!wasActive) {
            renderBar();

            return;
        }

        const remaining = state.tabs.filter((tab) => !isDockedTabKey(tab.key));

        if (remaining.length === 0) {
            state.activeKey = null;
            renderBar();
            window.location.assign(cfg?.dashboardUrl || '/app');

            return;
        }

        const next = (neighborKey && findTab(neighborKey))
            || [...remaining].sort(
                (a, b) => (b.focusedAt || b.lastActiveAt || 0) - (a.focusedAt || a.lastActiveAt || 0),
            )[0];

        state.activeKey = next.key;
        saveState();
        renderBar();
        window.location.assign(next.route);
    };

    if (!force) {
        confirmClose(tab, key, performClose);

        return;
    }

    performClose();
}

function registerCurrentFromPage() {
    const boot = cfg?.boot || null;
    const entity = boot || parseEntityFromLocation(window.location);

    if (!entity) {
        state.activeKey = null;
        saveState();
        renderBar();

        return;
    }

    const entityType = entity.entityType || entity.type;
    const entityId = entity.entityId ?? entity.id;
    const key = entity.key || makeKey(entityType, entityId);
    if (isExcludedWorkspaceKey(key)) {
        renderBar();

        return;
    }

    const title = boot?.title || defaultTitle(entityType, entityId);
    const subtitle = boot?.subtitle || '';
    const route = normalizeStoredRoute(
        entityType,
        entityId,
        boot?.route || entity.route || window.location.pathname + window.location.search,
    );

    if (!findTab(key)) {
        makeRoomForNewTab([key]);
    }

    upsertTab({
        key,
        entityType,
        entityId,
        route,
        title,
        subtitle,
        customerName: boot?.customerName ? String(boot.customerName) : '',
        pinned: isLeftDockKey(key),
    });

    ensureDockTabOrdering();

    state.activeKey = key;
    touchTab(key);
    saveState();
    renderBar();
    restoreContextForKey(key);

    // Full page load is server-authoritative; stale session dirty flags caused false reload prompts.
    setDirtyKey(key, false);

    markActiveTabSeen(boot?.signals || null);

    if (isIntakeKey(key)) {
        const hasIncomingCallPhone = (() => {
            try {
                return new URLSearchParams(window.location.search).has('phone');
            } catch {
                return false;
            }
        })();

        if (hasIncomingCallPhone) {
            setDirtyKey(key, false);
        } else {
            window.ARK?.intakeWorkspaceMemory?.persist?.();
        }
    }

    saveState();
    renderBar();
    scheduleActivityRefresh();

    document.dispatchEvent(new CustomEvent('ark:workspace-registered', {
        detail: {
            key,
            entityType,
            entityId,
        },
        bubbles: true,
    }));
}

function buildOpenPayloadFromUrl(url, linkEl) {
    try {
        const parsed = new URL(url, window.location.origin);
        if (parsed.origin !== window.location.origin) {
            return null;
        }

        const entity = parseEntityFromLocation(parsed);
        if (!entity) {
            return null;
        }

        if (isExcludedWorkspaceKey(makeKey(entity.type, entity.id))) {
            return null;
        }

        let title = defaultTitle(entity.type, entity.id);
        if (entity.type !== 'intake' && linkEl?.textContent) {
            const linkLabel = String(linkEl.textContent).trim().replace(/\s+/g, ' ');
            if (linkLabel && linkLabel.length < 72) {
                title = linkLabel;
            }
        }

        return {
            type: entity.type,
            id: entity.id,
            entityType: entity.type,
            entityId: entity.id,
            route: entity.type === 'repair_order'
                ? normalizeRepairOrderRoute(entity.id, parsed.pathname + parsed.search)
                : entity.type === 'intake'
                    ? normalizeIntakeRoute(parsed.pathname + parsed.search, entity.id)
                    : entity.type === 'report'
                        ? normalizeReportRoute(entity.id, parsed.pathname + parsed.search)
                        : parsed.pathname + parsed.search,
            title: entity.type === 'intake' ? 'Check In' : title,
        };
    } catch {
        return null;
    }
}

function onDocumentClick(event) {
    if (!cfg?.interceptLinks || !isDesktop()) {
        return;
    }

    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    const anchor = event.target.closest('a[href]');
    if (!anchor || anchor.target === '_blank' || anchor.hasAttribute('download')) {
        return;
    }

    if (anchor.dataset?.arkWorkspace === 'off' || anchor.dataset?.refreshScope) {
        return;
    }

    const href = anchor.getAttribute('href');
    if (!href || href.startsWith('#')) {
        return;
    }

    const payload = buildOpenPayloadFromUrl(href, anchor);
    if (!payload) {
        return;
    }

    event.preventDefault();
    openWorkspace(payload);
}

export function resetIntakeWorkspaceTab() {
    window.ARK?.intakeWorkspaceMemory?.clear?.();

    const tab = findTab(INTAKE_WORKSPACE_KEY);
    if (tab) {
        tab.route = '/app/intake/new';
        tab.title = 'Check In';
        tab.subtitle = 'Recognize customer';
        tab.activity = undefined;
        tab.dirty = false;
        tab.pinned = false;
        setDirtyKey(INTAKE_WORKSPACE_KEY, false);
        saveState();
    }

    ensureDockTabOrdering();
    renderBar();
}

export function initWorkspaceTabs() {
    if (!isEnabled()) {
        return;
    }

    loadState();
    ensureDockTabs();
    pruneExcludedWorkspaceTabs();

    if (cfg?.closeIntakeWorkspaceId) {
        closeIntakeWorkspaceById(String(cfg.closeIntakeWorkspaceId));
    }

    if (cfg?.resetIntake) {
        resetIntakeWorkspaceTab();
    }

    document.addEventListener('click', onDocumentClick, true);
    document.addEventListener('click', closeOverflowMenus);
    document.addEventListener('ark:intake-incoming-call-capture', () => {
        setDirtyKey(INTAKE_WORKSPACE_KEY, false);
    });

    window.addEventListener('beforeunload', () => {
        if (state.activeKey) {
            saveContextForKey(state.activeKey);
        }
    });

    window.addEventListener('resize', () => {
        syncWorkspaceChromeOffset();
        renderBar();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', registerCurrentFromPage);
    } else {
        registerCurrentFromPage();
    }

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            registerCurrentFromPage();
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            scheduleActivityRefresh();
        }
    });

    activityPollTimer = window.setInterval(() => {
        refreshWorkspaceActivity();
    }, ACTIVITY_POLL_MS);

    window.ARK = window.ARK || {};
    const previousWorkspace = window.ARK.workspace || {};
    window.ARK.workspace = {
        open: openWorkspace,
        focus: focusWorkspace,
        close: closeWorkspace,
        patchActivity,
        refreshActivity: refreshWorkspaceActivity,
        resetIntake: resetIntakeWorkspaceTab,
        togglePin: (key) => toggleTabPin(key || state.activeKey),
        setDirty: (keyOrDirty, maybeDirty) => {
            if (typeof keyOrDirty === 'boolean') {
                setDirtyKey(state.activeKey, keyOrDirty);
            } else {
                setDirtyKey(keyOrDirty, !!maybeDirty);
            }
        },
        isDirty: (key) => isDirtyKey(key || state.activeKey),
        syncDirty: previousWorkspace.syncDirty,
        hasUnsavedFormChanges: previousWorkspace.hasUnsavedFormChanges,
        getTabs: () => state.tabs.map((tab) => ({
            key: tab.key,
            entityType: tab.entityType,
            entityId: tab.entityId,
            route: tab.route,
            title: tab.title,
            dirty: tab.dirty || isDirtyKey(tab.key),
            pinned: !!tab.pinned,
        })),
        hasTab: (key) => !!findTab(key),
        getActiveKey: () => state.activeKey,
        registerCurrent: registerCurrentFromPage,
        updateIntakeTab,
        getEffectiveMaxTabs,
        makeRoomForNewTab,
        pickOldestInactiveEvictionCandidate: (excludeKeys = []) => pickOldestInactiveEvictionCandidate({
            tabs: state.tabs,
            activeKey: state.activeKey,
            excludeKeys,
            isDocked: isDockedTabKey,
            isDirty: keyHasUnsavedChanges,
        }),
        isDesktop,
        reorderTabs,
    };
}
