/*
 * The top bar's page actions (components.tabler.page-context). Below md the secondary actions move into the bar's
 * overflow menu, so the bar stays one row with the primary action in view; from md they move back in place. The
 * elements themselves move, keeping their listeners and forms. A worksheet refresh that replaces the bar is
 * arranged again by the observer.
 */
const narrow = window.matchMedia('(max-width: 767.98px)');

const arrange = (group) => {
    const overflow = group.querySelector(':scope > [data-ark-page-overflow]');
    const menu = overflow?.querySelector('[data-ark-page-overflow-menu]');

    if (! menu) {
        return;
    }

    if (narrow.matches) {
        for (const action of group.querySelectorAll(':scope > [data-ark-page-action="secondary"]')) {
            const marker = document.createComment('ark-page-action');
            action.before(marker);
            action.arkPageActionMarker = marker;
            action.classList.replace('btn', 'dropdown-item');
            menu.append(action);
        }
    } else {
        for (const action of menu.querySelectorAll(':scope > [data-ark-page-action]')) {
            action.classList.replace('dropdown-item', 'btn');
            const marker = action.arkPageActionMarker;

            if (marker?.isConnected) {
                marker.replaceWith(action);
            } else {
                overflow.before(action);
            }

            delete action.arkPageActionMarker;
        }
    }

    overflow.hidden = menu.children.length === 0;
};

const arrangeAll = () => {
    document.querySelectorAll('[data-ark-page-actions]').forEach(arrange);
};

const start = () => {
    arrangeAll();
    narrow.addEventListener('change', arrangeAll);

    for (const bar of document.querySelectorAll('[data-ark-topbar]')) {
        // Arranging is idempotent, so the observer's own moves settle after one pass.
        new MutationObserver(arrangeAll).observe(bar, { childList: true, subtree: true });
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, { once: true });
} else {
    start();
}
