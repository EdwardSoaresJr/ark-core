import assert from 'node:assert/strict';
import test from 'node:test';
import {
    arrivalOpensTab,
    pickAdjacentNeighborKey,
    splitVisibleTabs,
    tabsForActivityRefresh,
} from '../../resources/js/ark-workspace-tab-lifecycle.js';

const tabs = (...ids) => ids.map((id) => ({ key: `repair_order:${id}` }));
const keys = (list) => list.map((tab) => tab.key);

test('following a link, a redirect, or a typed address opens the tab', () => {
    assert.equal(arrivalOpensTab('navigate'), true);
    assert.equal(arrivalOpensTab(null), true);
});

test('reloading or moving through history never reopens a closed tab', () => {
    assert.equal(arrivalOpensTab('reload'), false);
    assert.equal(arrivalOpensTab('back_forward'), false);
});

test('every tab is shown while they fit', () => {
    const split = splitVisibleTabs({
        tabs: tabs(1, 2, 3),
        activeKey: 'repair_order:3',
        availableWidth: 540,
        slotWidth: 180,
        overflowWidth: 64,
    });

    assert.deepEqual(keys(split.visible), ['repair_order:1', 'repair_order:2', 'repair_order:3']);
    assert.deepEqual(split.overflow, []);
});

test('tabs that do not fit go to the overflow list in order, leaving room for its control', () => {
    const split = splitVisibleTabs({
        tabs: tabs(1, 2, 3, 4, 5),
        activeKey: 'repair_order:1',
        availableWidth: 600,
        slotWidth: 180,
        overflowWidth: 64,
    });

    assert.deepEqual(keys(split.visible), ['repair_order:1', 'repair_order:2']);
    assert.deepEqual(keys(split.overflow), ['repair_order:3', 'repair_order:4', 'repair_order:5']);
});

test('the active tab is always shown, taking the last visible place', () => {
    const split = splitVisibleTabs({
        tabs: tabs(1, 2, 3, 4, 5),
        activeKey: 'repair_order:4',
        availableWidth: 600,
        slotWidth: 180,
        overflowWidth: 64,
    });

    assert.deepEqual(keys(split.visible), ['repair_order:1', 'repair_order:4']);
    assert.deepEqual(keys(split.overflow), ['repair_order:2', 'repair_order:3', 'repair_order:5']);
});

test('no tab is dropped, and at least one stays visible on a narrow strip', () => {
    const split = splitVisibleTabs({
        tabs: tabs(1, 2, 3),
        activeKey: 'repair_order:3',
        availableWidth: 100,
        slotWidth: 180,
        overflowWidth: 64,
    });

    assert.deepEqual(keys(split.visible), ['repair_order:3']);
    assert.deepEqual(keys(split.overflow), ['repair_order:1', 'repair_order:2']);
});

test('activity refresh sends the active tab and the most recently focused, up to the limit', () => {
    const picked = tabsForActivityRefresh({
        tabs: [
            { key: 'repair_order:1', focusedAt: 10 },
            { key: 'repair_order:2', focusedAt: 40 },
            { key: 'repair_order:3', focusedAt: 5 },
            { key: 'repair_order:4', focusedAt: 30 },
        ],
        activeKey: 'repair_order:3',
        limit: 3,
    });

    assert.deepEqual(keys(picked), ['repair_order:3', 'repair_order:2', 'repair_order:4']);
});

test('adjacent neighbor prefers more recently focused side', () => {
    assert.equal(
        pickAdjacentNeighborKey({
            orderedKeys: ['a', 'b', 'c'],
            closedKey: 'b',
            focusedAtByKey: { a: 5, c: 9 },
        }),
        'c',
    );
});

test('adjacent neighbor falls back to sole neighbor', () => {
    assert.equal(
        pickAdjacentNeighborKey({
            orderedKeys: ['a', 'b'],
            closedKey: 'b',
            focusedAtByKey: { a: 1 },
        }),
        'a',
    );
});
