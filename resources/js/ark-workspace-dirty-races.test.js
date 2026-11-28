import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { plainControlChanged } from './ark-form-unsaved.js';
import {
    adoptRenderedBaselines,
    adoptSnapshotAsBaseline,
    captureFormBaselineSnapshot,
} from './ark-worksheet-draft.js';
import { dirtyAfterSaveAttempt } from './ark-worksheet-autosave.js';

const bindingsSource = readFileSync(new URL('./ark-workspace-dirty-bindings.js', import.meta.url), 'utf8');
const continuitySource = readFileSync(new URL('./ark-worksheet-continuity.js', import.meta.url), 'utf8');

function textField(value, defaultValue = '') {
    return {
        type: 'text',
        name: 'concern',
        tagName: 'INPUT',
        value,
        defaultValue,
        disabled: false,
        readOnly: false,
    };
}

test('A: deferred hydration adopt skips fields the advisor already touched', () => {
    const field = textField('Jordan typed early', '');
    const form = { elements: [field] };
    const root = {
        matches: () => false,
        querySelectorAll: () => [form],
    };
    const touched = new WeakSet([field]);

    adoptRenderedBaselines(root, { skipFields: touched });

    assert.equal(field.defaultValue, '');
    assert.equal(plainControlChanged(field), true);
});

test('A: untouched hydrated field is adopted clean', () => {
    const field = textField('Alpine filled', '');
    const form = { elements: [field] };
    const root = {
        matches: () => false,
        querySelectorAll: () => [form],
    };

    adoptRenderedBaselines(root, { skipFields: new WeakSet() });

    assert.equal(field.defaultValue, 'Alpine filled');
    assert.equal(plainControlChanged(field), false);
});

test('B: successful save of A keeps dirty when live value moved to B', () => {
    const field = textField('A', 'prior');
    const form = { elements: [field] };
    const snapshot = captureFormBaselineSnapshot(form);

    field.value = 'B';
    adoptSnapshotAsBaseline(form, snapshot);

    assert.equal(field.defaultValue, 'A');
    assert.equal(plainControlChanged(field), true);
});

test('B: successful save of A is clean when live value stayed A', () => {
    const field = textField('A', 'prior');
    const form = { elements: [field] };
    const snapshot = captureFormBaselineSnapshot(form);

    adoptSnapshotAsBaseline(form, snapshot);

    assert.equal(field.defaultValue, 'A');
    assert.equal(plainControlChanged(field), false);
});

test('C: out-of-order completion ignores stale settle when generation advanced', () => {
    assert.match(continuitySource, /_saveGeneration/);
    assert.match(continuitySource, /settleGeneration !== this\._saveGeneration/);
    assert.match(continuitySource, /adoptSnapshotAsBaseline/);
    assert.match(continuitySource, /captureFormBaselineSnapshot/);
});

test('D: failed autosave stays dirty while editing continues', () => {
    assert.equal(dirtyAfterSaveAttempt({
        ok: false,
        hadUnsavedChanges: true,
        formDiffers: true,
    }), true);

    const field = textField('draft', 'saved');
    assert.equal(plainControlChanged(field), true);
    field.value = 'draft continued';
    assert.equal(plainControlChanged(field), true);
});

test('bindings adopt immediately and again on rAF while skipping touched fields', () => {
    assert.match(bindingsSource, /adoptHydrationBaselines/);
    assert.match(bindingsSource, /skipFields: touchedFields/);
    assert.match(bindingsSource, /touchedFields\.add/);
    assert.match(bindingsSource, /requestAnimationFrame/);
});

test('busy gate still serializes overlapping worksheet POSTs', () => {
    assert.match(continuitySource, /if \(this\.worksheetBusyPending\)/);
    assert.match(continuitySource, /Still saving/);
});
