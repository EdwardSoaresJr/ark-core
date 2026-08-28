<?php

namespace App\Ark\Platform\Cloud;

use App\Ark\Runtime\Surfaces\SurfaceRouting;

/**
 * Cloud product URLs.
 *
 * Production (COMPANY_DOMAIN set): always https://autorepairkeeper.com/...
 * Local / tests (no company domain): /cloud on the current app host.
 */
final class CloudUrls
{
    /** @var array<string, string> */
    private const PATHS = [
        'home' => '/',
        'features' => '/features',
        'pricing' => '/pricing',
        'resources' => '/resources',
        'demo' => '/demo',
        'login' => '/login',
        'login.store' => '/login',
        'trial.shop' => '/trial',
        'trial.shop.store' => '/trial/shop',
        'trial.workspace' => '/trial/workspace',
        'trial.workspace.store' => '/trial/workspace',
        'trial.account' => '/trial/account',
        'trial.account.store' => '/trial/account',
        'trial.provisioning' => '/trial/provisioning',
        'welcome' => '/welcome',
        'dashboard' => '/dashboard',
        'workspace.open' => '/open-workspace',
    ];

    public static function route(string $name): string
    {
        return self::url(self::PATHS[$name] ?? '/');
    }

    public static function url(string $path): string
    {
        $trimmed = trim($path, '/');
        $normalized = $trimmed === '' ? '/' : '/'.$trimmed;

        if (SurfaceRouting::companyEnabled()) {
            return SurfaceRouting::urlForHost((string) SurfaceRouting::companyHost(), $normalized);
        }

        return $normalized === '/'
            ? url('/cloud')
            : url('/cloud'.$normalized);
    }

    public static function usesCloudPrefix(): bool
    {
        return ! SurfaceRouting::companyEnabled();
    }
}
