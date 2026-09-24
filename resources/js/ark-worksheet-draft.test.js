import assert from 'node:assert/strict';
import test from 'node:test';
import { plainControlChanged } from './ark-form-unsaved.js';
import {
    adoptRenderedBaseline,
    blockedPanelIdsFromNodes,
    cancelWouldReplace,
    versionToSubmit,
} from './ark-worksheet-draft.js';

const chain = (ids) => {
    let parent = null;

    for (const id of ids) {
        parent = { id, parentElement: parent };
    }

    return parent;
};

test('clean worksheet is not held and an unchanged control does not block refresh', () => {
    assert.equal(plainControlChanged({
        tagName: 'TEXTAREA',
        value: 'Brake noise',
        defaultValue: 'Brake noise',
    }), false);
    assert.equal(blockedPanelIdsFromNodes([], ['estimate-lines', 'visit-reason']).size, 0);
});

test('dirty add labor, part, narrative, and visit reason block the panel that contains them', () => {
    const labor = chain(['estimate-lines', 'workspace-modal-host']);
    labor.description = 'Replace pads';
    const part = chain(['estimate-lines', 'workspace-modal-host']);
    const narrative = chain(['estimate-lines', 'workspace-modal-host']);
    const visit = chain(['visit-reason']);

    for (const node of [labor, part, narrative]) {
        const blocked = blockedPanelIdsFromNodes([node], [
            'estimate-lines',
            'workspace-modal-host',
            'visit-reason',
            'estimate-builder-rail',
        ]);

        assert.equal(blocked.has('estimate-lines'), true);
        assert.equal(blocked.has('workspace-modal-host'), true);
        assert.equal(blocked.has('visit-reason'), false);
        assert.equal(blocked.has('estimate-builder-rail'), false);
    }

    const visitBlocked = blockedPanelIdsFromNodes([visit], ['estimate-lines', 'visit-reason']);
    assert.equal(visitBlocked.has('visit-reason'), true);
    assert.equal(visitBlocked.has('estimate-lines'), false);

    assert.equal(plainControlChanged({
        tagName: 'TEXTAREA',
        name: 'description',
        value: 'Replace front pads',
        defaultValue: '',
    }), true);
    assert.equal(plainControlChanged({
        tagName: 'TEXTAREA',
        name: 'customer_states',
        value: 'Squeals when stopping',
        defaultValue: '',
    }), true);
    assert.equal(plainControlChanged({
        tagName: 'TEXTAREA',
        name: 'visit_reason',
        value: 'Customer says it shakes',
        defaultValue: 'Shakes',
    }), true);
});

test('a rendered baseline keeps an opened editor clean until the advisor changes it', () => {
    const field = { type: 'text', value: '125.00', defaultValue: '' };
    const form = { elements: [field, { type: 'hidden', value: 'labor', defaultValue: '' }] };

    adoptRenderedBaseline(form);

    assert.equal(field.defaultValue, '125.00');
    assert.equal(plainControlChanged(field), false);

    field.value = '140.00';
    assert.equal(plainControlChanged(field), true);
});

test('opening a control without changing it is not dirty, including a hidden type field', () => {
    assert.equal(plainControlChanged({
        tagName: 'INPUT',
        type: 'hidden',
        name: 'type',
        value: 'labor',
        defaultValue: '',
    }), false);
    assert.equal(plainControlChanged({
        tagName: 'INPUT',
        type: 'text',
        name: 'description',
        value: '',
        defaultValue: '',
    }), false);
});

const nest = (ids) => {
    let parent = null;
    const nodes = {};

    for (const id of ids) {
        const node = { id, parentElement: parent, contains() { return false; } };
        nodes[id] = node;
        parent = node;
    }

    return nodes;
};

test('cancelling one draft inside estimate-lines leaves the other draft and its version', () => {
    const laborTree = nest(['estimate-lines', 'workspace-modal-host', 'workspace-line-create-1']);
    const narrativeTree = nest(['estimate-lines', 'workspace-modal-host', 'workspace-concern-narrative-2']);
    const lines = laborTree['estimate-lines'];
    const host = laborTree['workspace-modal-host'];
    const labor = laborTree['workspace-line-create-1'];
    const narrative = narrativeTree['workspace-concern-narrative-2'];
    narrative.parentElement = host;
    labor.value = 'Replace front pads';
    labor.token = '4';
    narrative.value = 'Squeals when stopping';
    narrative.token = '4';

    const drafts = [labor, narrative];
    const panels = [lines, host, labor, narrative];

    const replaced = panels
        .filter((node) => cancelWouldReplace(node, drafts, narrative))
        .map((node) => node.id);

    assert.deepEqual(replaced, ['workspace-concern-narrative-2']);
    assert.equal(labor.value, 'Replace front pads');
    assert.equal(labor.token, '4');
    assert.equal(cancelWouldReplace(lines, drafts, narrative), false);
    assert.equal(drafts.some((node) => node !== narrative), true);

    const reverse = panels
        .filter((node) => cancelWouldReplace(node, drafts, labor))
        .map((node) => node.id);

    assert.deepEqual(reverse, ['workspace-line-create-1']);
    assert.equal(narrative.value, 'Squeals when stopping');
    assert.equal(narrative.token, '4');
});

test('a dirty draft keeps the version it started from', () => {
    const form = {
        querySelector() {
            return { value: '4' };
        },
    };

    assert.equal(versionToSubmit(form, 5, 'opened_estimate_version', { dirty: true }), '4');
    assert.equal(versionToSubmit(form, 5, 'opened_estimate_version', { dirty: false }), '5');
});
