import assert from 'node:assert/strict';
import test from 'node:test';
import {
    IMPORT_STATUS,
    assignConcern,
    assignmentsFor,
    chooseMatrix,
    importIssues,
    launchedForRepairOrder,
    makeRow,
    matrixKeyForConcern,
    normalizeConcerns,
    normalizeMatrices,
    readLaunchContext,
    selectedSellCents,
    writeLaunchContext,
} from '../../resources/js/ark-partstech-cart-manager.js';

const context = {
    matrices: normalizeMatrices([
        { key: 'aft-parts', name: 'Standard', is_default: true },
        { key: 'warranty-no-markup', name: 'Warranty (No Markup)' },
        { key: 'oem', name: 'OEM Parts' },
    ]),
    concerns: normalizeConcerns([
        { id: 11, summary: 'Front Brake Noise', default_parts_matrix_key: 'aft-parts', work_groups: [{ id: 5, title: 'Replace pads', has_labor_anchor: true }] },
        { id: 12, summary: 'Warranty oil leak', default_parts_matrix_key: 'warranty-no-markup', work_groups: [] },
    ]),
    shopDefaultKey: 'aft-parts',
};

const line = (overrides = {}) => ({
    source_key: 'pt:item-pads',
    description: 'Front Brake Pad Set',
    quantity: '1.00',
    part_cost: '82.50',
    import_status: IMPORT_STATUS.NEW,
    imported_lines: [],
    ...overrides,
});

test('a part needs a deliberate concern unless PartsTech was opened from one', () => {
    const unassigned = makeRow(line(), context);
    const launched = makeRow(line(), context, { defaultConcernId: '12' });

    assert.equal(unassigned.concern_id, '');
    assert.equal(unassigned.pricing_matrix_key, 'aft-parts');
    assert.equal(launched.concern_id, '12');
    assert.equal(launched.pricing_matrix_key, 'warranty-no-markup');
});

test('the matrix follows the concern until the advisor picks one for that part', () => {
    const row = makeRow(line(), context, { defaultConcernId: '11' });

    assert.equal(row.pricing_matrix_key, 'aft-parts');
    assert.equal(row.work_group_id, '5');

    assignConcern(row, '12', context);
    assert.equal(row.pricing_matrix_key, 'warranty-no-markup');
    assert.equal(row.work_group_id, '');

    chooseMatrix(row, 'oem', context);
    assignConcern(row, '11', context);
    assert.equal(row.pricing_matrix_key, 'oem');
    assert.equal(row.matrix_explicit, true);
});

test('an unknown matrix is never chosen and a missing one falls back to the concern', () => {
    const row = makeRow(line(), context, { defaultConcernId: '11' });

    chooseMatrix(row, 'retired', context);
    assert.equal(row.pricing_matrix_key, 'aft-parts');
    assert.equal(row.matrix_explicit, false);

    const withRetiredConcernMatrix = { ...context, concerns: normalizeConcerns([{ id: 13, summary: 'Fleet', default_parts_matrix_key: 'retired' }]) };
    assert.equal(matrixKeyForConcern(withRetiredConcernMatrix, '13'), 'aft-parts');
});

test('pulling again keeps each part\'s concern, matrix override and edited cost', () => {
    const first = makeRow(line(), context, { defaultConcernId: '11' });
    chooseMatrix(first, 'oem', context);
    first.part_cost = '79.00';
    first.cost_edited = true;
    first.selected = false;

    const again = makeRow(line(), context, { previous: first, defaultConcernId: '12' });

    assert.equal(again.concern_id, '11');
    assert.equal(again.pricing_matrix_key, 'oem');
    assert.equal(again.part_cost, '79.00');
    assert.equal(again.selected, false);

    const costChangedInCart = makeRow(line({ part_cost: '85.00' }), context, { previous: first });
    assert.equal(costChangedInCart.part_cost, '85.00');
});

test('new cart parts start selected and parts already on the estimate start unselected', () => {
    const imported = makeRow(line({
        import_status: IMPORT_STATUS.IMPORTED,
        imported_lines: [{ line_id: 9, repair_order_concern_id: 12, concern_summary: 'Warranty oil leak' }],
    }), context, { defaultConcernId: '11' });
    const changed = makeRow(line({ source_key: 'pt:item-changed', import_status: IMPORT_STATUS.CHANGED, imported_lines: [{ line_id: 10 }] }), context);
    const fresh = makeRow(line({ source_key: 'pt:item-rotor' }), context, { defaultConcernId: '11' });

    assert.equal(fresh.selected, true);
    assert.equal(imported.selected, false);
    assert.equal(changed.selected, false);
    assert.equal(imported.concern_id, '12');
    assert.deepEqual(assignmentsFor([imported, changed, fresh]).map((row) => row.source_key), ['pt:item-rotor']);
});

test('another copy imports only after the advisor confirms it, naming the lines already on the estimate', () => {
    const imported = makeRow(line({
        import_status: IMPORT_STATUS.IMPORTED,
        imported_lines: [{ line_id: 9, repair_order_concern_id: 12 }, { line_id: 14, repair_order_concern_id: 11 }],
    }), context);
    const fresh = makeRow(line({ source_key: 'pt:item-rotor', description: 'Front Rotor' }), context, { defaultConcernId: '11' });

    imported.selected = true;

    assert.deepEqual(assignmentsFor([imported, fresh]).map((row) => row.source_key), ['pt:item-rotor']);
    assert.deepEqual(importIssues([imported, fresh]), ['Confirm the extra copy of Front Brake Pad Set, or untick it.']);
    assert.equal('acknowledged_duplicate_line_ids' in assignmentsFor([fresh])[0], false);

    imported.duplicate_acknowledged = true;
    const assignments = assignmentsFor([imported, fresh]);

    assert.deepEqual(assignments.find((row) => row.source_key === 'pt:item-pads').acknowledged_duplicate_line_ids, [9, 14]);
    assert.equal(assignments[0].pricing_matrix_key, 'warranty-no-markup');
    assert.deepEqual(importIssues([imported, fresh]), []);
});

test('import issues name the parts that need a concern or a price', () => {
    const rows = ['Wiper Blade', 'Cabin Filter', 'Air Filter'].map((description, index) => makeRow(line({ source_key: 'pt:' + index, description }), context));
    rows[2].concern_id = '11';
    rows[2].pricing_error = 'Enter a valid cost.';

    assert.deepEqual(importIssues(rows), [
        'Choose a concern for Wiper Blade and Cabin Filter.',
        'Fix the price for Air Filter.',
    ]);

    const many = ['A', 'B', 'C', 'D'].map((description) => makeRow(line({ source_key: 'pt:' + description, description }), context));
    assert.deepEqual(importIssues(many), ['Choose a concern for A, B and 2 more.']);
});

test('a PartsTech launch only applies to the repair order that launched it', () => {
    assert.equal(launchedForRepairOrder({ repairOrderId: 7, concernId: 12 }, '7'), true);
    assert.equal(launchedForRepairOrder({ repairOrderId: 8, concernId: 12 }, '7'), false);
    assert.equal(launchedForRepairOrder({ concernId: 12 }, '7'), false);
    assert.equal(launchedForRepairOrder({ repairOrderId: '' }, ''), false);
});

test('selected sell totals unit sell times quantity for priced rows across concerns', () => {
    const rows = [
        { selected: true, concern_id: '11', quantity: '2.00', pricing: { unit_sell_cents: 11895 }, pricing_error: '' },
        { selected: true, concern_id: '12', quantity: '5.00', pricing: { unit_sell_cents: 1216 }, pricing_error: '' },
        { selected: true, concern_id: '12', quantity: '1.00', pricing: null, pricing_error: 'Enter a cost.' },
        { selected: false, concern_id: '11', quantity: '1.00', pricing: { unit_sell_cents: 5000 }, pricing_error: '' },
    ];

    assert.equal(selectedSellCents(rows), 2 * 11895 + 5 * 1216);
});

test('the launch concern is remembered per repair order for four hours', () => {
    const storage = new Map();
    const store = { getItem: (key) => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value) };

    writeLaunchContext(store, 7, 12, { now: 1_000 });

    assert.deepEqual(readLaunchContext(store, 7, 2_000), { concernId: '12', at: 1_000, pulled: false });
    assert.equal(readLaunchContext(store, 8, 2_000), null, 'another repair order never reads this launch');
    assert.equal(readLaunchContext(store, 7, 1_000 + 4 * 60 * 60 * 1000 + 1), null);
});
