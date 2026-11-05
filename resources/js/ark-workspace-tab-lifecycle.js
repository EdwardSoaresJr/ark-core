/**
 * Workspace tab lifecycle: when arriving on a page opens a tab, which tab takes over when one closes, which open tabs
 * the strip shows, and which tabs have their activity refreshed. Pure helpers; no DOM / storage side effects.
 */

/**
 * Following a link, a redirect, or a typed address opens the page's tab. Reloading or moving through history only
 * re-activates a tab that is still open, so a closed tab stays closed.
 *
 * @param {string|null|undefined} navigationType PerformanceNavigationTiming.type; 'back_forward' for a page restored
 *   from the back/forward cache
 */
export function arrivalOpensTab(navigationType) {
    return navigationType !== 'back_forward' && navigationType !== 'reload';
}

/**
 * Prefer adjacent surviving tab after closing the active one.
 *
 * @param {{
 *   orderedKeys: string[],
 *   closedKey: string,
 *   focusedAtByKey: Record<string, number>,
 * }} opts
 * @returns {string|null}
 */
export function pickAdjacentNeighborKey(opts) {
    const keys = (opts.orderedKeys || []).map(String);
    const closedKey = String(opts.closedKey || '');
    const idx = keys.indexOf(closedKey);

    if (idx < 0) {
        return null;
    }

    const left = idx > 0 ? keys[idx - 1] : null;
    const right = idx < keys.length - 1 ? keys[idx + 1] : null;

    if (!left && !right) {
        return null;
    }

    if (left && !right) {
        return left;
    }

    if (right && !left) {
        return right;
    }

    const leftFocus = Number(opts.focusedAtByKey?.[left] || 0);
    const rightFocus = Number(opts.focusedAtByKey?.[right] || 0);

    return rightFocus >= leftFocus ? right : left;
}

/**
 * Splits the open tabs into those the strip shows and those its overflow menu lists, keeping their order. The active
 * tab is always shown: when it would overflow it takes the last visible place.
 *
 * @param {{
 *   tabs: Array<{ key: string }>,
 *   activeKey: string|null,
 *   availableWidth: number,
 *   slotWidth: number,
 *   overflowWidth: number,
 * }} opts
 * @returns {{ visible: Array<{ key: string }>, overflow: Array<{ key: string }> }}
 */
export function splitVisibleTabs(opts) {
    const tabs = [...(opts.tabs || [])];
    const slotWidth = Math.max(1, Number(opts.slotWidth) || 1);
    const availableWidth = Math.max(0, Number(opts.availableWidth) || 0);

    if (tabs.length <= Math.floor(availableWidth / slotWidth)) {
        return { visible: tabs, overflow: [] };
    }

    const overflowWidth = Math.max(0, Number(opts.overflowWidth) || 0);
    const capacity = Math.max(1, Math.floor((availableWidth - overflowWidth) / slotWidth));
    const visible = tabs.slice(0, capacity);
    const overflow = tabs.slice(capacity);
    const activeIndex = opts.activeKey ? overflow.findIndex((tab) => tab.key === opts.activeKey) : -1;

    if (activeIndex >= 0) {
        const [active] = overflow.splice(activeIndex, 1);
        overflow.unshift(visible.pop());
        visible.push(active);
    }

    return { visible, overflow };
}

/**
 * The tabs whose activity is refreshed at once: the active tab, then the most recently focused, at most `limit`.
 *
 * @param {{
 *   tabs: Array<{ key: string, focusedAt?: number, lastActiveAt?: number }>,
 *   activeKey: string|null,
 *   limit: number,
 * }} opts
 */
export function tabsForActivityRefresh(opts) {
    const limit = Math.max(1, Number(opts.limit) || 1);
    const focusedAt = (tab) => Number(tab.focusedAt || tab.lastActiveAt || 0);

    return [...(opts.tabs || [])]
        .sort((a, b) => {
            if (a.key === opts.activeKey) {
                return -1;
            }

            if (b.key === opts.activeKey) {
                return 1;
            }

            return focusedAt(b) - focusedAt(a);
        })
        .slice(0, limit);
}
