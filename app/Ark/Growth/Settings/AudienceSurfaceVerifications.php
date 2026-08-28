<?php

namespace App\Ark\Growth\Settings;

use App\Ark\Operations\Leads\Public\ShopPublicSurfaceRaw;
use Carbon\CarbonImmutable;

final class AudienceSurfaceVerifications
{
    private const STORAGE_KEY = 'audience_surface_verifications';

    /**
     * @return array<string, array{label: string, last_verified_at: ?string, days_since_verified: ?int, is_stale: bool, status: string}>
     */
    public static function surfacesForDisplay(): array
    {
        $stored = self::stored();
        $staleDays = max(1, (int) config('entity_health.verification_stale_days', 90));
        $today = CarbonImmutable::today();

        $surfaces = [];

        foreach (config('entity_health.audience_surfaces', []) as $key => $label) {
            $lastVerified = self::parseDate($stored[$key]['last_verified_at'] ?? null);
            $daysSince = $lastVerified !== null
                ? (int) $lastVerified->diffInDays($today, false)
                : null;

            $isStale = $lastVerified === null || $daysSince > $staleDays;

            $surfaces[$key] = [
                'key' => $key,
                'label' => $label,
                'last_verified_at' => $lastVerified?->toDateString(),
                'days_since_verified' => $daysSince,
                'is_stale' => $isStale,
                'status' => $lastVerified === null
                    ? 'never_verified'
                    : ($isStale ? 'stale' : 'fresh'),
            ];
        }

        return $surfaces;
    }

    public static function markVerified(string $surfaceKey): void
    {
        $allowed = array_keys(config('entity_health.audience_surfaces', []));

        if (! in_array($surfaceKey, $allowed, true)) {
            return;
        }

        $raw = ShopPublicSurfaceRaw::read();
        $verifications = is_array($raw[self::STORAGE_KEY] ?? null) ? $raw[self::STORAGE_KEY] : [];
        $verifications[$surfaceKey] = [
            'last_verified_at' => CarbonImmutable::today()->toDateString(),
        ];
        $raw[self::STORAGE_KEY] = $verifications;

        ShopPublicSurfaceRaw::write($raw);
    }

    /**
     * @return array<string, mixed>
     */
    private static function stored(): array
    {
        $raw = ShopPublicSurfaceRaw::read();
        $verifications = $raw[self::STORAGE_KEY] ?? [];

        return is_array($verifications) ? $verifications : [];
    }

    private static function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
