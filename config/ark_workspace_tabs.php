<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Workspace tabs (operational multitasking)
    |--------------------------------------------------------------------------
    |
    | Entity-based workspaces for operations — not browser tabs or iframes.
    | Behavior ported from ARK-SMS; see docs in legacy ark-sms-app repo.
    |
    */

    'enabled' => (bool) env('ARK_WORKSPACE_TABS_ENABLED', true),

    'max_tabs' => (int) env('ARK_WORKSPACE_TABS_MAX', 12),

    'tab_min_width' => 176,

    'desktop_min_width' => 1024,

    'persist_local' => true,

    'intercept_links' => true,

    /**
     * Always-present pinned workspaces (left dock). Order matters.
     *
     * @var list<array{key: string, entityType: string, entityId: string, route: string, title: string, subtitle?: string}>
     */
    'permanent_pinned' => [],

    /**
     * Workspace keys that must never appear in the tab bar (e.g. retired permanent tabs).
     *
     * @var list<string>
     */
    'excluded_workspace_keys' => [
        'report:operations',
    ],

    /**
     * Always-present workspaces fixed on the right rail (outside the scroll area).
     *
     * @var list<array{key: string, entityType: string, entityId: string, route: string, title: string, subtitle?: string}>
     */
    'docked_contextual' => [
        [
            'key' => 'intake:service',
            'entityType' => 'intake',
            'entityId' => 'service',
            'route' => '/app/intake/new',
            'title' => 'Check In',
        ],
    ],

    /**
     * @var list<string>
     */
    'types' => [
        'intake',
        'repair_order',
        'customer',
        'vehicle',
        'inbox',
        'report',
    ],

    /**
     * Path patterns (relative, no leading slash).
     *
     * @var array<string, array{type: string, pattern: string, id_group: int}>
     */
    'path_patterns' => [
        'intake_new' => [
            'type' => 'intake',
            'pattern' => '#^app/intake/new$#',
            'id_group' => 1,
        ],
        'repair_order_edit' => [
            'type' => 'repair_order',
            'pattern' => '#^app/repair-orders/(\d+)/edit$#',
            'id_group' => 1,
        ],
        'repair_order_review' => [
            'type' => 'repair_order',
            'pattern' => '#^app/repair-orders/(\d+)/estimate-review$#',
            'id_group' => 1,
        ],
        'repair_order_show' => [
            'type' => 'repair_order',
            'pattern' => '#^app/repair-orders/(\d+)$#',
            'id_group' => 1,
        ],
        'customer_show' => [
            'type' => 'customer',
            'pattern' => '#^app/customers/(\d+)$#',
            'id_group' => 1,
        ],
        'inbox_show' => [
            'type' => 'inbox',
            'pattern' => '#^app/inbox/(\d+)$#',
            'id_group' => 1,
        ],
    ],

];
