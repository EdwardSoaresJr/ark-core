<?php

namespace App\Ark\Operations\Leads\Measurement;

/**
 * Shop-wide measurement for a verified appointment request.
 *
 * Empty config emits nothing, so a shop that has not turned this on is unchanged.
 * The recorded event is a lead, not a confirmed appointment or a completed repair.
 */
final class AppointmentRequestMeasurement
{
    public const LEAD_TYPE = 'appointment_request';

    public static function enabled(): bool
    {
        return self::ga4Id() !== null;
    }

    public static function ga4Id(): ?string
    {
        return self::matching('ga4_measurement_id', '/^G-[A-Z0-9]{4,20}$/');
    }

    public static function adsId(): ?string
    {
        return self::matching('ads_id', '/^AW-\d{5,20}$/');
    }

    public static function adsConversionLabel(): ?string
    {
        if (self::adsId() === null) {
            return null;
        }

        return self::matching('ads_conversion_label', '/^[A-Za-z0-9_-]{1,80}$/');
    }

    /**
     * @return list<string>
     */
    public static function linkerDomains(): array
    {
        $raw = config('ark-appointment-request-measurement.linker_domains', '');
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $domains = [];
        foreach (preg_split('/\s*,\s*/', trim($raw)) ?: [] as $domain) {
            $domain = strtolower(trim($domain));
            if (preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$/', $domain) === 1) {
                $domains[] = $domain;
            }
        }

        return array_values(array_unique($domains));
    }

    /**
     * @return array{ga4_id: string, ga4_config: array<string, mixed>, ads_id: ?string}|null
     */
    public static function head(): ?array
    {
        $ga4Id = self::ga4Id();
        if ($ga4Id === null) {
            return null;
        }

        $config = [];
        $domains = self::linkerDomains();
        if ($domains !== []) {
            $config['linker'] = ['domains' => $domains];
        }

        return [
            'ga4_id' => $ga4Id,
            'ga4_config' => $config,
            'ads_id' => self::adsId(),
        ];
    }

    private static function matching(string $key, string $pattern): ?string
    {
        $value = config('ark-appointment-request-measurement.'.$key);
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return preg_match($pattern, $value) === 1 ? $value : null;
    }
}
