<?php

namespace App\Ark\Runtime\Surfaces;

/**
 * Resolve session cookie Domain for the active request host.
 *
 * Ops surfaces (*.demo-auto.test) keep SESSION_DOMAIN sharing.
 * Company product host (autorepairkeeper.com) must use host-only cookies —
 * browsers reject Domain=.demo-auto.test on a different registrable domain.
 */
final class SessionCookieDomain
{
    /**
     * @param  string|null  $sharedDomain  Ops shared domain from env (e.g. .demo-auto.test). Never mutate this source.
     */
    public static function forHost(string $host, ?string $sharedDomain): ?string
    {
        $host = strtolower(trim($host));

        if ($host !== '' && self::isCompanyProductHost($host)) {
            return null;
        }

        if ($sharedDomain === null || $sharedDomain === '') {
            return null;
        }

        return $sharedDomain;
    }

    public static function isCompanyProductHost(string $host): bool
    {
        $candidates = array_filter([
            SurfaceRouting::companyHost(),
            SurfaceRouting::companyWwwHost(),
            SurfaceRouting::cloudAppHost(),
        ]);

        foreach ($candidates as $candidate) {
            if ($host === strtolower((string) $candidate)) {
                return true;
            }
        }

        return false;
    }
}
