<?php

return [
    /*
    | Rollback ordinary path: set false to hide Platform Website management.
    | Data (sites, drafts, revisions) is retained.
    */
    'management_enabled' => (bool) env('PLATFORM_WEBSITE_MANAGEMENT_ENABLED', true),
];
