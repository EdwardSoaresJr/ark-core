import assert from 'node:assert/strict';
import test from 'node:test';
import {
    keysToEvictForNewTab,
    pickAdjacentNeighborKey,
    pickOldestInactiveEvictionCandidate,
} from '../../resources/js/ark-workspace-tab-eviction.js';

const notDocked = () => false;
const neverDirty = () => false;

test('picks oldest inactive by openedAt', () => {
    const victim = pickOldestInactiveEvictionCandidate({
        tabs: [
            { key: 'repair_order:1', openedAt: 100 },
            { key: 'repair_order:2', openedAt: 50 },
            { key: 'repair_order:3', openedAt: 200 },
        ],
        activeKey: 'repair_order:3',
        isDocked: notDocked,
        isDirty: neverDirty,
    });

    assert.equal(victim?.key, 'repair_order:2');
});

test('never evicts active when inactive candidates exist', () => {
    const victim = pickOldestInactiveEvictionCandidate({
        tabs: [
            { key: 'repair_order:1', openedAt: 10 },
            { key: 'repair_order:2', openedAt: 20 },
        ],
        activeKey: 'repair_order:1',
        isDocked: notDocked,
        isDirty: neverDirty,
    });

    assert.equal(victim?.key, 'repair_order:2');
});

test('skips dirty candidates', () => {
    const victim = pickOldestInactiveEvictionCandidate({
        tabs: [
            { key: 'repair_order:1', openedAt: 10 },
            { key: 'repair_order:2', openedAt: 20 },
        ],
        activeKey: 'repair_order:3',
        isDocked: notDocked,
        isDirty: (key) => key === 'repair_order:1',
    });

    assert.equal(victim?.key, 'repair_order:2');
});

test('excludes key being opened', () => {
    const victim = pickOldestInactiveEvictionCandidate({
        tabs: [
            { key: 'repair_order:1', openedAt: 10 },
            { key: 'repair_order:9', openedAt: 1 },
        ],
        activeKey: 'repair_order:1',
        excludeKeys: ['repair_order:9'],
        isDocked: notDocked,
        isDirty: neverDirty,
    });

    assert.equal(victim, null);
});

test('keysToEvict frees one slot at limit without resetting openedAt semantics', () => {
    const tabs = [
        { key: 'repair_order:1', openedAt: 10 },
        { key: 'repair_order:2', openedAt: 20 },
        { key: 'repair_order:3', openedAt: 30 },
    ];

    const remove = keysToEvictForNewTab({
        tabs,
        activeKey: 'repair_order:3',
        excludeKeys: ['repair_order:4'],
        maxOpen: 3,
        isDocked: notDocked,
        isDirty: neverDirty,
    });

    assert.deepEqual(remove, ['repair_order:1']);
    assert.equal(tabs[0].openedAt, 10);
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
