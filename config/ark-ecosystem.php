<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ecosystem display theme cookie domain
    |--------------------------------------------------------------------------
    |
    | Shared across ARK surfaces (app, learn, portal) so a later standalone
    | ARKademy host can honor the same light/dark preference.
    |
    */
    'cookie_domain' => env('ARK_ECOSYSTEM_COOKIE_DOMAIN', env('SESSION_DOMAIN')),

    /*
    |--------------------------------------------------------------------------
    | Ecosystem product URLs (UX switcher — not routing authority)
    |--------------------------------------------------------------------------
    */
    'operations_url' => rtrim((string) env('ARK_OPERATIONS_URL', env('APP_URL', 'http://localhost')), '/'),

    'arkademy_url' => rtrim((string) env('ARK_ARKADEMY_URL', env('APP_URL', 'http://localhost')), '/'),

    'platform_url' => rtrim((string) env('ARK_PLATFORM_URL', 'https://platform.autorepairkeeper.com'), '/'),
];
