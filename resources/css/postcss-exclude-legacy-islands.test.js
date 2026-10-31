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
    assert.equal(run('::-webkit-scrollbar-thumb:hover { color: red; }'), ':not(.ark-legacy *)::-webkit-scrollbar-thumb:hover { color: red; }');
    assert.equal(run('*, ::before { box-sizing: border-box; }'), '*:not(.ark-legacy *), :not(.ark-legacy *)::before { box-sizing: border-box; }');
});

test('whitespace around a list item does not become a descendant combinator', () => {
    assert.equal(
        run('.a,\n::-webkit-datetime-edit-text ,\n  .b\n{ padding: 0; }'),
        '.a:not(.ark-legacy *),\n:not(.ark-legacy *)::-webkit-datetime-edit-text ,\n  .b:not(.ark-legacy *)\n{ padding: 0; }',
    );
});

test('complex selectors are scoped on their subject only', () => {
    assert.equal(
        run('.navbar > .nav-item + .nav-item ~ [class*="col sm"]:is(.a, .b):has(> .c):not(.d) { gap: 0; }'),
        '.navbar > .nav-item + .nav-item ~ [class*="col sm"]:is(.a, .b):has(> .c):not(.d):not(.ark-legacy *) { gap: 0; }',
    );
    assert.equal(
        run('[data-bs-theme="dark"] .card :where(h1, h2) { color: inherit; }'),
        '[data-bs-theme="dark"] .card :where(h1, h2):not(.ark-legacy *) { color: inherit; }',
    );
});

test('rules nested in conditional at-rules are scoped', () => {
    assert.equal(
        run('@media (min-width: 768px) { @supports (display: grid) { .row { display: grid; } } }'),
        '@media (min-width: 768px) { @supports (display: grid) { .row:not(.ark-legacy *) { display: grid; } } }',
    );
});

test('keyframe steps and declaration-only at-rules are left alone', () => {
    assert.equal(run('@keyframes spin { from { opacity: 0; } to { opacity: 1; } }'), '@keyframes spin { from { opacity: 0; } to { opacity: 1; } }');
    assert.equal(run('@-webkit-keyframes spin { 50% { opacity: 0; } }'), '@-webkit-keyframes spin { 50% { opacity: 0; } }');
    assert.equal(run('@font-face { font-family: x; }'), '@font-face { font-family: x; }');
});

test('only the Tabler entry is rewritten', () => {
    assert.equal(run('.grid { display: grid; }', '/app/resources/css/app.css'), '.grid { display: grid; }');
    assert.equal(run('.grid { display: grid; }', 'C:\\app\\resources\\css\\tabler.css'), '.grid:not(.ark-legacy *) { display: grid; }');
});
