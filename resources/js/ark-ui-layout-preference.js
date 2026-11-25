/**
 * Shared browser-local List/Cards preference API.
 *
 * Storage key: ark.ui.layout.v1:{tenant}:{user}:{module}
 * Values: list | cards
 *
 * Job Board authority is the authenticated user's `job_board_view` (server).
 * This module mirrors that choice for fast paint and one-time legacy migration.
 * Collection modules may still use this store until their DB-backed slice ships.
 *
 * Clean URLs: never add ?view=; accept and strip legacy bookmarks.
 */

export const STORAGE_PREFIX = 'ark.ui.layout.v1';
export const MD_UP = '(min-width: 768px)';

export const UiLayoutModule = Object.freeze({
    JobBoard: 'job_board',
    Customers: 'customers',
    Vehicles: 'vehicles',
    RepairOrders: 'repair_orders',
});

const LEGACY_COOKIES = Object.freeze({
    [UiLayoutModule.JobBoard]: 'ark_job_board_layout',
    [UiLayoutModule.Customers]: 'ark_collection_layout_customers',
    [UiLayoutModule.Vehicles]: 'ark_collection_layout_vehicles',
    [UiLayoutModule.RepairOrders]: 'ark_collection_layout_repair_orders',
});

export function normalizeLayout(value) {
    if (value === 'cards' || value === 'board') {
        return 'cards';
    }
    if (value === 'list') {
        return 'list';
    }
    return null;
}

export function viewportDefault() {
    return window.matchMedia(MD_UP).matches ? 'list' : 'cards';
}

export function storageKey({ tenant, userId, module }) {
    const safeTenant = tenant && String(tenant).trim() !== '' ? String(tenant) : 'local';
    const safeUser = userId && String(userId).trim() !== '' ? String(userId) : 'anon';
    return `${STORAGE_PREFIX}:${safeTenant}:${safeUser}:${module}`;
}

export function readCookie(name) {
    const prefix = `${name}=`;
    const parts = document.cookie.split(';');
    for (const part of parts) {
        const trimmed = part.trim();
        if (trimmed.startsWith(prefix)) {
            return decodeURIComponent(trimmed.slice(prefix.length));
        }
    }
    return null;
}

export function writeCookie(name, value, domain) {
    const maxAge = 60 * 60 * 24 * 365;
    let cookie = `${name}=${encodeURIComponent(value)}; path=/; max-age=${maxAge}; samesite=lax`;
    if (window.location.protocol === 'https:') {
        cookie += '; secure';
    }
    if (domain) {
        cookie += `; domain=${domain}`;
    }
    document.cookie = cookie;
}

function readLocal(key) {
    try {
        return normalizeLayout(window.localStorage.getItem(key));
    } catch {
        return null;
    }
}

function writeLocal(key, layout) {
    try {
        window.localStorage.setItem(key, layout);
    } catch {
        // Private mode / quota — cookie dual-write still helps SSR.
    }
}

/**
 * @param {{ tenant: string, userId: string, module: string, cookieDomain?: string|null }} scope
 * @returns {'list'|'cards'|null}
 */
export function readExplicitLayout(scope) {
    const key = storageKey(scope);
    const fromStore = readLocal(key);
    if (fromStore) {
        return fromStore;
    }

    const legacyName = LEGACY_COOKIES[scope.module];
    if (!legacyName) {
        return null;
    }

    const fromCookie = normalizeLayout(readCookie(legacyName));
    if (fromCookie) {
        writeLocal(key, fromCookie);
        return fromCookie;
    }

    return null;
}

/**
 * Persist an explicit List/Cards choice for this module scope.
 * Dual-writes the legacy cookie for SSR compatibility.
 */
export function writeExplicitLayout(scope, layout, { cookieDomain = null } = {}) {
    const normalized = normalizeLayout(layout);
    if (!normalized) {
        return null;
    }

    writeLocal(storageKey(scope), normalized);

    const legacyName = LEGACY_COOKIES[scope.module];
    if (legacyName) {
        writeCookie(legacyName, normalized, cookieDomain || undefined);
    }

    return normalized;
}

/** Remove ?view= from the address bar without reload (bookmark one-shot). */
export function stripViewQueryParam() {
    try {
        const url = new URL(window.location.href);
        if (!url.searchParams.has('view')) {
            return false;
        }
        url.searchParams.delete('view');
        const next = url.pathname + (url.searchParams.toString() ? `?${url.searchParams}` : '') + url.hash;
        window.history.replaceState(window.history.state, '', next);
        return true;
    } catch {
        return false;
    }
}

export function readViewQueryParam() {
    try {
        return normalizeLayout(new URL(window.location.href).searchParams.get('view'));
    } catch {
        return null;
    }
}

export function scopeFromDocument(root = document) {
    const tenant =
        root.querySelector('meta[name="ark-ui-tenant-key"]')?.content ||
        root.documentElement?.getAttribute?.('data-ark-ui-tenant') ||
        'local';
    const userId =
        root.querySelector('meta[name="ark-current-user-id"]')?.content ||
        'anon';

    return { tenant, userId };
}
