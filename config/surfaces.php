<?php

$appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');
$appHost = parse_url($appUrl, PHP_URL_HOST) ?: 'localhost';

return [
    'enabled' => (bool) env('SURFACE_DOMAINS_ENABLED', false),

    'app' => env('APP_DOMAIN', $appHost),

    'portal' => env('PORTAL_DOMAIN'),

    'learn' => env('LEARN_DOMAIN'),

    // Production: PUBLIC_DOMAIN=demo-auto.test. Local: falls back to SHOP_LOCAL_PARENT_DOMAIN (demo-auto.test).
    'public' => env('PUBLIC_DOMAIN', env('SHOP_LOCAL_PARENT_DOMAIN')),

    // ARK Platform product — company domain (never a Shop).
    'company' => env('COMPANY_DOMAIN'),

    'company_www' => env('COMPANY_WWW_DOMAIN'),

    // Future: Auth + Cloud dashboard (Phase 2+). Empty in Phase 1.
    'cloud_app' => env('CLOUD_APP_DOMAIN'),
];
