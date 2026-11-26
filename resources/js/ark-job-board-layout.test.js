import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(new URL('./ark-job-board-layout.js', import.meta.url), 'utf8');
const css = readFileSync(new URL('../css/tabler.css', import.meta.url), 'utf8');

test('mobile Kanban column select syncs a single active lane instead of only scrolling', () => {
    assert.match(source, /data-job-board-kanban-column-select/);
    assert.match(source, /is-kanban-column-active/);
    assert.match(source, /data-job-board-kanban-active-column/);
    assert.match(source, /max-width:\s*767\.98px/);
    assert.match(source, /setAttribute\('inert'/);
    assert.match(source, /mobileColumnMq\.matches/);
    assert.match(source, /scrollIntoView/);
});

test('mobile Cards CSS shows one full-width Kanban lane at the Column breakpoint', () => {
    assert.match(css, /\.ark-job-board-kanban-column\.is-kanban-column-active/);
    assert.match(css, /Re-assert mobile single-lane after container/);
    assert.match(
        css,
        /\.ark-job-board-kanban-column\s*\{[^}]*display:\s*none;[^}]*width:\s*100%;[^}]*max-width:\s*100%/s,
    );
    assert.match(
        css,
        /\.ark-job-board-kanban-column\.is-kanban-column-active\s*\{[^}]*display:\s*flex/s,
    );
    assert.match(
        css,
        /\.ark-job-board-kanban-scroller\s*\{[^}]*overflow-x:\s*hidden/s,
    );
});
