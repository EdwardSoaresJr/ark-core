/**
 * Class names for the workspace tab strip, per shell. The legacy shell styles ops-workspace-tab* in app.css; Tabler
 * pages use Tabler classes. Pure helpers; no DOM / storage side effects.
 *
 * Elements the strip finds again carry data-workspace-tab* hooks, so its selectors do not depend on either map.
 * An empty string means the shell adds no class (or, for an element that only one shell renders, no element).
 */

const legacy = {
    rootVisible: 'is-visible',
    rootHidden: '',
    bodyActive: 'ops-workspace-tabs-active',
    scroll: 'ops-workspace-tabs__scroll',
    pinnedZone: 'ops-workspace-tabs__pinned-zone',
    contextualZone: 'ops-workspace-tabs__contextual-zone',
    divider: 'ops-workspace-tabs__divider',
    railDivider: 'ops-workspace-tabs__divider ops-workspace-tabs__divider--rail',
    rightRail: 'ops-workspace-tabs__right-rail',
    rightZone: 'ops-workspace-tabs__right-zone',
    tab: 'ops-workspace-tab',
    tabActive: 'ops-workspace-tab--active',
    tabDock: 'ops-workspace-tab--dock',
    tabDockLeft: 'ops-workspace-tab--dock-left',
    tabDockRight: 'ops-workspace-tab--dock-right',
    tabIntake: 'ops-workspace-tab--intake',
    tabRepairOrder: 'ops-workspace-tab--repair-order',
    tabUrgency: {
        low: 'ops-workspace-tab--urgency-low',
        medium: 'ops-workspace-tab--urgency-medium',
        high: 'ops-workspace-tab--urgency-high',
    },
    tabUrgencyInactive: {},
    tabStale: 'ops-workspace-tab--stale',
    tabDragging: 'ops-workspace-tab--dragging',
    tabDropTarget: 'ops-workspace-tab--drop-target',
    tabWidth: '',
    icon: 'ops-workspace-tab__icon',
    iconActive: '',
    iconTone: {},
    label: 'ops-workspace-tab__label',
    labelRow: '',
    labelStack: 'ops-workspace-tab__label--stack',
    title: 'ops-workspace-tab__title',
    titleDock: '',
    titleStaleInactive: '',
    subtitle: 'ops-workspace-tab__subtitle',
    dirty: 'ops-workspace-tab__dirty',
    signal: 'ops-workspace-tab__signal',
    close: 'ops-workspace-tab__close',
    closeText: '&times;',
    overflowWrap: 'ops-workspace-tabs__overflow-wrap',
    overflow: 'ops-workspace-tabs__overflow',
    overflowOpen: 'is-open',
    overflowMenu: 'ops-workspace-tabs__overflow-menu',
    overflowMenuOpen: 'is-open',
    overflowItem: 'ops-workspace-tabs__overflow-item',
    overflowItemActive: 'is-active',
    overflowItemLock: 'ops-workspace-tabs__overflow-item-lock',
    overflowItemLabel: 'ops-workspace-tabs__overflow-item-label',
    lockIcon: 'ops-workspace-lock',
};

const tabler = {
    rootVisible: 'd-flex',
    rootHidden: 'd-none',
    bodyActive: '',
    scroll: 'nav nav-bordered border-0 flex-nowrap flex-fill gap-1 overflow-x-auto overflow-y-hidden px-2 pt-1',
    pinnedZone: 'd-flex flex-shrink-0 gap-1',
    contextualZone: 'd-flex flex-shrink-0 gap-1',
    divider: 'vr flex-shrink-0 my-2',
    railDivider: 'vr flex-shrink-0 my-2',
    rightRail: 'd-flex flex-shrink-0 gap-1 ms-auto',
    rightZone: 'nav nav-bordered border-0 flex-nowrap gap-1 pe-2 pt-1',
    tab: 'nav-link cursor-pointer flex-shrink-0 gap-2 mb-0 px-2 py-1',
    tabActive: 'active',
    tabDock: '',
    tabDockLeft: '',
    tabDockRight: '',
    tabIntake: '',
    tabRepairOrder: '',
    tabUrgency: {},
    tabUrgencyInactive: {
        medium: 'border-warning',
        high: 'border-danger',
    },
    tabStale: '',
    tabDragging: 'opacity-50',
    tabDropTarget: 'bg-primary-lt',
    tabWidth: '11rem',
    icon: 'status-dot flex-shrink-0',
    iconActive: 'status-primary',
    iconTone: {
        repair_order: 'status-primary',
        customer: 'status-green',
        vehicle: 'status-yellow',
        lead: 'status-purple',
        inbox: 'status-pink',
        intake: 'status-cyan',
        report: 'status-secondary',
        default: 'status-secondary',
    },
    label: 'd-flex flex-fill overflow-hidden lh-sm',
    labelRow: 'align-items-center gap-1',
    labelStack: 'flex-column',
    title: 'text-truncate fw-bold',
    titleDock: 'text-uppercase small',
    titleStaleInactive: 'fst-italic opacity-75',
    subtitle: 'text-truncate text-secondary small fw-normal',
    dirty: 'flex-shrink-0 text-orange small lh-1',
    signal: 'flex-shrink-0 text-orange small fw-bold lh-1',
    close: 'btn-close flex-shrink-0 p-0',
    closeText: '',
    overflowWrap: 'dropdown flex-shrink-0 align-self-center',
    overflow: 'btn btn-sm',
    overflowOpen: 'show',
    overflowMenu: 'dropdown-menu dropdown-menu-end',
    overflowMenuOpen: 'show',
    overflowItem: 'dropdown-item gap-2',
    overflowItemActive: 'active',
    overflowItemLock: 'flex-shrink-0 text-azure',
    overflowItemLabel: 'text-truncate flex-fill',
    lockIcon: 'icon',
};

export const WORKSPACE_TABS_UI = Object.freeze({ legacy, tabler });

/**
 * @param {string|null|undefined} mode the mount's data-workspace-tabs-ui
 */
export function workspaceTabsUi(mode) {
    return mode === 'tabler' ? tabler : legacy;
}

export function classNames(...parts) {
    return parts.filter(Boolean).join(' ');
}

export function classTokens(classes) {
    return String(classes || '').split(/\s+/).filter(Boolean);
}

/**
 * @param {typeof legacy} ui
 * @param {{
 *   active?: boolean,
 *   dockLeft?: boolean,
 *   dockRight?: boolean,
 *   intake?: boolean,
 *   repairOrder?: boolean,
 *   urgency?: string,
 *   stale?: boolean,
 * }} flags
 */
export function workspaceTabClassName(ui, flags) {
    const dock = !!(flags.dockLeft || flags.dockRight);

    return classNames(
        ui.tab,
        flags.active ? ui.tabActive : '',
        dock ? ui.tabDock : '',
        flags.dockLeft ? ui.tabDockLeft : '',
        flags.dockRight ? ui.tabDockRight : '',
        flags.intake ? ui.tabIntake : '',
        flags.repairOrder ? ui.tabRepairOrder : '',
        flags.urgency ? ui.tabUrgency[flags.urgency] : '',
        flags.urgency && !flags.active ? ui.tabUrgencyInactive[flags.urgency] : '',
        flags.stale ? ui.tabStale : '',
    );
}

export function workspaceTabIconClassName(ui, entityType, active) {
    const tone = active && ui.iconActive
        ? ui.iconActive
        : (ui.iconTone[entityType] || ui.iconTone.default || '');

    return classNames(ui.icon, tone);
}

export function workspaceTabLabelClassName(ui, stack) {
    return classNames(ui.label, stack ? ui.labelStack : ui.labelRow);
}

export function workspaceTabTitleClassName(ui, { dock = false, stale = false, active = false } = {}) {
    return classNames(
        ui.title,
        dock ? ui.titleDock : '',
        stale && !active ? ui.titleStaleInactive : '',
    );
}
