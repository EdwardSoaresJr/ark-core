import assert from 'node:assert/strict';
import test from 'node:test';
import { arkLaborMemorySuggest } from '../../resources/js/ark-labor-memory-suggest.js';

function createSuggest(overrides = {}) {
    const api = arkLaborMemorySuggest({
        suggestUrl: 'https://example.test/suggest',
        eventUrl: null,
        repairOrderId: 1,
        surface: 'labor_entry',
    });

    Object.assign(api, {
        $refs: { descriptionInput: { focus() {} } },
        $nextTick(fn) {
            fn();
        },
        ...overrides,
    });

    return api;
}

test('choosing a suggestion closes the list and skips the follow-up focus fetch', async () => {
    const api = createSuggest();
    let fetchCalled = false;

    api.suggestions = [{ id: 1, text: 'Replace front brake pads' }];
    api.suggestionsOpen = true;
    api.activeIndex = 0;
    api.fetchSuggestions = async () => {
        fetchCalled = true;
        api.suggestionsOpen = true;
    };

    api.chooseSuggestion(api.suggestions[0]);
    api.handleFocus();

    assert.equal(api.suggestionsOpen, false);
    assert.equal(api.description, 'Replace front brake pads');
    assert.equal(fetchCalled, false);
});

test('closing suggestions invalidates in-flight fetches so they cannot reopen the list', async () => {
    const api = createSuggest();
    const startedAt = api.requestId;

    api.suggestionsOpen = true;
    api.closeSuggestions();

    assert.equal(api.suggestionsOpen, false);
    assert.ok(api.requestId > startedAt);
});
