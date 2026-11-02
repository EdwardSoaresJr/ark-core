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

const { initIntakeWorkspaceMemory } = await import('../../resources/js/ark-intake-workspace-memory.js');

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
