import { fieldHasChanges, formHasChanges, isWorkspaceModalOpen } from './ark-form-unsaved.js';

export function dirtyFormsIn(root) {
    if (! root?.querySelectorAll) {
        return [];
    }

    return [...root.querySelectorAll('form')].filter((form) => formHasChanges(form));
}

export function draftMarkersIn(root) {
    if (! root?.querySelectorAll) {
        return [];
    }

    return [...root.querySelectorAll('[data-worksheet-draft="1"]')];
}

export function dirtyControlsOutsideForms(root) {
    if (! root?.querySelectorAll) {
        return [];
    }

    return [...root.querySelectorAll('input, textarea, select')].filter((field) => {
        if (field.closest?.('form') || field.closest?.('[data-mileage-inline]')) {
            return false;
        }

        if (typeof field.getClientRects === 'function' && field.getClientRects().length === 0) {
            return false;
        }

        return fieldHasChanges(field);
    });
}

function baselineFieldKey(field, index) {
    const name = String(field?.name || '');
    const type = String(field?.type || '').toLowerCase();

    if (type === 'radio') {
        return `${name}::radio::${String(field.value ?? '')}`;
    }

    if (name !== '') {
        return `${name}::${type || 'field'}`;
    }

    return `__idx_${index}`;
}

function readBaselineFieldState(field) {
    const type = String(field?.type || '').toLowerCase();

    if (type === 'checkbox' || type === 'radio') {
        return { kind: 'check', checked: !!field.checked, value: String(field.value ?? '') };
    }

    if (field?.options) {
        return { kind: 'select', value: String(field.value ?? '') };
    }

    return { kind: 'value', value: String(field?.value ?? '') };
}

function applyBaselineFieldState(field, state) {
    if (! field || ! state) {
        return;
    }

    if (state.kind === 'check') {
        field.defaultChecked = !!state.checked;

        return;
    }

    if (state.kind === 'select' && field.options) {
        for (const option of field.options) {
            option.defaultSelected = option.value === state.value;
        }

        return;
    }

    if ('defaultValue' in field) {
        field.defaultValue = state.value;
    }
}

/**
 * Snapshot of trackable control state at submit (or hydrate) time.
 * Used so a successful older response cannot adopt a newer live edit as "saved".
 */
export function captureFormBaselineSnapshot(form) {
    const snapshot = new Map();

    if (! form?.elements) {
        return snapshot;
    }

    [...form.elements].forEach((field, index) => {
        const type = String(field.type || '').toLowerCase();

        if (type === 'hidden' || type === 'button' || type === 'submit' || type === 'reset' || type === 'file' || type === 'image') {
            return;
        }

        snapshot.set(baselineFieldKey(field, index), readBaselineFieldState(field));
    });

    return snapshot;
}

/**
 * Adopt defaults from a prior snapshot (what was submitted), not from live values.
 * Newer edits after submit remain dirty vs the saved snapshot.
 */
export function adoptSnapshotAsBaseline(form, snapshot) {
    if (! form?.elements || ! snapshot) {
        return;
    }

    [...form.elements].forEach((field, index) => {
        const type = String(field.type || '').toLowerCase();

        if (type === 'hidden' || type === 'button' || type === 'submit' || type === 'reset' || type === 'file' || type === 'image') {
            return;
        }

        applyBaselineFieldState(field, snapshot.get(baselineFieldKey(field, index)));
    });
}

export function adoptRenderedBaseline(form) {
    if (! form?.elements) {
        return;
    }

    for (const field of form.elements) {
        const type = String(field.type || '').toLowerCase();

        if (type === 'hidden' || type === 'button' || type === 'submit' || type === 'reset') {
            continue;
        }

        if (type === 'checkbox' || type === 'radio') {
            field.defaultChecked = field.checked;

            continue;
        }

        if (field.options) {
            const selected = field.value;

            for (const option of field.options) {
                option.defaultSelected = option.value === selected;
            }

            continue;
        }

        if ('defaultValue' in field) {
            field.defaultValue = field.value;
        }
    }
}

/**
 * Hydration adopt that skips controls the advisor already touched.
 * Prevents a deferred boot adopt from marking an in-progress edit as clean.
 */
export function adoptRenderedBaselines(root, { skipFields = null } = {}) {
    if (! root) {
        return;
    }

    const forms = [];

    if (typeof root.matches === 'function' && root.matches('form')) {
        forms.push(root);
    }

    if (typeof root.querySelectorAll === 'function') {
        forms.push(...root.querySelectorAll('form'));
    }

    forms.forEach((form) => {
        if (! skipFields || typeof skipFields.has !== 'function') {
            adoptRenderedBaseline(form);

            return;
        }

        [...(form.elements || [])].forEach((field) => {
            if (skipFields.has(field)) {
                return;
            }

            const type = String(field.type || '').toLowerCase();

            if (type === 'hidden' || type === 'button' || type === 'submit' || type === 'reset') {
                return;
            }

            if (type === 'checkbox' || type === 'radio') {
                field.defaultChecked = field.checked;

                return;
            }

            if (field.options) {
                const selected = field.value;

                for (const option of field.options) {
                    option.defaultSelected = option.value === selected;
                }

                return;
            }

            if ('defaultValue' in field) {
                field.defaultValue = field.value;
            }
        });
    });
}

function nodeHolds(ignore, node) {
    if (! ignore || ! node) {
        return false;
    }

    if (node === ignore) {
        return true;
    }

    if (typeof ignore.contains === 'function' && ignore.contains(node)) {
        return true;
    }

    return typeof node.contains === 'function' && node.contains(ignore);
}

export function blockedPanelIdsFromNodes(nodes, panelIds) {
    const blocked = new Set();

    for (const node of nodes) {
        let current = node;

        while (current) {
            if (current.id && panelIds.includes(current.id)) {
                blocked.add(current.id);
            }

            current = current.parentElement ?? null;
        }
    }

    return blocked;
}

export function draftNodesIn(root) {
    return [
        ...dirtyFormsIn(root),
        ...draftMarkersIn(root),
        ...dirtyControlsOutsideForms(root),
    ];
}

export function blockedPanelIds(root, panelIds, ignore = null) {
    const nodes = draftNodesIn(root).filter((node) => ! nodeHolds(ignore, node));
    const blocked = blockedPanelIdsFromNodes(nodes, panelIds);

    // An open authoring modal must survive worksheet morphs / collaboration refresh.
    if (isWorkspaceModalOpen(root) && panelIds.includes('workspace-modal-host')) {
        blocked.add('workspace-modal-host');
    }

    return blocked;
}

export function cancelWouldReplace(boundary, draftNodes, discarded) {
    if (! boundary) {
        return false;
    }

    for (const draft of draftNodes) {
        if (nodeHolds(discarded, draft)) {
            continue;
        }

        if (boundary === draft) {
            return false;
        }

        if (typeof boundary.contains === 'function' && boundary.contains(draft)) {
            return false;
        }

        let current = draft.parentElement ?? null;

        while (current) {
            if (current === boundary) {
                return false;
            }

            current = current.parentElement ?? null;
        }
    }

    return true;
}

export function versionToSubmit(form, liveVersion, fieldName, options = {}) {
    const own = form?.querySelector?.(`input[name="${String(fieldName).replace(/\\/g, '\\\\').replace(/"/g, '\\"')}"]`)?.value ?? '';
    const dirty = options.dirty ?? formHasChanges(form);

    if (dirty) {
        return String(own ?? '');
    }

    if (liveVersion !== undefined && liveVersion !== null && String(liveVersion) !== '') {
        return String(liveVersion);
    }

    return String(own ?? '');
}

export function inputIsInsideDraft(input) {
    const form = input?.closest?.('form');

    if (form && formHasChanges(form)) {
        return true;
    }

    return Boolean(input?.closest?.('[data-worksheet-draft="1"]'));
}
