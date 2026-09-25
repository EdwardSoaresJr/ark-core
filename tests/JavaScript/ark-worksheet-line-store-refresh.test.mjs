import assert from 'node:assert/strict';
import test from 'node:test';
import { createServer } from 'vite';
import { versionToSubmit } from '../../resources/js/ark-worksheet-draft.js';

function node(id, { tag = 'div', draft = false, parent = null } = {}) {
    const el = {
        id,
        tagName: tag,
        parentElement: parent,
        children: [],
        dataset: draft ? { worksheetDraft: '1' } : {},
        wasReplaced: false,
        matches(selector) {
            return draft && selector === '[data-worksheet-draft="1"]';
        },
        querySelector() {
            return null;
        },
        querySelectorAll(selector) {
            return collect(el, selector);
        },
        contains(other) {
            let current = other;

            while (current) {
                if (current === el) {
                    return true;
                }

                current = current.parentElement ?? null;
            }

            return false;
        },
        getAttribute() {
            return null;
        },
        cloneNode() {
            const copy = node(id, { tag, draft });

            for (const child of el.children) {
                const childCopy = child.cloneNode();
                childCopy.parentElement = copy;
                copy.children.push(childCopy);
            }

            return copy;
        },
        replaceWith(next) {
            el.wasReplaced = true;
            const parentNode = el.parentElement;

            if (! parentNode) {
                return;
            }

            const index = parentNode.children.indexOf(el);

            if (index >= 0) {
                parentNode.children[index] = next;
            }

            next.parentElement = parentNode;
            el.parentElement = null;
        },
    };

    if (parent) {
        parent.children.push(el);
    }

    return el;
}

function collect(root, selector) {
    const found = [];

    const walk = (current) => {
        for (const child of current.children ?? []) {
            if (selector === '[data-worksheet-draft="1"]' && child.dataset?.worksheetDraft === '1') {
                found.push(child);
            }

            if (selector === 'form' && child.tagName === 'FORM') {
                found.push(child);
            }

            walk(child);
        }
    };

    walk(root);

    return found;
}

function documentFrom(root) {
    root.getElementById = (id) => {
        const walk = (current) => {
            if (current.id === id) {
                return current;
            }

            for (const child of current.children ?? []) {
                const match = walk(child);

                if (match) {
                    return match;
                }
            }

            return null;
        };

        return walk(root);
    };

    root.querySelectorAll = (selector) => collect(root, selector);

    return root;
}

function worksheetTree() {
    const root = node('');
    const lines = node('estimate-lines', { parent: root });
    const host = node('workspace-modal-host', { parent: lines });
    const form = node('workspace-line-create-1', { tag: 'FORM', parent: host });
    form.dataset.refreshScope = 'worksheet';
    node('', { draft: true, parent: form });
    node('estimate-total-panel', { parent: root });

    return { root: documentFrom(root), lines, host, form };
}

function freshTree() {
    const root = node('');
    node('estimate-lines', { parent: root });
    node('workspace-modal-host', { parent: root });
    node('estimate-total-panel', { parent: root });

    return documentFrom(root);
}

test('a dirty line composer uses the live estimate version', () => {
    const form = {
        querySelector() {
            return { value: '4' };
        },
    };

    assert.equal(versionToSubmit(form, '9', 'opened_estimate_version', { dirty: true }), '4');
    assert.equal(versionToSubmit(form, '9', 'opened_estimate_version', { dirty: false }), '9');
});

test('a saved line composer does not block the worksheet refresh', async () => {
    const server = await createServer({
        server: { middlewareMode: true },
        appType: 'custom',
        logLevel: 'error',
    });

    try {
        const { arkWorksheetContinuity } = await server.ssrLoadModule('/resources/js/ark-worksheet-continuity.js');
        const api = arkWorksheetContinuity({
            worksheetScopeId: 'estimate-lines',
            continuityPanelIds: ['workspace-modal-host', 'estimate-total-panel'],
        });

        globalThis.window = {};
        globalThis.HTMLFormElement = class HTMLFormElement {};

        const blocked = worksheetTree();
        globalThis.document = blocked.root;
        api.replaceFromDocument(freshTree(), blocked.form, null);
        assert.equal(blocked.lines.wasReplaced, false);
        assert.equal(blocked.host.wasReplaced, false);

        const saved = worksheetTree();
        globalThis.document = saved.root;
        const result = api.replaceFromDocument(freshTree(), saved.form, saved.form);

        assert.equal(saved.lines.wasReplaced, true);
        assert.equal(result.replaced > 0, true);
    } finally {
        await server.close();
        delete globalThis.document;
        delete globalThis.window;
        delete globalThis.HTMLFormElement;
    }
});
