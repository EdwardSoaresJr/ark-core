<?php

/**
 * Shop deployment identity — single public origin for all shop capabilities.
 *
 * @see docs/platform/shop-identity-v1.md
 */
return [

    'base_url' => rtrim((string) env('SHOP_BASE_URL', env('APP_URL', 'http://localhost')), '/'),

    /*
    | Stable shop identity for machine credentials (Dragon service tokens).
    | Defaults to the host of SHOP_BASE_URL / APP_URL.
    */
    'identity' => (static function (): string {
        $explicit = trim((string) env('SHOP_IDENTITY', ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $base = rtrim((string) env('SHOP_BASE_URL', env('APP_URL', 'http://localhost')), '/');
        $host = parse_url($base, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'default';
    })(),

    /*
    | Desk phone microbrowser (Poly VVX) — plain HTTP, token-gated read-only screen.
    | Legacy firmware cannot complete modern Let's Encrypt TLS handshakes.
    */
    'microbrowser_scheme' => env('SHOP_MICROBROWSER_SCHEME', 'http'),

    /*
    | Max open-RO cards returned by GET /api/dragon/work items[].
    | Summary counts are always computed across all open ROs (not capped).
    */
    'dragon_work_items_limit' => (int) env('DRAGON_WORK_ITEMS_LIMIT', 500),

];
