/**
 * Workspace dirty-flag policy helpers.
 * Keep sticky sessionStorage honest: flush live form state before leaving a page,
 * and only warn when a real draft remains.
 */

/**
 * While the departing workspace is still in the DOM, recompute its dirty flag
 * so a successful autosave / reverted edit does not leave a sticky scare on
 * inactive-tab close.
 *
 * @param {{ fromKey?: string|null, bootKey?: string|null, syncDirty?: (() => boolean)|null }} args
 * @returns {boolean} true when syncDirty ran
 */
export function flushDepartingWorkspaceDirty({ fromKey = null, bootKey = null, syncDirty = null } = {}) {
    if (! fromKey || ! bootKey || fromKey !== bootKey) {
        return false;
    }

    if (typeof syncDirty !== 'function') {
        return false;
    }

    syncDirty();

    return true;
}

/**
 * Decide whether closing/switching a workspace needs a discard confirm.
 * Active tabs with a live sync use the DOM; inactive tabs use the session flag.
 *
 * @param {{ key?: string|null, activeKey?: string|null, sessionDirty?: boolean, liveDirty?: boolean|null, hasLiveSync?: boolean }} args
 */
export function workspaceNeedsDiscardConfirm({
    key = null,
    activeKey = null,
    sessionDirty = false,
    liveDirty = null,
    hasLiveSync = false,
} = {}) {
    if (! key) {
        return false;
    }

    if (key === activeKey && hasLiveSync) {
        return liveDirty === true;
    }

    return sessionDirty === true;
}
