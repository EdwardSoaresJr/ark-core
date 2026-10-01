<?php

/**
 * External booking host (marketing / Lead form). Not ARK Website CMS.
 *
 * LNP cutover: keep lugsnplugs.com on the booking-capable runtime.
 * Public Core must not claim that host without BOOKING_SURFACE_BASE_URL.
 */
return [
    /*
    | Absolute origin that still serves GET /book and POST /leads
    | (e.g. https://lugsnplugs.com). Empty = no bridge; /book stays absent.
    */
    'base_url' => rtrim((string) env('BOOKING_SURFACE_BASE_URL', ''), '/'),

    /*
    | When true (default in production), boot fails if PUBLIC_DOMAIN / APP_URL
    | host is a protected marketing host and base_url is empty.
    */
    'enforce' => filter_var(
        env('BOOKING_SURFACE_ENFORCE', env('APP_ENV') === 'production' ? 'true' : 'false'),
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
    | Hosts that still require an external booking surface after Public Core cutover.
    | Do not attach these FQDNs to a Core-only Coolify app without BOOKING_SURFACE_BASE_URL.
    */
    'protected_hosts' => array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env(
            'BOOKING_SURFACE_PROTECTED_HOSTS',
            'lugsnplugs.com,www.lugsnplugs.com'
        ))
    ))),
];
