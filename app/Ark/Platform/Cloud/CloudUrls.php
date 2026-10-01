<?php

namespace App\Ark\Platform\Cloud;

/**
 * External ARK Complete / company portal URLs.
 *
 * Core Boxes never host the managed-service portal. These point at
 * ARK_PLATFORM_BASE_URL (alias ARK_CLOUD_BASE_URL), e.g. https://cloud.arksms.com
 * during the DNS compatibility period.
 */
final class CloudUrls
{
    public static function base(): string
    {
        return rtrim((string) (
            config('services.ark_platform.base_url')
            ?: config('services.ark_cloud.base_url')
            ?: config('services.ark_mail.base_url')
            ?: ''
        ), '/');
    }

    public static function portalHome(): ?string
    {
        $base = self::base();

        return $base !== '' ? $base.'/' : null;
    }

    public static function login(): ?string
    {
        $base = self::base();

        return $base !== '' ? $base.'/login' : null;
    }

    public static function pairing(): ?string
    {
        $base = self::base();

        return $base !== '' ? $base.'/portal/pairing' : null;
    }

    public static function connect(string $pairingPublicId, ?string $returnUrl = null): ?string
    {
        $base = self::base();
        if ($base === '' || $pairingPublicId === '') {
            return null;
        }

        $url = $base.'/connect/'.rawurlencode($pairingPublicId);
        if (is_string($returnUrl) && $returnUrl !== '') {
            $url .= '?'.http_build_query(['return_url' => $returnUrl]);
        }

        return $url;
    }

    public static function go(string $to, ?string $shopPublicId = null): ?string
    {
        $base = self::base();
        if ($base === '') {
            return null;
        }

        $query = array_filter([
            'to' => $to,
            'shop' => $shopPublicId,
        ]);

        return $base.'/go?'.http_build_query($query);
    }

    public static function dashboard(): ?string
    {
        $base = self::base();

        return $base !== '' ? $base.'/portal' : null;
    }

    /** @deprecated Use dashboard()/login(); retained for stale view references during cleanup. */
    public static function route(string $name): string
    {
        return match ($name) {
            'login', 'login.store' => self::login() ?? '/',
            'dashboard', 'welcome', 'home' => self::dashboard() ?? '/',
            default => self::portalHome() ?? '/',
        };
    }

    public static function usesCloudPrefix(): bool
    {
        return false;
    }
}
