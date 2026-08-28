<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ecosystem display theme cookie domain
    |--------------------------------------------------------------------------
    |
    | Shared across ARK surfaces (app, learn, portal) so BookStack can honor
    | ARK user light/dark preference before OIDC session sync is complete.
    |
    */
    'cookie_domain' => env('ARK_ECOSYSTEM_COOKIE_DOMAIN', env('SESSION_DOMAIN')),

    /*
    |--------------------------------------------------------------------------
    | Ecosystem product URLs (UX switcher — not routing authority)
    |--------------------------------------------------------------------------
    */
    'operations_url' => rtrim((string) env('ARK_OPERATIONS_URL', env('APP_URL', 'http://localhost')), '/'),

    'arkademy_url' => rtrim((string) env('ARK_ARKADEMY_URL', env('BOOKSTACK_URL', 'https://learn.demo-auto.test')), '/'),

    'platform_url' => rtrim((string) env('ARK_PLATFORM_URL', 'https://platform.autorepairkeeper.com'), '/'),

    'shelf_slug' => env('BOOKSTACK_SHELF_SLUG', 'shop-in-a-box'),
];
