/**
 * Service intake work memory — URL step context, scroll, and in-progress recognize draft.
 */

const SCROLL_RETRY_MS = [0, 50, 150, 350, 700];
const PERSIST_DEBOUNCE_MS = 200;
const LEGACY_STORAGE_KEY = 'ark:intake-memory:service';
const STORAGE_KEY_PREFIX = 'ark:intake-memory:';
export const INTAKE_WS_QUERY_KEY = 'ws';
export const INTAKE_LAUNCHER_ENTITY_ID = 'service';

let persistTimer = null;
let restoreScheduled = false;
let pendingDraft = null;
let skipNextIntakePersist = false;

export function createIntakeWorkspaceId() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID().replace(/-/g, '').slice(0, 12);
    }

    return `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 8)}`.slice(0, 12);
}

export function isValidIntakeWorkspaceId(id) {
    return typeof id === 'string'
        && id !== ''
        && id !== INTAKE_LAUNCHER_ENTITY_ID
        && /^[a-z0-9]{6,20}$/i.test(id);
}

export function intakeWorkspaceIdFromSearch(search = '') {
    try {
        const id = new URLSearchParams(search.startsWith('?') ? search.slice(1) : search).get(INTAKE_WS_QUERY_KEY);

        return isValidIntakeWorkspaceId(id) ? id : null;
    } catch {
        return null;
    }
}

export function intakeWorkspaceIdFromRoute(route) {
    try {
        const url = new URL(String(route), window.location.origin);

        return intakeWorkspaceIdFromSearch(url.search);
    } catch {
        return null;
    }
}

export function appendIntakeWorkspaceId(route, workspaceId) {
    const url = new URL(String(route), window.location.origin);
    url.searchParams.set(INTAKE_WS_QUERY_KEY, workspaceId);

    return url.pathname + url.search;
}

function intakeMemoryStorageKey(workspaceId = intakeWorkspaceIdFromSearch(window.location.search)) {
    return workspaceId ? `${STORAGE_KEY_PREFIX}${workspaceId}` : LEGACY_STORAGE_KEY;
}

export function intakeRouteDepth(search = '') {
    const params = new URLSearchParams(search.startsWith('?') ? search.slice(1) : search);

    if (params.has('vehicle_id') && params.has('customer_id')) {
        return 3;
    }

    if (params.has('customer_id')) {
        return 2;
    }

    if (params.has('q')) {
        return 1;
    }

    return 0;
}

function onIntakePage() {
    return window.location.pathname === '/app/intake/new';
}

export function clearIntakeIncomingCallCapture() {
    clearIntakeWorkspaceMemory();
    document.dispatchEvent(new CustomEvent('ark:intake-incoming-call-capture', { bubbles: true }));
}

export function clearIntakeWorkspaceMemoryFor(workspaceId = intakeWorkspaceIdFromSearch(window.location.search)) {
    try {
        sessionStorage.removeItem(intakeMemoryStorageKey(workspaceId));
    } catch {
        /* ignore */
    }
}

export function hasIncomingCallPhoneParam(search = window.location.search) {
    try {
        return new URLSearchParams(search).has('phone');
    } catch {
        return false;
    }
}

export function isExplicitIntakeResetRoute(route) {
    try {
        const url = new URL(String(route), window.location.origin);

        if (url.pathname !== '/app/intake/new') {
            return false;
        }

        if (hasIncomingCallPhoneParam(url.search)) {
            return true;
        }

        return intakeRouteDepth(url.search) === 0;
    } catch {
        return false;
    }
}

export function prepareIntakeNavigation(targetRoute) {
    try {
        const targetUrl = new URL(String(targetRoute), window.location.origin);
        if (targetUrl.pathname !== '/app/intake/new') {
            return;
        }

        if (targetUrl.searchParams.has('phone')) {
            skipNextIntakePersist = true;
            clearIntakeIncomingCallCapture();

            return;
        }

        if (!onIntakePage()) {
            return;
        }

        if (intakeRouteDepth(targetUrl.search) < intakeRouteDepth(window.location.search)) {
            skipNextIntakePersist = true;
            clearIntakeWorkspaceMemory();
        }
    } catch {
        /* ignore */
    }
}

export function shouldSkipIntakePersist() {
    if (!skipNextIntakePersist) {
        return false;
    }

    skipNextIntakePersist = false;

    return true;
}

export function captureIntakeDraftFromDom() {
    const root = document.querySelector('.ops-intake-find-workspace');

    if (!root?._x_dataStack?.[0]) {
        return null;
    }

    const state = root._x_dataStack[0];

    return {
        query: String(state.query ?? ''),
        selectedCustomerId: state.selectedCustomerId ?? null,
        firstName: String(state.firstName ?? ''),
        lastName: String(state.lastName ?? ''),
        phone: String(state.phone ?? ''),
        email: String(state.email ?? ''),
        addressLine1: String(state.addressLine1 ?? ''),
        addressLine2: String(state.addressLine2 ?? ''),
        city: String(state.city ?? ''),
        state: String(state.state ?? ''),
        postalCode: String(state.postalCode ?? ''),
        referralSource: String(state.referralSource ?? ''),
        customerType: String(state.customerType ?? 'Retail'),
    };
}

export function captureIntakeWorkspaceMemory() {
    if (!onIntakePage()) {
        return null;
    }

    const params = new URLSearchParams(window.location.search);

    if (params.has('phone')) {
        return null;
    }

    const draft = !params.has('customer_id') ? captureIntakeDraftFromDom() : null;

    return {
        version: 1,
        scrollY: window.scrollY || 0,
        pathname: window.location.pathname,
        search: window.location.search,
        draft,
        savedAt: Date.now(),
    };
}

export function persistIntakeWorkspaceMemory() {
    const memory = captureIntakeWorkspaceMemory();
    if (!memory) {
        return;
    }

    try {
        sessionStorage.setItem(intakeMemoryStorageKey(), JSON.stringify(memory));
    } catch {
        /* ignore */
    }
}

function schedulePersist() {
    if (persistTimer) {
        window.clearTimeout(persistTimer);
    }

    persistTimer = window.setTimeout(() => {
        persistTimer = null;
        persistIntakeWorkspaceMemory();
    }, PERSIST_DEBOUNCE_MS);
}

function applyScrollWithRetry(y) {
    SCROLL_RETRY_MS.forEach((delay) => {
        window.setTimeout(() => {
            if (typeof y === 'number' && y >= 0) {
                window.scrollTo(0, y);
            }
        }, delay);
    });
}

function dispatchDraftRestore(draft) {
    if (!draft || typeof draft !== 'object') {
        return;
    }

    pendingDraft = draft;
    document.dispatchEvent(new CustomEvent('ark:intake-memory-restore', {
        detail: { draft },
        bubbles: true,
    }));
}

export function consumeIntakeDraftRestore() {
    const draft = pendingDraft;
    pendingDraft = null;

    return draft;
}

export function restoreIntakeWorkspaceMemory() {
    if (!onIntakePage()) {
        return;
    }

    let memory;
    try {
        const raw = sessionStorage.getItem(intakeMemoryStorageKey());
        if (!raw) {
            return;
        }

        memory = JSON.parse(raw);
    } catch {
        return;
    }

    if (!memory || memory.version !== 1) {
        return;
    }

    const params = new URLSearchParams(window.location.search);

    if (params.has('phone')) {
        clearIntakeIncomingCallCapture();

        return;
    }

    if (params.has('lead_id')) {
        return;
    }

    applyScrollWithRetry(memory.scrollY);

    if (!params.has('customer_id') && memory.draft) {
        dispatchDraftRestore(memory.draft);
    }
}

function scheduleRestore() {
    if (restoreScheduled) {
        return;
    }

    restoreScheduled = true;

    const run = () => {
        restoreScheduled = false;
        restoreIntakeWorkspaceMemory();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run, { once: true });
    } else {
        requestAnimationFrame(() => requestAnimationFrame(run));
    }
}

export function clearIntakeWorkspaceMemory() {
    clearIntakeWorkspaceMemoryFor();
}

export function initIntakeWorkspaceMemory() {
    if (!window.__ARK_WORKSPACE__?.enabled) {
        return;
    }

    if (!onIntakePage()) {
        return;
    }

    window.addEventListener('scroll', schedulePersist, { passive: true });
    document.addEventListener('input', (event) => {
        if (event.target?.closest?.('.ops-intake-find-workspace, .ops-intake-open-stack')) {
            schedulePersist();
        }
    }, true);

    document.addEventListener('ark:intake-draft-changed', schedulePersist);

    window.addEventListener('pagehide', persistIntakeWorkspaceMemory);
    window.addEventListener('beforeunload', persistIntakeWorkspaceMemory);

    document.addEventListener('ark:workspace-registered', (event) => {
        if (String(event.detail?.entityType || '') === 'intake') {
            scheduleRestore();
        }
    });

    scheduleRestore();

    window.ARK = window.ARK || {};
    window.ARK.intakeWorkspaceMemory = {
        capture: captureIntakeWorkspaceMemory,
        persist: persistIntakeWorkspaceMemory,
        restore: restoreIntakeWorkspaceMemory,
        consumeDraftRestore,
        clear: clearIntakeWorkspaceMemory,
        clearFor: clearIntakeWorkspaceMemoryFor,
        clearIncomingCallCapture: clearIntakeIncomingCallCapture,
    };
}
