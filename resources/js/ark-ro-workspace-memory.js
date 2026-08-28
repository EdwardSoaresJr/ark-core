/**
 * Repair order work memory — concern orientation, expanded details, editing line, scroll.
 * Cooperates with workspace tabs; persists per shop RO number in sessionStorage.
 */

const SCROLL_RETRY_MS = [0, 120, 350];
const PERSIST_DEBOUNCE_MS = 200;

let persistTimer = null;
let restoreScheduled = false;
let restoredNavigationKey = null;
let scrollRestoreTimers = [];
let scrollRestoreToken = 0;
let scrollRestoreCancellationBound = false;

function shopRoId() {
    const boot = window.__ARK_WORKSPACE__?.boot;
    if (boot?.entityType === 'repair_order' && boot.entityId) {
        return String(boot.entityId);
    }

    const match = window.location.pathname.match(/\/app\/repair-orders\/(\d+)/);
    return match ? match[1] : null;
}

function storageKey(id) {
    return `ark:ro-memory:${id}`;
}

function concernIdFromElement(element) {
    if (!element?.id?.startsWith('concern-')) {
        return null;
    }

    return element.id.replace('concern-', '');
}

function captureFocusedConcernId() {
    const active = document.activeElement;
    if (active?.closest) {
        const card = active.closest('[id^="concern-"]');
        const id = concernIdFromElement(card);
        if (id) {
            return id;
        }
    }

    const editingLine = new URLSearchParams(window.location.search).get('editing_line');
    if (editingLine) {
        const line = document.getElementById(`line-${editingLine}`);
        const card = line?.closest('[id^="concern-"]');
        const id = concernIdFromElement(card);
        if (id) {
            return id;
        }
    }

    return null;
}

export function captureRoWorkspaceMemory() {
    const id = shopRoId();
    if (!id) {
        return null;
    }

    const params = new URLSearchParams(window.location.search);

    return {
        version: 1,
        scrollY: window.scrollY || 0,
        pathname: window.location.pathname,
        search: window.location.search,
        editingLine: params.get('editing_line'),
        openDetails: [...document.querySelectorAll('details[open][id]')].map((el) => el.id).filter(Boolean),
        focusedConcernId: captureFocusedConcernId(),
        savedAt: Date.now(),
    };
}

export function persistRoWorkspaceMemory() {
    const id = shopRoId();
    const memory = captureRoWorkspaceMemory();
    if (!id || !memory) {
        return;
    }

    try {
        sessionStorage.setItem(storageKey(id), JSON.stringify(memory));
    } catch {
        /* ignore */
    }

    if (typeof window.preserveRepairOrderLineScroll === 'function' && memory.editingLine) {
        window.preserveRepairOrderLineScroll(memory.editingLine);
    }
}

function schedulePersist() {
    if (persistTimer) {
        window.clearTimeout(persistTimer);
    }

    persistTimer = window.setTimeout(() => {
        persistTimer = null;
        persistRoWorkspaceMemory();
    }, PERSIST_DEBOUNCE_MS);
}

export function cancelRoScrollRestore() {
    scrollRestoreToken += 1;
    scrollRestoreTimers.forEach((timerId) => window.clearTimeout(timerId));
    scrollRestoreTimers = [];
}

function bindScrollRestoreCancellation() {
    if (scrollRestoreCancellationBound) {
        return;
    }

    scrollRestoreCancellationBound = true;

    const cancel = () => cancelRoScrollRestore();

    window.addEventListener('wheel', cancel, { passive: true, capture: true });
    window.addEventListener('touchstart', cancel, { passive: true, capture: true });
    window.addEventListener('keydown', (event) => {
        const scrollKeys = new Set(['ArrowUp', 'ArrowDown', 'PageUp', 'PageDown', 'Home', 'End', ' ']);

        if (scrollKeys.has(event.key)) {
            cancel();
        }
    }, { capture: true });
}

function applyScrollWithRetry(y, anchorEl) {
    cancelRoScrollRestore();

    const token = scrollRestoreToken;

    const apply = () => {
        if (token !== scrollRestoreToken) {
            return;
        }

        if (anchorEl?.isConnected) {
            anchorEl.scrollIntoView({ block: 'nearest', behavior: 'instant' });

            return;
        }

        if (typeof y === 'number' && y > 0) {
            window.scrollTo({ top: y, left: 0, behavior: 'instant' });
        }
    };

    SCROLL_RETRY_MS.forEach((delay) => {
        scrollRestoreTimers.push(window.setTimeout(apply, delay));
    });
}

export function restoreRoWorkspaceMemory() {
    const id = shopRoId();
    if (!id) {
        return;
    }

    const navigationKey = `${window.location.pathname}${window.location.search}`;

    if (restoredNavigationKey === navigationKey) {
        return;
    }

    let memory;
    try {
        const raw = sessionStorage.getItem(storageKey(id));
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

    if (Array.isArray(memory.openDetails)) {
        memory.openDetails.forEach((detailsId) => {
            const el = document.getElementById(detailsId);
            if (el?.tagName === 'DETAILS') {
                el.open = true;
            }
        });
    }

    const currentParams = new URLSearchParams(window.location.search);
    const currentEditingLine = currentParams.get('editing_line');
    const rememberedEditingLine = memory.editingLine ? String(memory.editingLine) : null;

    if (rememberedEditingLine && rememberedEditingLine !== currentEditingLine) {
        currentParams.set('editing_line', rememberedEditingLine);
        const next = `${window.location.pathname}?${currentParams.toString()}`;
        window.location.replace(next);

        return;
    }

    restoredNavigationKey = navigationKey;

    let anchor = null;
    if (rememberedEditingLine) {
        anchor = document.getElementById(`line-${rememberedEditingLine}`);
    } else if (memory.focusedConcernId) {
        anchor = document.getElementById(`concern-${memory.focusedConcernId}`);
    }

    applyScrollWithRetry(memory.scrollY, anchor);
}

function scheduleRestore() {
    if (restoreScheduled) {
        return;
    }

    restoreScheduled = true;

    const run = () => {
        restoreScheduled = false;
        restoreRoWorkspaceMemory();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run, { once: true });
    } else {
        requestAnimationFrame(() => requestAnimationFrame(run));
    }
}

export function initRoWorkspaceMemory() {
    if (!window.__ARK_WORKSPACE__?.enabled) {
        return;
    }

    if (!shopRoId()) {
        return;
    }

    bindScrollRestoreCancellation();

    window.addEventListener('scroll', schedulePersist, { passive: true });
    document.addEventListener('toggle', (event) => {
        if (event.target?.tagName === 'DETAILS' && event.target.id) {
            schedulePersist();
        }
    }, true);

    document.addEventListener('focusin', (event) => {
        if (event.target?.closest?.('[id^="concern-"], [id^="line-"]')) {
            schedulePersist();
        }
    }, true);

    window.addEventListener('pagehide', persistRoWorkspaceMemory);
    window.addEventListener('beforeunload', persistRoWorkspaceMemory);

    document.addEventListener('ark:workspace-registered', (event) => {
        if (String(event.detail?.entityType || '') === 'repair_order') {
            scheduleRestore();
        }
    });

    scheduleRestore();

    window.ARK = window.ARK || {};
    window.ARK.roWorkspaceMemory = {
        capture: captureRoWorkspaceMemory,
        persist: persistRoWorkspaceMemory,
        restore: restoreRoWorkspaceMemory,
        cancelScrollRestore: cancelRoScrollRestore,
    };
}
