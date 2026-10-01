export const CONTINUITY_REFRESH_FAILED_MESSAGE = 'Saved. Some information could not be refreshed. Reload to see the current repair order.';

export const EDITOR_PRESENTATION_ID = 'worksheet-line-editor';

// A scope names which inputs changed. Several scopes may name the same region.
// That region is still one element and is applied once.
const SCOPE_REGIONS = {
    lines: ['line', 'worksheet-count', 'totals', 'concern-money', 'authorized-money', 'financial-position', 'invoice-posture', 'authorization-rail'],
    authorization: ['concern-authorization', 'authorized-money', 'financial-position', 'invoice-posture', 'line', 'authorization-rail'],
    settlement: ['settlement', 'financial-position'],
    identity: [],
    workflow: ['workflow'],
};

export function continuityRegionKinds(scope) {
    return SCOPE_REGIONS[scope] ?? null;
}

function regionsIn(root) {
    if (! root?.querySelectorAll) {
        return [];
    }

    return [...root.querySelectorAll('[data-continuity-region]')];
}

function containsContinuityRegion(region) {
    return regionsIn(region).length > 0;
}

function nodeInside(boundary, node) {
    if (! boundary || ! node) {
        return false;
    }

    let current = node;

    while (current) {
        if (current === boundary) {
            return true;
        }

        current = current.parentElement ?? null;
    }

    return false;
}

function controlChanged(field) {
    if (! field || field.disabled || field.readOnly) {
        return false;
    }

    const type = String(field.type || '').toLowerCase();

    if (['hidden', 'button', 'submit', 'reset', 'file', 'image'].includes(type)) {
        return false;
    }

    if (type === 'checkbox' || type === 'radio') {
        return Boolean(field.checked) !== Boolean(field.defaultChecked);
    }

    if (field.tagName === 'SELECT') {
        const options = [...(field.options ?? [])];
        const selected = options.filter((option) => option.selected).map((option) => option.value).join('\n');
        const initial = options.filter((option) => option.defaultSelected).map((option) => option.value).join('\n');

        return selected !== initial;
    }

    return String(field.value ?? '') !== String(field.defaultValue ?? '');
}

export function regionOwnsDirtyState(region, ignore = null) {
    if (! region?.querySelectorAll) {
        return false;
    }

    for (const field of region.querySelectorAll('input, textarea, select')) {
        if (nodeInside(ignore, field)) {
            continue;
        }

        if (controlChanged(field)) {
            return true;
        }
    }

    return false;
}

function regionId(node) {
    if (! node) {
        return '';
    }

    return node.id || node.getAttribute?.('id') || '';
}

function regionKind(node) {
    return node?.getAttribute?.('data-continuity-region') ?? '';
}

function ownedRegions(root, owned) {
    return regionsIn(root).filter((region) => owned.has(regionKind(region)));
}

function matchingFreshRegion(freshDocument, region) {
    const id = regionId(region);
    const fresh = id ? freshDocument.getElementById?.(id) : null;

    if (! fresh || regionKind(fresh) !== regionKind(region)) {
        return null;
    }

    return fresh;
}

function detach(node) {
    node?.remove?.();
}

function insertAfter(reference, node) {
    reference.parentElement.insertBefore(node, reference.nextSibling ?? null);
}

function placeFreshRegion(currentDocument, freshRegion) {
    const parent = freshRegion.parentElement;
    const mount = parent ? currentDocument.getElementById?.(regionId(parent)) : null;

    if (! mount || regionKind(mount)) {
        return null;
    }

    const clone = freshRegion.cloneNode(true);
    const siblings = [...parent.childNodes].filter((node) => node.nodeType === 1);
    const index = siblings.indexOf(freshRegion);

    for (let cursor = index - 1; cursor >= 0; cursor -= 1) {
        const siblingId = regionId(siblings[cursor]);
        const currentSibling = siblingId ? currentDocument.getElementById?.(siblingId) : null;

        if (currentSibling && currentSibling.parentElement === mount) {
            insertAfter(currentSibling, clone);

            return clone;
        }
    }

    for (let cursor = index + 1; cursor < siblings.length; cursor += 1) {
        const siblingId = regionId(siblings[cursor]);
        const currentSibling = siblingId ? currentDocument.getElementById?.(siblingId) : null;

        if (currentSibling && currentSibling.parentElement === mount) {
            mount.insertBefore(clone, currentSibling);

            return clone;
        }
    }

    mount.append(clone);

    return clone;
}

function walkElements(node, visit) {
    if (! node) {
        return;
    }

    if (node.nodeType === 1) {
        visit(node);
    }

    const children = node.childNodes;

    if (! children) {
        return;
    }

    for (const child of children) {
        walkElements(child, visit);
    }
}

function mountMissingConcernPresentation(currentDocument, freshDocument, owned) {
    const mountsConcerns = ['concern-money', 'line', 'worksheet-count', 'concern-authorization']
        .some((kind) => owned.has(kind));

    if (! mountsConcerns) {
        return { failed: [], placed: [] };
    }

    const failed = [];
    const placedNodes = [];
    const freshSections = [];

    walkElements(freshDocument, (node) => {
        if (node.tagName === 'SECTION' && String(node.id || '').startsWith('concern-')) {
            freshSections.push(node);
        }
    });

    for (const section of freshSections) {
        if (! section.id || currentDocument.getElementById?.(section.id)) {
            continue;
        }

        const placed = placeFreshRegion(currentDocument, section);

        if (! placed) {
            failed.push(section.id);

            continue;
        }

        placedNodes.push(placed);

        const concernId = section.id.slice('concern-'.length);

        walkElements(freshDocument, (node) => {
            if (node.getAttribute?.('data-concern-authoring') !== concernId || ! node.id) {
                return;
            }

            if (currentDocument.getElementById?.(node.id)) {
                return;
            }

            const placedPanel = placeFreshRegion(currentDocument, node);

            if (! placedPanel) {
                failed.push(node.id);

                return;
            }

            placedNodes.push(placedPanel);
        });
    }

    return { failed, placed: placedNodes };
}

export function applyContinuityScope(currentDocument, freshDocument, scope, options = {}) {
    return applyContinuityScopes(currentDocument, freshDocument, [scope], options);
}

export function applyContinuityScopes(currentDocument, freshDocument, scopes, options = {}) {
    const required = new Set((options.requiredIds ?? []).filter(Boolean));
    const applied = [];
    const preserved = [];
    const failed = [];
    const owned = new Set();

    for (const scope of scopes ?? []) {
        const kinds = continuityRegionKinds(scope);

        if (kinds === null) {
            return { ok: false, applied, preserved, failed: ['scope'] };
        }

        for (const kind of kinds) {
            owned.add(kind);
        }
    }

    const ignore = options.ignore ?? null;

    const mounted = mountMissingConcernPresentation(currentDocument, freshDocument, owned);

    for (const id of mounted.failed) {
        failed.push(id);
    }

    for (const node of mounted.placed) {
        try {
            options.initTree?.(node);
        } catch {
            if (node.id) {
                failed.push(node.id);
            }
        }
    }

    for (const region of ownedRegions(currentDocument, owned)) {
        const id = regionId(region);
        const fresh = matchingFreshRegion(freshDocument, region);

        if (containsContinuityRegion(region) || (fresh && containsContinuityRegion(fresh))) {
            failed.push(id || regionKind(region));

            continue;
        }

        if (regionOwnsDirtyState(region, ignore)) {
            preserved.push(id);

            if (required.has(id) || ! fresh) {
                failed.push(id);
            }

            continue;
        }

        if (! fresh) {
            if (! id) {
                failed.push('missing');

                continue;
            }

            try {
                options.destroyTree?.(region);
                detach(region);
                applied.push(id);
            } catch {
                failed.push(id);
            }

            continue;
        }

        try {
            options.destroyTree?.(region);
            const next = fresh.cloneNode(true);
            region.replaceWith(next);
            options.initTree?.(next);
            applied.push(id);
        } catch {
            failed.push(id);
        }
    }

    for (const freshRegion of ownedRegions(freshDocument, owned)) {
        const id = regionId(freshRegion);

        if (! id || currentDocument.getElementById?.(id)) {
            continue;
        }

        if (containsContinuityRegion(freshRegion)) {
            failed.push(id);

            continue;
        }

        try {
            const placed = placeFreshRegion(currentDocument, freshRegion);

            if (! placed) {
                failed.push(id);

                continue;
            }

            options.initTree?.(placed);
            applied.push(id);
        } catch {
            failed.push(id);
        }
    }

    for (const id of required) {
        if (! applied.includes(id) && ! failed.includes(id)) {
            failed.push(id);
        }
    }

    // applied: fresh region shown. preserved: unsaved input kept. failed: a required region was not applied.
    // Preserved is not a failure. Failure is the only path that asks for a reload.
    return {
        ok: failed.length === 0,
        applied,
        preserved,
        failed,
    };
}

export function replaceSubmittedRegion(currentDocument, freshDocument, form, options = {}) {
    if (! form?.isConnected || ! form.id) {
        return false;
    }

    if (! form.getAttribute?.('data-continuity-region')) {
        return false;
    }

    const fresh = freshDocument.getElementById?.(form.id);

    if (! fresh || fresh.getAttribute?.('data-continuity-region') !== form.getAttribute('data-continuity-region')) {
        return false;
    }

    if (containsContinuityRegion(form) || containsContinuityRegion(fresh)) {
        return false;
    }

    try {
        options.destroyTree?.(form);
        const next = fresh.cloneNode(true);
        form.replaceWith(next);
        options.initTree?.(next);

        return true;
    } catch {
        return false;
    }
}

export function applyEditorPresentation(currentDocument, freshDocument, options = {}) {
    const current = currentDocument.getElementById?.(EDITOR_PRESENTATION_ID);
    const fresh = freshDocument.getElementById?.(EDITOR_PRESENTATION_ID);

    if (! current || ! fresh) {
        return false;
    }

    options.destroyTree?.(current);
    const next = fresh.cloneNode(true);
    current.replaceWith(next);
    options.initTree?.(next);

    return true;
}
