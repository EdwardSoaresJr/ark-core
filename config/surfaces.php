<?php

$appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');
$appHost = parse_url($appUrl, PHP_URL_HOST) ?: 'localhost';

return [
    'enabled' => (bool) env('SURFACE_DOMAINS_ENABLED', false),

    'app' => env('APP_DOMAIN', $appHost),

    'portal' => env('PORTAL_DOMAIN'),

    'learn' => env('LEARN_DOMAIN'),

    // Canonical public hostname. Aliases resolve the same website publication.
    'public' => env('PUBLIC_DOMAIN', env('SHOP_LOCAL_PARENT_DOMAIN')),

    'public_aliases' => array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env('SURFACE_PUBLIC_ALIASES', '')),
    ))),

    // ARK Platform product - company domain (never a Shop).
    'company' => env('COMPANY_DOMAIN'),

    'company_www' => env('COMPANY_WWW_DOMAIN'),

    // Future: Auth + Cloud dashboard (Phase 2+). Empty in Phase 1.
    'cloud_app' => env('CLOUD_APP_DOMAIN'),
];
