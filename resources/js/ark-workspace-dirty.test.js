import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { plainControlChanged } from './ark-form-unsaved.js';
import { adoptRenderedBaseline } from './ark-worksheet-draft.js';
import {
    flushDepartingWorkspaceDirty,
    workspaceNeedsDiscardConfirm,
} from './ark-workspace-dirty-policy.js';
import { dirtyAfterSaveAttempt } from './ark-worksheet-autosave.js';

const bindingsSource = readFileSync(new URL('./ark-workspace-dirty-bindings.js', import.meta.url), 'utf8');
const tabsSource = readFileSync(new URL('./ark-workspace-tabs.js', import.meta.url), 'utf8');
const continuitySource = readFileSync(new URL('./ark-worksheet-continuity.js', import.meta.url), 'utf8');
const modalSource = readFileSync(new URL('./ark-workspace-modal.js', import.meta.url), 'utf8');

test('flush departing workspace only when boot key still matches the page being left', () => {
    let calls = 0;
    const syncDirty = () => {
        calls += 1;

        return false;
    };

    assert.equal(flushDepartingWorkspaceDirty({
        fromKey: 'customer:1',
        bootKey: 'customer:1',
        syncDirty,
    }), true);
    assert.equal(calls, 1);

    assert.equal(flushDepartingWorkspaceDirty({
        fromKey: 'customer:1',
        bootKey: 'vehicle:9',
        syncDirty,
    }), false);
    assert.equal(calls, 1);

    assert.equal(flushDepartingWorkspaceDirty({
        fromKey: 'customer:1',
        bootKey: 'customer:1',
        syncDirty: null,
    }), false);
});

test('active workspace uses live dirty; inactive uses sticky session only', () => {
    assert.equal(workspaceNeedsDiscardConfirm({
        key: 'ro:1',
        activeKey: 'ro:1',
        sessionDirty: true,
        liveDirty: false,
        hasLiveSync: true,
    }), false);

    assert.equal(workspaceNeedsDiscardConfirm({
        key: 'ro:1',
        activeKey: 'ro:1',
        sessionDirty: false,
        liveDirty: true,
        hasLiveSync: true,
    }), true);

    assert.equal(workspaceNeedsDiscardConfirm({
        key: 'ro:1',
        activeKey: 'ro:2',
        sessionDirty: true,
        liveDirty: false,
        hasLiveSync: true,
    }), true);

    assert.equal(workspaceNeedsDiscardConfirm({
        key: 'ro:1',
        activeKey: 'ro:2',
        sessionDirty: false,
        liveDirty: true,
        hasLiveSync: true,
    }), false);
});

test('read-only workspace with adopted hydration baseline does not look dirty', () => {
    const field = {
        type: 'text',
        tagName: 'INPUT',
        value: 'Jordan Hale',
        defaultValue: '',
        disabled: false,
        readOnly: false,
    };
    const form = { elements: [field] };

    adoptRenderedBaseline(form);

    assert.equal(field.defaultValue, 'Jordan Hale');
    assert.equal(plainControlChanged(field), false);
});

test('failed autosave keeps dirty; successful save with no remaining form drift is clean', () => {
    assert.equal(dirtyAfterSaveAttempt({
        ok: false,
        hadUnsavedChanges: true,
        formDiffers: true,
    }), true);

    assert.equal(dirtyAfterSaveAttempt({
        ok: true,
        hadUnsavedChanges: true,
        formDiffers: false,
    }), false);

    assert.equal(dirtyAfterSaveAttempt({
        ok: true,
        hadUnsavedChanges: true,
        formDiffers: true,
    }), true);
});

test('dirty bindings adopt rendered baselines on init then syncDirty', () => {
    assert.match(bindingsSource, /adoptHydrationBaselines/);
    assert.match(bindingsSource, /skipFields: touchedFields/);
    assert.match(bindingsSource, /requestAnimationFrame/);
    assert.match(bindingsSource, /syncDirty\(\)/);
});

test('cross-workspace navigation flushes departing dirty before location.assign', () => {
    assert.match(tabsSource, /flushDepartingWorkspaceDirty/);
    assert.match(tabsSource, /bootKey:\s*window\.__ARK_WORKSPACE__\?\.boot\?\.key/);
    assert.match(tabsSource, /window\.location\.assign\(route\)/);
});

test('confirmed switch-without-saving clears sticky dirty on the departing key', () => {
    assert.match(tabsSource, /Switch without saving\?/);
    assert.match(tabsSource, /setDirtyKey\(fromKey, false\)/);
});

test('successful settleSaveDirty adopts the submitted snapshot baseline before syncDirty', () => {
    assert.match(continuitySource, /adoptSnapshotAsBaseline\(form, snapshot\)/);
    assert.match(continuitySource, /captureFormBaselineSnapshot/);
    assert.match(continuitySource, /settleSaveDirty\(form, true\)/);
    assert.match(continuitySource, /settleSaveDirty\(form, false\)/);
});

test('modal discard keeps its own confirm copy; closed modal is ignored for page dirty', () => {
    assert.match(modalSource, /Discard unsaved changes in this form\?/);
    assert.match(bindingsSource, /#workspace-modal-host\[data-workspace-modal-open="0"\]/);
});
