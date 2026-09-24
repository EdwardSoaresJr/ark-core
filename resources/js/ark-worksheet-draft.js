import { fieldHasChanges, formHasChanges } from './ark-form-unsaved.js';

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
        if (field.closest?.('form') || field.closest?.('.ops-mileage-inline')) {
            return false;
        }

        if (typeof field.getClientRects === 'function' && field.getClientRects().length === 0) {
            return false;
        }

        return fieldHasChanges(field);
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

    return blockedPanelIdsFromNodes(nodes, panelIds);
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
