/**
 * Open-RO working-set eviction — oldest inactive first.
 * Pure helpers; no DOM / storage side effects.
 */

/**
 * @param {{
 *   tabs: Array<{ key: string, openedAt?: number }>,
 *   activeKey: string|null,
 *   excludeKeys?: string[],
 *   isDocked: (key: string) => boolean,
 *   isDirty: (key: string) => boolean,
 * }} opts
 * @returns {{ key: string, openedAt: number }|null}
 */
export function pickOldestInactiveEvictionCandidate(opts) {
    const exclude = new Set((opts.excludeKeys || []).map(String));
    const activeKey = opts.activeKey ? String(opts.activeKey) : null;

    const candidates = (opts.tabs || [])
        .filter((tab) => {
            const key = String(tab?.key || '');
            if (!key || exclude.has(key)) {
                return false;
            }
            if (opts.isDocked(key)) {
                return false;
            }
            if (activeKey && key === activeKey) {
                return false;
            }
            if (opts.isDirty(key)) {
                return false;
            }

            return true;
        })
        .map((tab) => ({
            key: String(tab.key),
            openedAt: Number(tab.openedAt || 0),
        }))
        .sort((a, b) => {
            if (a.openedAt !== b.openedAt) {
                return a.openedAt - b.openedAt;
            }

            return a.key.localeCompare(b.key);
        });

    return candidates[0] || null;
}

/**
 * @param {{
 *   tabs: Array<{ key: string, openedAt?: number }>,
 *   activeKey: string|null,
 *   excludeKeys?: string[],
 *   maxOpen: number,
 *   isDocked: (key: string) => boolean,
 *   isDirty: (key: string) => boolean,
 * }} opts
 * @returns {string[]} keys to remove (oldest first)
 */
export function keysToEvictForNewTab(opts) {
    const maxOpen = Math.max(1, Number(opts.maxOpen) || 1);
    const isDocked = opts.isDocked;
    const openCount = () => (opts.tabs || []).filter((tab) => !isDocked(String(tab.key || ''))).length;

    const removeKeys = [];
    const excluded = new Set((opts.excludeKeys || []).map(String));

    while (openCount() - removeKeys.length >= maxOpen) {
        const remainingTabs = (opts.tabs || []).filter((tab) => !removeKeys.includes(String(tab.key)));
        const victim = pickOldestInactiveEvictionCandidate({
            tabs: remainingTabs,
            activeKey: opts.activeKey,
            excludeKeys: [...excluded, ...removeKeys],
            isDocked,
            isDirty: opts.isDirty,
        });

        if (!victim) {
            break;
        }

        removeKeys.push(victim.key);
    }

    return removeKeys;
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
