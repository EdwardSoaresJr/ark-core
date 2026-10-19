import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { plainControlChanged } from './ark-form-unsaved.js';
import {
    dirtyAfterSaveAttempt,
    editShouldAutosave,
    focusReturnPlan,
    refreshPresentsAsSaving,
} from './ark-worksheet-autosave.js';
import { adoptRenderedBaseline } from './ark-worksheet-draft.js';

const continuitySource = readFileSync(new URL('./ark-worksheet-continuity.js', import.meta.url), 'utf8');
const collaborationSource = readFileSync(new URL('./ark-worksheet-collaboration.js', import.meta.url), 'utf8');
const dirtySource = readFileSync(new URL('./ark-workspace-dirty-bindings.js', import.meta.url), 'utf8');
const mileageSource = readFileSync(new URL('./ark-repair-order-mileage.js', import.meta.url), 'utf8');

function refreshWorksheetSource() {
    const start = continuitySource.indexOf('async refreshWorksheet');
    const end = continuitySource.indexOf('async editLine');

    assert.ok(start > 0 && end > start);

    return continuitySource.slice(start, end);
}

test('returning to an unchanged repair order does not save or show Saving', () => {
    const plan = focusReturnPlan({
        wasHidden: true,
        hiddenNow: false,
        socketPhase: 'live',
    });

    assert.equal(plan.save, false);
    assert.equal(plan.refresh, false);
    assert.equal(plan.showSaving, false);
    assert.equal(refreshPresentsAsSaving({}), false);
    assert.match(collaborationSource, /focusReturnPlan/);
    assert.match(collaborationSource, /if \(plan\.refresh\)/);
    assert.doesNotMatch(refreshWorksheetSource(), /activeElement\.blur/);
    assert.doesNotMatch(refreshWorksheetSource(), /beginWorksheetBusy\(\)/);
});

test('repeated focus and blur cycles do not request a save', () => {
    for (let cycle = 0; cycle < 4; cycle += 1) {
        const hidden = focusReturnPlan({
            wasHidden: false,
            hiddenNow: true,
            socketPhase: 'live',
        });
        const visible = focusReturnPlan({
            wasHidden: true,
            hiddenNow: false,
            socketPhase: cycle % 2 === 0 ? 'live' : 'initial',
        });

        assert.equal(hidden.save, false);
        assert.equal(hidden.refresh, false);
        assert.equal(visible.save, false);
        assert.equal(visible.refresh, false);
        assert.equal(editShouldAutosave({ changed: false, reason: 'blur' }), false);
        assert.equal(editShouldAutosave({ changed: false, reason: 'focus' }), false);
    }
});

test('a genuine edit still autosaves and focus alone does not', () => {
    assert.equal(editShouldAutosave({ changed: true, reason: 'input' }), true);
    assert.equal(editShouldAutosave({ changed: true, reason: 'change' }), true);
    assert.equal(editShouldAutosave({ changed: true, reason: 'blur' }), true);
    assert.equal(editShouldAutosave({ changed: true, reason: 'commit' }), true);
    assert.equal(editShouldAutosave({ changed: true, reason: 'focus' }), false);
    assert.equal(editShouldAutosave({ changed: true, reason: 'visibility' }), false);
    assert.equal(editShouldAutosave({ changed: true, reason: 'poll' }), false);
    assert.equal(editShouldAutosave({ changed: true, reason: 'hydrate' }), false);
    assert.match(mileageSource, /editShouldAutosave\(\{ changed, reason \}\)/);
});

test('pending edits remain in place across focus changes', () => {
    const plan = focusReturnPlan({
        wasHidden: true,
        hiddenNow: false,
        socketPhase: 'recovering',
    });

    assert.equal(plan.save, false);
    assert.equal(plan.showSaving, false);
    assert.equal(plan.refresh, true);
    assert.equal(dirtyAfterSaveAttempt({
        ok: false,
        hadUnsavedChanges: true,
        formDiffers: true,
    }), true);
    assert.match(continuitySource, /settleSaveDirty\(form, false\)/);
    assert.match(continuitySource, /adoptRenderedBaselines/);
});

test('background refresh and polling do not present Saving', () => {
    assert.equal(refreshPresentsAsSaving({ showSaving: false }), false);
    assert.equal(refreshPresentsAsSaving({ showSaving: true }), true);
    assert.equal(focusReturnPlan({
        wasHidden: true,
        hiddenNow: false,
        socketPhase: 'recovering',
    }).showSaving, false);
    assert.match(refreshWorksheetSource(), /refreshPresentsAsSaving/);
    assert.match(continuitySource, /worksheetSaving \? 'Still saving…' : 'Still updating this repair order…'/);
});

test('a failed save stays dirty and a successful save follows the form', () => {
    assert.equal(dirtyAfterSaveAttempt({
        ok: false,
        hadUnsavedChanges: true,
        formDiffers: false,
    }), true);
    assert.equal(dirtyAfterSaveAttempt({
        ok: false,
        hadUnsavedChanges: false,
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
    assert.doesNotMatch(dirtySource, /setDirty\(key, false\)/);
    assert.match(continuitySource, /settleSaveDirty\(form, true\)/);
});

test('hydration adopts the rendered value so an unchanged control is clean', () => {
    const field = {
        type: 'text',
        tagName: 'INPUT',
        value: 'Brake noise',
        defaultValue: '',
    };
    const form = { elements: [field] };

    adoptRenderedBaseline(form);

    assert.equal(field.defaultValue, 'Brake noise');
    assert.equal(plainControlChanged(field), false);
});
