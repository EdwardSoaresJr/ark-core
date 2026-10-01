<?php

return [
    /*
    | Rollback ordinary path: set false to hide Platform Website management.
    | Data (sites, drafts, revisions) is retained.
    */
    'management_enabled' => (bool) env('PLATFORM_WEBSITE_MANAGEMENT_ENABLED', true),

    /*
    | New Platform uploads stay off until shared-disk durability is verified in production.
    | Import may reference existing Core media paths only.
    */
    'uploads_enabled' => (bool) env('PLATFORM_WEBSITE_UPLOADS_ENABLED', false),
];
