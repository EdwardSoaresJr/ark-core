import assert from 'node:assert/strict';
import test from 'node:test';
import { tablerBadgeClasses } from './ark-nav-count-badge.js';

test('a live call outranks since-last-shift pressure', () => {
    assert.deepEqual(tablerBadgeClasses({ has_live_calls: true, since_last_shift_count: 3 }), ['bg-red', 'text-red-fg']);
});

test('since-last-shift pressure is orange', () => {
    assert.deepEqual(tablerBadgeClasses({ since_last_shift_count: '2' }), ['bg-orange', 'text-orange-fg']);
});

test('ordinary pressure is a light red badge', () => {
    assert.deepEqual(tablerBadgeClasses({}), ['bg-red-lt']);
    assert.deepEqual(tablerBadgeClasses(), ['bg-red-lt']);
});
