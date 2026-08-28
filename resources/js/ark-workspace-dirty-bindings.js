/**
 * Wire entity surfaces to ARK.workspace.setDirty — only when forms actually differ
 * from their loaded baselines (reverts clear the scare).
 */
import { rootHasUnsavedFormChanges } from './ark-form-unsaved';

export function initWorkspaceDirtyBindings() {
    const cfg = window.__ARK_WORKSPACE__;
    if (!cfg?.enabled || !window.ARK?.workspace) {
        return;
    }

    const boot = cfg.boot || null;
    if (!boot?.key) {
        return;
    }

    const key = boot.key;
    const root = document.querySelector('[data-worksheet-root]')
        || document.querySelector('.ops-main')
        || document.body;
    const ignoreSelector = '[data-ark-workspace-dirty="off"], [type="search"], .ops-workspace-tabs';

    function syncDirty() {
        const dirty = rootHasUnsavedFormChanges(root);
        window.ARK.workspace.setDirty(key, dirty);

        return dirty;
    }

    function shouldTrack(target) {
        if (!target?.closest) {
            return false;
        }

        if (target.closest(ignoreSelector)) {
            return false;
        }

        const el = target.closest('input, textarea, select, [contenteditable="true"]');
        if (!el || el.disabled || el.readOnly) {
            return false;
        }

        if (el instanceof HTMLInputElement && ['hidden', 'button', 'submit', 'reset', 'file', 'image'].includes(el.type)) {
            return false;
        }

        return true;
    }

    root.addEventListener('input', (event) => {
        if (!event.isTrusted || !shouldTrack(event.target)) {
            return;
        }

        syncDirty();
    }, true);

    root.addEventListener('change', (event) => {
        if (!event.isTrusted || !shouldTrack(event.target)) {
            return;
        }

        syncDirty();
    }, true);

    root.addEventListener('submit', (event) => {
        const form = event.target;
        if (!form?.closest || form.closest('[data-ark-workspace-dirty="off"]')) {
            return;
        }

        if (form.matches('form')) {
            // Optimistic clear; continuity / full navigation re-syncs after replace.
            window.ARK.workspace.setDirty(key, false);
        }
    }, true);

    window.ARK = window.ARK || {};
    window.ARK.workspace = window.ARK.workspace || {};
    window.ARK.workspace.syncDirty = syncDirty;
    window.ARK.workspace.hasUnsavedFormChanges = () => rootHasUnsavedFormChanges(root);
}
