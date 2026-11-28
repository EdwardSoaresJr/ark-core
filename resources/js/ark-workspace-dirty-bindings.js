/**
 * Wire entity surfaces to ARK.workspace.setDirty - only when forms actually differ
 * from their loaded baselines (reverts clear the scare).
 */
import { rootHasUnsavedFormChanges } from './ark-form-unsaved';
import { adoptRenderedBaselines } from './ark-worksheet-draft';

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
    const ignoreSelector = [
        '[data-ark-workspace-dirty="off"]',
        '[type="search"]',
        '.ops-workspace-tabs',
        '#workspace-modal-host[data-workspace-modal-open="0"]',
    ].join(', ');
    const touchedFields = new WeakSet();

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

    function markTouched(target) {
        const el = target?.closest?.('input, textarea, select, [contenteditable="true"]');
        if (el) {
            touchedFields.add(el);
        }
    }

    function adoptHydrationBaselines() {
        // Skip controls the advisor already edited so a deferred adopt cannot
        // treat an unpersisted draft as the new clean baseline.
        adoptRenderedBaselines(root, { skipFields: touchedFields });
        syncDirty();
    }

    root.addEventListener('input', (event) => {
        if (!event.isTrusted || !shouldTrack(event.target)) {
            return;
        }

        markTouched(event.target);
        syncDirty();
    }, true);

    root.addEventListener('change', (event) => {
        if (!event.isTrusted || !shouldTrack(event.target)) {
            return;
        }

        markTouched(event.target);
        syncDirty();
    }, true);

    window.ARK = window.ARK || {};
    window.ARK.workspace = window.ARK.workspace || {};
    window.ARK.workspace.syncDirty = syncDirty;
    window.ARK.workspace.hasUnsavedFormChanges = () => rootHasUnsavedFormChanges(root);

    // Immediate adopt after Alpine.start() (bindings run after Alpine in app.js).
    adoptHydrationBaselines();

    // One deferred pass for late Alpine/select hydration; still skips touched fields.
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            adoptHydrationBaselines();
        });
    });
}
