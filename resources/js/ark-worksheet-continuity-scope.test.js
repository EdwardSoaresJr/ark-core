import assert from 'node:assert/strict';
import test from 'node:test';
import { applyContinuityScope, applyContinuityScopes, applyEditorPresentation, CONTINUITY_REFRESH_FAILED_MESSAGE, replaceSubmittedRegion } from './ark-worksheet-continuity-scope.js';

class El {
    constructor(tag, attrs = {}) {
        this.nodeType = tag === '#text' ? 3 : 1;
        this.tagName = tag === '#text' ? '#text' : String(tag).toUpperCase();
        this.attrs = { ...attrs };
        this.childNodes = [];
        this.parentElement = null;
        this.value = attrs.value ?? '';
        this.defaultValue = this.value;
        this.checked = Boolean(attrs.checked);
        this.defaultChecked = this.checked;
        this.type = attrs.type ?? (this.tagName === 'TEXTAREA' ? 'textarea' : 'text');
        this.disabled = false;
        this.readOnly = false;
        this.text = attrs.text ?? '';
    }

    get id() {
        return this.attrs.id ?? '';
    }

    getAttribute(name) {
        return Object.prototype.hasOwnProperty.call(this.attrs, name) ? this.attrs[name] : null;
    }

    append(child) {
        child.parentElement = this;
        this.childNodes.push(child);

        return this;
    }

    get textContent() {
        if (this.nodeType === 3) {
            return this.text;
        }

        return this.childNodes.map((child) => child.textContent).join('');
    }

    querySelectorAll(selector) {
        const selectors = String(selector).split(',').map((item) => item.trim());
        const matches = [];

        const visit = (node) => {
            for (const child of node.childNodes) {
                if (child.nodeType !== 1) {
                    continue;
                }

                if (selectors.some((item) => selectorMatches(child, item))) {
                    matches.push(child);
                }

                visit(child);
            }
        };

        visit(this);

        return matches;
    }

    getElementById(id) {
        if (this.id === id) {
            return this;
        }

        for (const child of this.childNodes) {
            if (child.nodeType !== 1) {
                continue;
            }

            const found = child.getElementById(id);

            if (found) {
                return found;
            }
        }

        return null;
    }

    cloneNode(deep) {
        const copy = new El(this.nodeType === 3 ? '#text' : this.tagName.toLowerCase(), {
            ...this.attrs,
            text: this.text,
            value: this.value,
        });
        copy.value = this.value;
        copy.defaultValue = this.defaultValue;
        copy.type = this.type;
        copy.checked = this.checked;
        copy.defaultChecked = this.defaultChecked;

        if (deep) {
            for (const child of this.childNodes) {
                copy.append(child.cloneNode(true));
            }
        }

        return copy;
    }

    get isConnected() {
        return this.parentElement !== null;
    }

    get nextSibling() {
        if (! this.parentElement) {
            return null;
        }

        const index = this.parentElement.childNodes.indexOf(this);

        return index >= 0 ? (this.parentElement.childNodes[index + 1] ?? null) : null;
    }

    remove() {
        const parent = this.parentElement;

        if (! parent) {
            return;
        }

        const index = parent.childNodes.indexOf(this);

        if (index >= 0) {
            parent.childNodes.splice(index, 1);
        }

        this.parentElement = null;
    }

    insertBefore(node, reference) {
        node.parentElement = this;

        if (! reference) {
            this.childNodes.push(node);

            return node;
        }

        const index = this.childNodes.indexOf(reference);
        this.childNodes.splice(index, 0, node);

        return node;
    }

    replaceWith(next) {
        const parent = this.parentElement;
        const index = parent.childNodes.indexOf(this);
        next.parentElement = parent;
        parent.childNodes.splice(index, 1, next);
        this.parentElement = null;
    }
}

function selectorMatches(node, selector) {
    if (selector.startsWith('#')) {
        return node.id === selector.slice(1);
    }

    if (selector === '[data-continuity-region]') {
        return node.getAttribute('data-continuity-region') !== null;
    }

    if (selector.startsWith('[data-continuity-region="') && selector.endsWith('"]')) {
        const value = selector.slice('[data-continuity-region="'.length, -2);

        return node.getAttribute('data-continuity-region') === value;
    }

    if (selector === 'input') {
        return node.tagName === 'INPUT';
    }

    if (selector === 'textarea') {
        return node.tagName === 'TEXTAREA';
    }

    if (selector === 'select') {
        return node.tagName === 'SELECT';
    }

    return false;
}

function text(value) {
    return new El('#text', { text: value });
}

function h(tag, attrs = {}, ...children) {
    const node = new El(tag, attrs);

    for (const child of children) {
        node.append(typeof child === 'string' ? text(child) : child);
    }

    return node;
}

function worksheet({ lineTwo, labor, total, composerValue = '' }) {
    const composerField = h('textarea', { id: 'composer-field' });
    composerField.defaultValue = '';
    composerField.value = composerValue;

    const lines = h('div', { id: 'concern-lines-1' });
    lines.append(h('article', { id: 'line-1', 'data-continuity-region': 'line' }, 'Qty 1.00 $165.00'));

    if (lineTwo !== null) {
        lines.append(h('article', { id: 'line-2', 'data-continuity-region': 'line' }, lineTwo));
    }

    const root = h('div', { id: 'root', 'data-worksheet-root': '', 'data-continuity-scope': 'lines', 'data-ark-line-id': '2' });
    root.append(h('form', { id: 'composer-a', 'data-continuity-region': 'composer' }, composerField));
    root.append(lines);
    root.append(h('p', { id: 'concern-money-1', 'data-continuity-region': 'concern-money' }, labor));
    root.append(h(
        'div',
        { id: 'estimate-totals-region', 'data-continuity-region': 'totals' },
        `Labor ${labor} Total ${total}`,
    ));
    root.append(h(
        'div',
        { id: 'financial-position', 'data-continuity-region': 'financial-position' },
        `Owe today ${total} Deposits $20.00`,
    ));
    root.append(h(
        'button',
        { id: 'authorization-rail', 'data-continuity-region': 'authorization-rail' },
        `Authorization ${total}`,
    ));

    return root;
}

test('lines scope keeps a dirty composer and shows saved labor truth', () => {
    const current = worksheet({
        lineTwo: 'Qty 1.00 $165.00',
        labor: '$330.00',
        total: '$330.00',
        composerValue: 'Bleed brakes',
    });
    const fresh = worksheet({
        lineTwo: 'Qty 2.00 $330.00',
        labor: '$495.00',
        total: '$495.00',
        composerValue: '',
    });

    const result = applyContinuityScope(current, fresh, 'lines', {
        requiredIds: ['line-2', 'estimate-totals-region', 'financial-position'],
    });

    assert.equal(result.ok, true);
    assert.deepEqual(result.preserved, []);
    assert.deepEqual(result.failed, []);
    assert.ok(result.applied.includes('line-2'));
    assert.ok(result.applied.includes('estimate-totals-region'));
    assert.ok(result.applied.includes('financial-position'));
    assert.equal(current.getElementById('composer-field').value, 'Bleed brakes');
    assert.match(current.getElementById('line-1').textContent, /Qty 1\.00/);
    assert.match(current.getElementById('line-2').textContent, /Qty 2\.00/);
    assert.match(current.getElementById('line-2').textContent, /\$330\.00/);
    assert.match(current.getElementById('concern-money-1').textContent, /\$495\.00/);
    assert.match(current.getElementById('estimate-totals-region').textContent, /Labor \$495\.00/);
    assert.match(current.getElementById('estimate-totals-region').textContent, /Total \$495\.00/);
    assert.match(current.getElementById('financial-position').textContent, /Owe today \$495\.00/);
    assert.match(current.getElementById('financial-position').textContent, /Deposits \$20\.00/);
    assert.equal(current.getElementById('authorization-rail').textContent, 'Authorization $495.00');
    assert.equal(current.getElementById('line-2').textContent, fresh.getElementById('line-2').textContent);
    assert.equal(
        current.getElementById('estimate-totals-region').textContent,
        fresh.getElementById('estimate-totals-region').textContent,
    );
});

test('a dirty sibling line is preserved while the saved line and totals update', () => {
    const current = worksheet({
        lineTwo: 'Qty 1.00 $165.00',
        labor: '$330.00',
        total: '$330.00',
    });
    const siblingNote = h('textarea', { id: 'line-1-note' });
    siblingNote.defaultValue = '';
    siblingNote.value = 'waiting on pads';
    current.getElementById('line-1').append(siblingNote);

    const fresh = worksheet({
        lineTwo: 'Qty 2.00 $330.00',
        labor: '$495.00',
        total: '$495.00',
    });

    const result = applyContinuityScope(current, fresh, 'lines', {
        requiredIds: ['line-2', 'estimate-totals-region'],
    });

    assert.equal(result.ok, true);
    assert.deepEqual(result.preserved, ['line-1']);
    assert.match(current.getElementById('line-1').textContent, /Qty 1\.00/);
    assert.equal(current.getElementById('line-1-note').value, 'waiting on pads');
    assert.match(current.getElementById('line-2').textContent, /Qty 2\.00/);
    assert.match(current.getElementById('estimate-totals-region').textContent, /Total \$495\.00/);
});

test('a nested continuity region is not applied and the failure is visible', () => {
    const current = worksheet({
        lineTwo: 'Qty 1.00 $165.00',
        labor: '$330.00',
        total: '$330.00',
    });
    current.getElementById('estimate-totals-region').append(
        h('div', { id: 'nested-payment', 'data-continuity-region': 'settlement' }, 'Pay'),
    );
    const fresh = worksheet({
        lineTwo: 'Qty 2.00 $330.00',
        labor: '$495.00',
        total: '$495.00',
    });

    const result = applyContinuityScope(current, fresh, 'lines', {
        requiredIds: ['line-2', 'estimate-totals-region'],
    });

    assert.equal(result.ok, false);
    assert.ok(result.failed.includes('estimate-totals-region'));
    assert.match(current.getElementById('estimate-totals-region').textContent, /Total \$330\.00/);
    assert.equal(
        CONTINUITY_REFRESH_FAILED_MESSAGE,
        'Saved. Some information could not be refreshed. Reload to see the current repair order.',
    );
});

test('an unchanged select on a line does not keep that line from showing saved truth', () => {
    const current = worksheet({
        lineTwo: 'Qty 1.00 $48.00',
        labor: '$495.00',
        total: '$543.00',
    });
    const menu = h('select', { id: 'part-procurement-2' });
    const option = h('option', { value: 'none', selected: '' });
    option.selected = true;
    option.defaultSelected = true;
    menu.options = [option];
    menu.append(option);
    menu.value = 'none';
    current.getElementById('line-2').append(menu);

    const fresh = worksheet({
        lineTwo: 'Qty 1.00 $60.00',
        labor: '$495.00',
        total: '$555.00',
    });

    const result = applyContinuityScope(current, fresh, 'lines', {
        requiredIds: ['line-2', 'estimate-totals-region'],
    });

    assert.equal(result.ok, true);
    assert.match(current.getElementById('line-2').textContent, /\$60\.00/);
    assert.match(current.getElementById('estimate-totals-region').textContent, /Total \$555\.00/);
});

test('adding a line updates the new card and totals while a dirty composer stays', () => {
    const current = worksheet({
        lineTwo: 'Qty 1.00 $165.00',
        labor: '$330.00',
        total: '$330.00',
        composerValue: 'Bleed brakes',
    });
    const fresh = worksheet({
        lineTwo: 'Qty 1.00 $165.00',
        labor: '$495.00',
        total: '$495.00',
        composerValue: '',
    });
    fresh.getElementById('concern-lines-1').append(
        h('article', { id: 'line-3', 'data-continuity-region': 'line' }, 'Fee Shop supplies $12.00'),
    );

    const result = applyContinuityScope(current, fresh, 'lines', {
        requiredIds: ['line-3', 'estimate-totals-region'],
    });

    assert.equal(result.ok, true);
    assert.ok(result.applied.includes('line-3'));
    assert.equal(current.getElementById('composer-field').value, 'Bleed brakes');
    assert.equal(current.getElementById('line-3').textContent, 'Fee Shop supplies $12.00');
    assert.equal(
        current.getElementById('concern-lines-1').childNodes.map((node) => node.id).join(','),
        'line-1,line-2,line-3',
    );
    assert.match(current.getElementById('estimate-totals-region').textContent, /Total \$495\.00/);
});

test('removing a line drops that card and updates totals while a dirty composer stays', () => {
    const current = worksheet({
        lineTwo: 'Qty 1.00 $165.00',
        labor: '$330.00',
        total: '$330.00',
        composerValue: 'Bleed brakes',
    });
    const fresh = worksheet({
        lineTwo: null,
        labor: '$165.00',
        total: '$165.00',
        composerValue: '',
    });

    const result = applyContinuityScope(current, fresh, 'lines', {
        requiredIds: ['estimate-totals-region'],
    });

    assert.equal(result.ok, true);
    assert.equal(current.getElementById('line-2'), null);
    assert.equal(current.getElementById('composer-field').value, 'Bleed brakes');
    assert.match(current.getElementById('line-1').textContent, /Qty 1\.00/);
    assert.match(current.getElementById('concern-money-1').textContent, /\$165\.00/);
    assert.match(current.getElementById('estimate-totals-region').textContent, /Total \$165\.00/);
});

test('a dirty line that the server removed is kept and the refresh fails visibly', () => {
    const current = worksheet({
        lineTwo: 'Qty 1.00 $165.00',
        labor: '$330.00',
        total: '$330.00',
    });
    const draft = h('textarea', { id: 'line-2-note' });
    draft.defaultValue = '';
    draft.value = 'do not lose this';
    current.getElementById('line-2').append(draft);

    const fresh = worksheet({
        lineTwo: null,
        labor: '$165.00',
        total: '$165.00',
    });

    const result = applyContinuityScope(current, fresh, 'lines', {
        requiredIds: ['estimate-totals-region'],
    });

    assert.equal(result.ok, false);
    assert.ok(result.failed.includes('line-2'));
    assert.equal(current.getElementById('line-2-note').value, 'do not lose this');
    assert.match(current.getElementById('estimate-totals-region').textContent, /Total \$165\.00/);
});

test('opening an editor replaces the editor mount and leaves a dirty composer and line cards alone', () => {
    const current = h('div', { id: 'root' });
    const composerField = h('textarea', { id: 'composer-field' });
    composerField.defaultValue = '';
    composerField.value = 'Bleed brakes';
    current.append(h('form', { id: 'composer-a', 'data-continuity-region': 'composer' }, composerField));
    current.append(h('article', { id: 'line-1', 'data-continuity-region': 'line' }, 'Qty 1.00 $165.00'));
    current.append(h('div', { id: 'worksheet-line-editor' }));
    current.append(h('p', { id: 'estimate-totals-region', 'data-continuity-region': 'totals' }, 'Total $165.00'));

    const fresh = h('div', { id: 'root' });
    const editor = h('div', {
        id: 'worksheet-line-editor',
        'data-editor-line-id': '9',
        'data-editor-line-label': 'Labor',
        'data-editor-line-type': 'labor',
    }, h('input', { id: 'book-hours', value: '2.00' }));
    fresh.append(h('article', { id: 'line-1', 'data-continuity-region': 'line' }, 'Qty 9.00 $999.00'));
    fresh.append(editor);
    fresh.append(h('p', { id: 'estimate-totals-region', 'data-continuity-region': 'totals' }, 'Total $999.00'));

    assert.equal(applyEditorPresentation(current, fresh), true);
    assert.equal(current.getElementById('composer-field').value, 'Bleed brakes');
    assert.match(current.getElementById('line-1').textContent, /Qty 1\.00/);
    assert.match(current.getElementById('estimate-totals-region').textContent, /\$165\.00/);
    assert.equal(current.getElementById('worksheet-line-editor').getAttribute('data-editor-line-id'), '9');
    assert.equal(current.getElementById('book-hours').value, '2.00');
});

function authorizationWorksheet({ approvedLabel, otherLabel, authorized, owe, status, lineText, rail, parts }) {
    const composerField = h('textarea', { id: 'composer-field' });
    composerField.defaultValue = '';
    composerField.value = 'Bleed brakes';

    const root = h('div', { id: 'root', 'data-worksheet-root': '', 'data-continuity-scope': 'authorization workflow' });
    root.append(h('form', { id: 'composer-a', 'data-continuity-region': 'composer' }, composerField));
    root.append(h('div', { id: 'concern-authorization-1', 'data-continuity-region': 'concern-authorization' }, approvedLabel));
    root.append(h('div', { id: 'concern-authorization-2', 'data-continuity-region': 'concern-authorization' }, otherLabel));
    root.append(h('article', { id: 'line-1', 'data-continuity-region': 'line' }, lineText));
    root.append(h('div', { id: 'worksheet-line-editor' }, 'Book 2.00'));
    root.append(h('div', { id: 'authorized-money', 'data-continuity-region': 'authorized-money' }, authorized));
    root.append(h('div', { id: 'financial-position', 'data-continuity-region': 'financial-position' }, `Owe today ${owe} Deposits $20.00`));
    root.append(h('form', { id: 'repair-order-workflow', 'data-continuity-region': 'workflow' }, status));
    root.append(h('button', { id: 'authorization-rail', 'data-continuity-region': 'authorization-rail' }, rail));
    root.append(h('button', { id: 'parts-procurement' }, parts));

    return root;
}

test('authorization and workflow update their regions once and keep a dirty composer and the editor', () => {
    const current = authorizationWorksheet({
        approvedLabel: 'Pending',
        otherLabel: 'Pending',
        authorized: 'Approved $0.00 Needs approval $341.55',
        owe: '$0.00',
        status: 'Building Estimate',
        lineText: 'Needs authorization Phase 4B',
        rail: 'Authorization',
        parts: 'Parts 1 · 1 blocking',
    });
    const fresh = authorizationWorksheet({
        approvedLabel: 'Approved',
        otherLabel: 'Pending',
        authorized: 'Approved $341.55 Needs approval $170.78',
        owe: '$321.55',
        status: 'Approved',
        lineText: 'Phase 4B',
        rail: 'Authorization $341.55',
        parts: 'Parts 1 · Ready',
    });
    const result = applyContinuityScopes(current, fresh, ['authorization', 'workflow'], {
        requiredIds: [
            'concern-authorization-1',
            'concern-authorization-2',
            'authorized-money',
            'financial-position',
            'repair-order-workflow',
        ],
    });

    assert.equal(result.ok, true);
    assert.deepEqual(result.preserved, []);
    assert.deepEqual(result.failed, []);
    assert.equal(result.applied.filter((id) => id === 'financial-position').length, 1);
    assert.equal(current.getElementById('composer-field').value, 'Bleed brakes');
    assert.equal(current.getElementById('concern-authorization-1').textContent, 'Approved');
    assert.equal(current.getElementById('concern-authorization-2').textContent, 'Pending');
    assert.equal(current.getElementById('authorized-money').textContent, 'Approved $341.55 Needs approval $170.78');
    assert.match(current.getElementById('financial-position').textContent, /Owe today \$321\.55/);
    assert.match(current.getElementById('financial-position').textContent, /Deposits \$20\.00/);
    assert.equal(current.getElementById('repair-order-workflow').textContent, 'Approved');
    assert.equal(current.getElementById('authorization-rail').textContent, 'Authorization $341.55');
    assert.equal(current.getElementById('parts-procurement').textContent, 'Parts 1 · 1 blocking');
    assert.equal(current.getElementById('line-1').textContent, 'Phase 4B');
    assert.equal(current.getElementById('worksheet-line-editor').textContent, 'Book 2.00');
});

test('deferring work leaves it out of authorized dollars and owe today', () => {
    const current = authorizationWorksheet({
        approvedLabel: 'Approved',
        otherLabel: 'Pending',
        authorized: 'Approved $341.55 Needs approval $170.78',
        owe: '$321.55',
        status: 'Approved',
        lineText: 'Phase 4B',
        rail: 'Authorization $341.55',
        parts: 'Parts 1 · 1 blocking',
    });
    const fresh = authorizationWorksheet({
        approvedLabel: 'Deferred',
        otherLabel: 'Pending',
        authorized: 'Approved $0.00 Needs approval $170.78',
        owe: '$0.00',
        status: 'Waiting Approval',
        lineText: 'Phase 4B',
        rail: 'Authorization $0.00',
        parts: 'Parts 1 · Ready',
    });

    const result = applyContinuityScopes(current, fresh, ['authorization', 'workflow'], {
        requiredIds: ['concern-authorization-1', 'authorized-money', 'financial-position', 'repair-order-workflow'],
    });

    assert.equal(result.ok, true);
    assert.equal(current.getElementById('composer-field').value, 'Bleed brakes');
    assert.equal(current.getElementById('concern-authorization-1').textContent, 'Deferred');
    assert.equal(current.getElementById('concern-authorization-2').textContent, 'Pending');
    assert.match(current.getElementById('authorized-money').textContent, /Approved \$0\.00/);
    assert.match(current.getElementById('financial-position').textContent, /Owe today \$0\.00/);
    assert.match(current.getElementById('financial-position').textContent, /Deposits \$20\.00/);
    assert.equal(current.getElementById('repair-order-workflow').textContent, 'Waiting Approval');
    assert.equal(current.getElementById('authorization-rail').textContent, 'Authorization $0.00');
    assert.equal(current.getElementById('parts-procurement').textContent, 'Parts 1 · 1 blocking');
});

function settlementWorksheet({ owe, deposits, history, posture, balance, lineText, capture, rail = 'Authorization $435.38' }) {
    const composerField = h('textarea', { id: 'composer-field' });
    composerField.defaultValue = '';
    composerField.value = 'Bleed brakes';

    const root = h('div', { id: 'root', 'data-worksheet-root': '', 'data-continuity-scope': 'settlement' });
    root.append(h('form', { id: 'composer-a', 'data-continuity-region': 'composer' }, composerField));
    root.append(h('article', { id: 'line-1', 'data-continuity-region': 'line' }, lineText));
    root.append(h('div', { id: 'worksheet-line-editor' }, 'Book 2.00'));
    root.append(h('div', { id: 'financial-position', 'data-continuity-region': 'financial-position' }, `Owe today ${owe} Deposits ${deposits}`));
    root.append(h('div', { id: 'settlement-balance', 'data-continuity-region': 'settlement' }, balance));
    root.append(h('div', { id: 'settlement-history', 'data-continuity-region': 'settlement' }, history));
    root.append(h('div', { id: 'payment-posture', 'data-continuity-region': 'settlement' }, posture));
    root.append(h('div', { id: 'closeout-eligibility', 'data-continuity-region': 'settlement' }, ''));
    root.append(h('div', { id: 'take-payment' }, capture));
    root.append(h('button', { id: 'authorization-rail', 'data-continuity-region': 'authorization-rail' }, rail));

    return root;
}

test('settlement updates ledger regions and leaves the worksheet, line, and card capture alone', () => {
    const current = settlementWorksheet({
        owe: '$150.78',
        deposits: '$20.00',
        history: 'Deposit · Cash $10.00',
        posture: 'Deposit on file',
        balance: 'Deposits on file $20.00',
        lineText: 'Phase 4B Qty 3.00',
        capture: 'Platform is not connected.',
    });
    const fresh = settlementWorksheet({
        owe: '$140.78',
        deposits: '$30.00',
        history: 'Deposit · Cash $10.00 Continuity cash',
        posture: 'Deposit on file',
        balance: 'Deposits on file $30.00',
        lineText: 'SHOULD NOT REPLACE',
        capture: 'Card capture ready',
        rail: 'SHOULD NOT REPLACE',
    });

    const result = applyContinuityScopes(current, fresh, ['settlement'], {
        requiredIds: ['financial-position', 'settlement-balance', 'settlement-history', 'payment-posture', 'closeout-eligibility'],
    });

    assert.equal(result.ok, true);
    assert.deepEqual(result.preserved, []);
    assert.deepEqual(result.failed, []);
    assert.equal(result.applied.filter((id) => id === 'financial-position').length, 1);
    assert.equal(result.applied.includes('line-1'), false);
    assert.equal(current.getElementById('composer-field').value, 'Bleed brakes');
    assert.equal(current.getElementById('line-1').textContent, 'Phase 4B Qty 3.00');
    assert.equal(current.getElementById('worksheet-line-editor').textContent, 'Book 2.00');
    assert.equal(current.getElementById('take-payment').textContent, 'Platform is not connected.');
    assert.match(current.getElementById('financial-position').textContent, /Owe today \$140\.78/);
    assert.match(current.getElementById('financial-position').textContent, /Deposits \$30\.00/);
    assert.match(current.getElementById('settlement-history').textContent, /Continuity cash/);
    assert.match(current.getElementById('settlement-balance').textContent, /Deposits on file \$30\.00/);
    assert.equal(current.getElementById('payment-posture').textContent, 'Deposit on file');
    assert.equal(current.getElementById('authorization-rail').textContent, 'Authorization $435.38');
});

test('a new concern mounts from the lines response and leaves an unrelated composer', () => {
    const composerField = h('textarea', { id: 'composer-field' });
    composerField.defaultValue = '';
    composerField.value = 'DIRTY DURING EDIT';

    const current = h('div', { id: 'root' });
    current.append(h('form', { id: 'composer-a', 'data-continuity-region': 'composer' }, composerField));
    current.append(h('p', { id: 'worksheet-count', 'data-continuity-region': 'worksheet-count' }, '0 scopes · 0 lines'));
    current.append(h('div', { id: 'worksheet-concerns' }));
    current.append(h('div', { id: 'workspace-modal-body' }));
    current.append(h('div', { id: 'estimate-totals-region', 'data-continuity-region': 'totals' }, 'Total $0.00'));

    const fresh = h('div', { id: 'root' });
    fresh.append(h('form', { id: 'composer-a', 'data-continuity-region': 'composer' }));
    fresh.append(h('p', { id: 'worksheet-count', 'data-continuity-region': 'worksheet-count' }, '1 scopes · 0 lines'));
    const concerns = h('div', { id: 'worksheet-concerns' });
    const section = h('section', { id: 'concern-9' });
    section.append(h('p', { id: 'concern-money-9', 'data-continuity-region': 'concern-money' }, '$0.00'));
    section.append(h('div', { id: 'concern-disposition-9', 'data-continuity-region': 'concern-authorization' }, 'Draft'));
    concerns.append(section);
    fresh.append(concerns);
    const modal = h('div', { id: 'workspace-modal-body' });
    modal.append(h('div', {
        id: 'concern-authoring-line-concern-9',
        'data-concern-authoring': '9',
    }, 'Add labor'));
    fresh.append(modal);
    fresh.append(h('div', { id: 'estimate-totals-region', 'data-continuity-region': 'totals' }, 'Total $0.00'));

    const result = applyContinuityScope(current, fresh, 'lines', {
        requiredIds: ['worksheet-count', 'estimate-totals-region', 'concern-money-9'],
    });

    assert.equal(result.ok, true);
    assert.equal(current.getElementById('composer-field').value, 'DIRTY DURING EDIT');
    assert.equal(current.getElementById('worksheet-count').textContent, '1 scopes · 0 lines');
    assert.equal(current.getElementById('concern-9').textContent.includes('Draft'), true);
    assert.equal(current.getElementById('concern-money-9').textContent, '$0.00');
    assert.equal(current.getElementById('concern-authoring-line-concern-9').textContent, 'Add labor');
});

test('replacing the submitted composer leaves the other composer', () => {
    const dirty = h('textarea', { id: 'composer-field' });
    dirty.defaultValue = '';
    dirty.value = 'DIRTY DURING EDIT';
    const submittedField = h('textarea', { id: 'part-description' });
    submittedField.defaultValue = '';
    submittedField.value = 'Front brake pads';

    const current = h('div', { id: 'root' });
    current.append(h('form', { id: 'composer-a', 'data-continuity-region': 'composer' }, dirty));
    const submitted = h('form', { id: 'workspace-line-create-9', 'data-continuity-region': 'composer' }, submittedField);
    current.append(submitted);

    const fresh = h('div', { id: 'root' });
    fresh.append(h('form', { id: 'composer-a', 'data-continuity-region': 'composer' }));
    fresh.append(h('form', { id: 'workspace-line-create-9', 'data-continuity-region': 'composer' }, 'empty'));

    const replaced = replaceSubmittedRegion(current, fresh, submitted);

    assert.equal(replaced, true);
    assert.equal(current.getElementById('composer-field').value, 'DIRTY DURING EDIT');
    assert.equal(current.getElementById('workspace-line-create-9').textContent, 'empty');
    assert.equal(current.getElementById('part-description'), null);
});
