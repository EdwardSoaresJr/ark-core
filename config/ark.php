<?php

return [
    // LugsNPlugs production still has website/Growth tables. Keep this true there
    // so the deferred drop migration is not loaded and is not recorded as ran.
    'preserve_website_growth_schema' => (bool) env('ARK_PRESERVE_WEBSITE_GROWTH_SCHEMA', false),

    // ARKademy stays reachable by URL and from in-page guide links; this only lists it in staff navigation.
    'arkademy_in_navigation' => (bool) env('ARK_ARKADEMY_IN_NAVIGATION', false),
];
