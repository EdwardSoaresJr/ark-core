/**
 * Shared List | Cards switcher for record indexes (Customers, Vehicles, Repair Orders).
 * Module cookie name comes from data-collection-cookie on the root.
 */
const MD_UP = '(min-width: 768px)';

function readCookie(name) {
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

function writeCookie(name, value, domain) {
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

function viewportDefault() {
    return window.matchMedia(MD_UP).matches ? 'list' : 'cards';
}

function normalizeLayout(value) {
    if (value === 'cards') {
        return 'cards';
    }
    if (value === 'list') {
        return 'list';
    }
    return null;
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

function syncUrlView(layout) {
    try {
        const url = new URL(window.location.href);
        url.searchParams.set('view', layout);
        window.history.replaceState(window.history.state, '', url.toString());
    } catch (error) {
        // History API unavailable; cookie + hidden input still persist the choice.
    }
}

function applyLayout(root, layout, { persist = false, mode = null } = {}) {
    if (persist) {
        root.setAttribute('data-collection-layout', layout);
        root.setAttribute('data-collection-layout-explicit', '1');
        const cookieName = root.getAttribute('data-collection-cookie');
        if (cookieName) {
            writeCookie(cookieName, layout, root.getAttribute('data-collection-cookie-domain') || undefined);
        }
        syncUrlView(layout);
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

    const cookieName = root.getAttribute('data-collection-cookie') || '';
    const cookieLayout = cookieName ? normalizeLayout(readCookie(cookieName)) : null;
    const modeAttr = root.getAttribute('data-collection-layout') || 'auto';
    const explicitMode = normalizeLayout(modeAttr);
    const layout = explicitMode || cookieLayout || viewportDefault();

    root.setAttribute('data-collection-layout-explicit', explicitMode || cookieLayout ? '1' : '0');
    applyLayout(root, layout, { persist: false, mode: explicitMode || cookieLayout || 'auto' });

    root.querySelectorAll('[data-collection-layout-option]').forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            const next = normalizeLayout(btn.getAttribute('data-collection-layout-option'));
            if (!next) {
                return;
            }
            applyLayout(root, next, { persist: true });
        });
    });

    const media = window.matchMedia(MD_UP);
    const onViewportChange = () => {
        if (root.getAttribute('data-collection-layout-explicit') === '1') {
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
