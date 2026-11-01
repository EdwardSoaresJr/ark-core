import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import {
    WORKSPACE_TABS_UI,
    workspaceTabClassName,
    workspaceTabIconClassName,
    workspaceTabLabelClassName,
    workspaceTabsUi,
    workspaceTabTitleClassName,
} from '../../resources/js/ark-workspace-tabs-ui.js';

const { legacy, tabler } = WORKSPACE_TABS_UI;

function allClassStrings(map) {
    return Object.values(map).flatMap((value) => (typeof value === 'object' ? Object.values(value) : [value]));
}

/** The class list ark-workspace-tabs.js built before the class map existed. */
function originalLegacyTabClassName({ active, dockLeft, dockRight, intake, repairOrder, urgency, stale }) {
    return [
        'ops-workspace-tab',
        active ? 'ops-workspace-tab--active' : '',
        dockLeft || dockRight ? 'ops-workspace-tab--dock' : '',
        dockLeft ? 'ops-workspace-tab--dock-left' : '',
        dockRight ? 'ops-workspace-tab--dock-right' : '',
        intake ? 'ops-workspace-tab--intake' : '',
        repairOrder ? 'ops-workspace-tab--repair-order' : '',
        urgency ? `ops-workspace-tab--urgency-${urgency}` : '',
        stale ? 'ops-workspace-tab--stale' : '',
    ].filter(Boolean).join(' ');
}

test('the mount picks the Tabler map only for data-workspace-tabs-ui="tabler"', () => {
    assert.equal(workspaceTabsUi('tabler'), tabler);
    assert.equal(workspaceTabsUi('legacy'), legacy);
    assert.equal(workspaceTabsUi(undefined), legacy);
    assert.equal(workspaceTabsUi(''), legacy);
});

test('both maps define the same keys', () => {
    assert.deepEqual(Object.keys(tabler).sort(), Object.keys(legacy).sort());
});

test('the Tabler map has no legacy classes', () => {
    allClassStrings(tabler).forEach((classes) => {
        assert.doesNotMatch(classes, /(^|\s)(ops-|is-(open|active|visible))/, classes);
    });
});

test('legacy tabs keep the class list they had before the map', () => {
    const combos = [];
    for (const active of [false, true]) {
        for (const dock of ['none', 'left', 'right']) {
            for (const urgency of [undefined, 'low', 'medium', 'high']) {
                for (const stale of [false, true]) {
                    combos.push({
                        active,
                        dockLeft: dock === 'left',
                        dockRight: dock === 'right',
                        intake: dock === 'right',
                        repairOrder: dock === 'none',
                        urgency,
                        stale,
                    });
                }
            }
        }
    }

    combos.forEach((flags) => {
        assert.equal(workspaceTabClassName(legacy, flags), originalLegacyTabClassName(flags), JSON.stringify(flags));
    });
});

test('legacy labels, titles, and icons keep their classes', () => {
    assert.equal(workspaceTabLabelClassName(legacy, false), 'ops-workspace-tab__label');
    assert.equal(workspaceTabLabelClassName(legacy, true), 'ops-workspace-tab__label ops-workspace-tab__label--stack');
    assert.equal(workspaceTabTitleClassName(legacy, { dock: true, stale: true }), 'ops-workspace-tab__title');
    assert.equal(workspaceTabIconClassName(legacy, 'customer', false), 'ops-workspace-tab__icon');
    assert.equal(workspaceTabIconClassName(legacy, 'repair_order', true), 'ops-workspace-tab__icon');
    assert.equal(legacy.closeText, '&times;');
    assert.equal(legacy.rootVisible, 'is-visible');
    assert.equal(legacy.bodyActive, 'ops-workspace-tabs-active');
});

test('Tabler tabs are nav links that mark urgency and staleness only while inactive', () => {
    const inactive = workspaceTabClassName(tabler, { urgency: 'high', stale: true });
    const active = workspaceTabClassName(tabler, { active: true, urgency: 'high', stale: true });

    assert.match(inactive, /(^|\s)nav-link(\s|$)/);
    assert.match(inactive, /border-danger/);
    assert.doesNotMatch(inactive, /(^|\s)active(\s|$)/);
    assert.match(active, /(^|\s)active(\s|$)/);
    assert.doesNotMatch(active, /border-danger/);
    assert.match(workspaceTabClassName(tabler, { urgency: 'medium' }), /border-warning/);
    assert.doesNotMatch(workspaceTabClassName(tabler, { urgency: 'low' }), /border-/);

    assert.match(workspaceTabTitleClassName(tabler, { stale: true }), /fst-italic/);
    assert.doesNotMatch(workspaceTabTitleClassName(tabler, { stale: true, active: true }), /fst-italic/);
    assert.match(workspaceTabTitleClassName(tabler, { dock: true }), /text-uppercase/);
});

test('Tabler labels stack without conflicting alignment utilities', () => {
    const row = workspaceTabLabelClassName(tabler, false);
    const stack = workspaceTabLabelClassName(tabler, true);

    assert.match(row, /align-items-center/);
    assert.match(stack, /flex-column/);
    assert.doesNotMatch(stack, /align-items-/);
});

test('Tabler entity dots use a status color per type and primary when active', () => {
    assert.equal(workspaceTabIconClassName(tabler, 'customer', false), 'status-dot flex-shrink-0 status-green');
    assert.equal(workspaceTabIconClassName(tabler, 'customer', true), 'status-dot flex-shrink-0 status-primary');
    assert.equal(workspaceTabIconClassName(tabler, 'unknown', false), 'status-dot flex-shrink-0 status-secondary');
    assert.equal(tabler.closeText, '');
    assert.match(tabler.close, /btn-close/);
    assert.equal(tabler.rootHidden, 'd-none');
    assert.equal(tabler.rootVisible, 'd-flex');
});

test('the strip script finds its own elements by data hooks, not legacy classes', () => {
    const script = readFileSync(new URL('../../resources/js/ark-workspace-tabs.js', import.meta.url), 'utf8');

    assert.doesNotMatch(script, /['"`.]ops-workspace-tab(?!s['"])/);
    assert.match(script, /getElementById\('ops-workspace-tabs'\)/);
    assert.doesNotMatch(script, /is-(open|active|visible)/);
    assert.match(script, /querySelector\('\[data-workspace-tabs-scroll\]'\)/);
    assert.match(script, /closest\('\[data-workspace-tab-close\]'\)/);
    assert.match(script, /workspaceTabsUi\(barEl\?\.dataset\.workspaceTabsUi\)/);
});
