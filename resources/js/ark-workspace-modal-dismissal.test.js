import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { isWorkspaceModalOpen } from './ark-form-unsaved.js';
import { blockedPanelIds } from './ark-worksheet-draft.js';

const modalSource = readFileSync(new URL('./ark-workspace-modal.js', import.meta.url), 'utf8');
const modalBlade = readFileSync(
    new URL('../views/components/tabler/workspace-modal.blade.php', import.meta.url),
    'utf8',
);
const formUnsavedSource = readFileSync(new URL('./ark-form-unsaved.js', import.meta.url), 'utf8');
const dirtyBindingsSource = readFileSync(new URL('./ark-workspace-dirty-bindings.js', import.meta.url), 'utf8');
const continuitySource = readFileSync(new URL('./ark-worksheet-continuity.js', import.meta.url), 'utf8');

test('workspace modal blade uses a static backdrop and never wires outside-click dismiss', () => {
    assert.match(modalBlade, /data-workspace-modal-static-backdrop/);
    assert.match(modalBlade, /data-bs-backdrop="static"/);
    assert.match(modalBlade, /data-workspace-modal-open="0"/);
    assert.doesNotMatch(modalBlade, /@click\.self=.*onBackdrop/);
    assert.doesNotMatch(modalBlade, /onBackdrop\(\)/);
});

test('requestClose confirms dirty drafts and onBackdrop is a no-op', () => {
    assert.match(modalSource, /hasUnsavedModalChanges/);
    assert.match(modalSource, /Discard unsaved changes in this form\?/);
    assert.match(modalSource, /discardModalDrafts/);
    assert.match(modalSource, /onBackdrop\(\)\s*\{\s*return;/);
    assert.match(modalSource, /syncOpenAttr/);
    assert.match(modalSource, /data-workspace-modal-open/);
});

test('closed modal drafts are ignored by workspace dirty tracking', () => {
    assert.match(formUnsavedSource, /#workspace-modal-host\[data-workspace-modal-open="0"\]/);
    assert.match(dirtyBindingsSource, /#workspace-modal-host\[data-workspace-modal-open="0"\]/);
    assert.match(formUnsavedSource, /isWorkspaceModalOpen/);
});

test('open modal host is detected and blocks continuity remount', () => {
    const host = {
        id: 'workspace-modal-host',
        getAttribute(name) {
            return name === 'data-workspace-modal-open' ? '1' : null;
        },
        parentElement: null,
        querySelectorAll() {
            return [];
        },
    };
    const root = {
        getElementById(id) {
            return id === 'workspace-modal-host' ? host : null;
        },
        querySelectorAll() {
            return [];
        },
    };

    assert.equal(isWorkspaceModalOpen(root), true);
    assert.equal(isWorkspaceModalOpen({
        getElementById() {
            return {
                getAttribute() {
                    return '0';
                },
            };
        },
    }), false);

    const blocked = blockedPanelIds(root, ['estimate-lines', 'workspace-modal-host', 'estimate-total-panel']);

    assert.equal(blocked.has('workspace-modal-host'), true);
    assert.equal(blocked.has('estimate-lines'), false);
    assert.match(continuitySource, /isWorkspaceModalOpen\(\)/);
    assert.match(continuitySource, /blocked\.add\('workspace-modal-host'\)/);
});
