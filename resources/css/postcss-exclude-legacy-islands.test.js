import assert from 'node:assert/strict';
import test from 'node:test';
import postcss from 'postcss';
import excludeLegacyIslands from './postcss-exclude-legacy-islands.js';

const run = (css, from = '/app/resources/css/tabler.css') => postcss([excludeLegacyIslands({ entry: 'resources/css/tabler.css' })])
    .process(css, { from })
    .css;

test('every selector in a list is kept out of legacy islands', () => {
    assert.equal(run('.grid, .p-4 { color: red; }'), '.grid:not(.ark-legacy *), .p-4:not(.ark-legacy *) { color: red; }');
});

test('the scope goes on the subject, before any pseudo-element', () => {
    assert.equal(run('.card .btn:hover::before { content: ""; }'), '.card .btn:hover:not(.ark-legacy *)::before { content: ""; }');
    assert.equal(run('.form-control:after { content: ""; }'), '.form-control:not(.ark-legacy *):after { content: ""; }');
});

test('keyframe steps are left alone', () => {
    assert.equal(run('@keyframes spin { from { opacity: 0; } to { opacity: 1; } }'), '@keyframes spin { from { opacity: 0; } to { opacity: 1; } }');
});

test('only the Tabler entry is rewritten', () => {
    assert.equal(run('.grid { display: grid; }', '/app/resources/css/app.css'), '.grid { display: grid; }');
});
