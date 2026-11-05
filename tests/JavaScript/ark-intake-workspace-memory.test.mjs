import assert from 'node:assert/strict';
import test from 'node:test';

const store = new Map();
const document = new EventTarget();
document.readyState = 'complete';
document.querySelector = () => null;

const window = new EventTarget();
window.__ARK_WORKSPACE__ = { enabled: true };
window.location = { pathname: '/app/intake/new', search: '?ws=abc123def456', origin: 'https://ark.test' };
window.scrollY = 0;
window.scrollTo = () => {};
window.setTimeout = setTimeout;
window.clearTimeout = clearTimeout;

globalThis.window = window;
globalThis.document = document;
globalThis.requestAnimationFrame = (callback) => callback();
globalThis.sessionStorage = {
    getItem: (key) => (store.has(key) ? store.get(key) : null),
    setItem: (key, value) => store.set(key, String(value)),
    removeItem: (key) => store.delete(key),
};

const draft = { query: '', selectedCustomerId: null, firstName: 'Draftly', lastName: 'Restorecase', city: 'Testville' };

store.set('ark:intake-memory:abc123def456', JSON.stringify({
    version: 1,
    scrollY: 0,
    pathname: '/app/intake/new',
    search: '?ws=abc123def456',
    draft,
    savedAt: Date.now(),
}));

const {
    initIntakeWorkspaceMemory,
    intakeDraftIsEmpty,
    intakeSearchIsBare,
    intakeWorkspaceIsUntouched,
} = await import('../../resources/js/ark-intake-workspace-memory.js');

const savedWorkspace = (workspaceId, search, savedDraft) => store.set(`ark:intake-memory:${workspaceId}`, JSON.stringify({
    version: 1,
    scrollY: 0,
    pathname: '/app/intake/new',
    search,
    draft: savedDraft,
    savedAt: Date.now(),
}));

test('a check in draft is empty only when nothing was entered', () => {
    assert.equal(intakeDraftIsEmpty(null), true);
    assert.equal(intakeDraftIsEmpty({ query: '  ', selectedCustomerId: null, firstName: '', customerType: 'Retail' }), true);
    assert.equal(intakeDraftIsEmpty({ query: '719555' }), false);
    assert.equal(intakeDraftIsEmpty({ selectedCustomerId: 12 }), false);
    assert.equal(intakeDraftIsEmpty({ lastName: 'Rivera' }), false);
    assert.equal(intakeDraftIsEmpty({ customerType: 'Fleet' }), false);
});

test('a check in address is bare only with nothing but its workspace id', () => {
    assert.equal(intakeSearchIsBare(''), true);
    assert.equal(intakeSearchIsBare('?ws=abc123def456'), true);
    assert.equal(intakeSearchIsBare('?ws=abc123def456&q=rivera'), false);
    assert.equal(intakeSearchIsBare('?ws=abc123def456&customer_id=4'), false);
    assert.equal(intakeSearchIsBare('?phone=%2B17195550100'), false);
    assert.equal(intakeSearchIsBare('?lead_id=9'), false);
});

test('a check in workspace is untouched only when this window saved it empty', () => {
    savedWorkspace('emptyws000001', '?ws=emptyws000001', { query: '', firstName: '', customerType: 'Retail' });
    savedWorkspace('typedws000001', '?ws=typedws000001', { query: '', firstName: 'Jordan' });
    savedWorkspace('searchws00001', '?ws=searchws00001&q=jordan', null);

    assert.equal(intakeWorkspaceIsUntouched('emptyws000001'), true);
    assert.equal(intakeWorkspaceIsUntouched('typedws000001'), false);
    assert.equal(intakeWorkspaceIsUntouched('searchws00001'), false);
    assert.equal(intakeWorkspaceIsUntouched('otherwindow01'), false);
    assert.equal(intakeWorkspaceIsUntouched('service'), false);
});

test('check in boots the intake memory api and hands a saved draft to the form once', () => {
    const dispatched = [];
    document.addEventListener('ark:intake-memory-restore', (event) => dispatched.push(event.detail.draft));

    assert.doesNotThrow(() => initIntakeWorkspaceMemory());

    const api = window.ARK?.intakeWorkspaceMemory;

    assert.equal(typeof api?.consumeDraftRestore, 'function');
    assert.deepEqual(dispatched, [draft]);
    assert.deepEqual(api.consumeDraftRestore(), draft);
    assert.equal(api.consumeDraftRestore(), null);
});
