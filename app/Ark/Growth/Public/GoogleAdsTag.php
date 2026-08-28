<?php

namespace App\Ark\Growth\Public;

final class GoogleAdsTag
{
    public static function enabled(): bool
    {
        return self::tagId() !== null;
    }

    public static function tagId(): ?string
    {
        $id = trim((string) config('growth.integrations.google_ads.tag_id', ''));

        if ($id === '' || ! preg_match('/^AW-\d+$/', $id)) {
            return null;
        }

        return $id;
    }

    public static function ga4MeasurementId(): ?string
    {
        $id = trim((string) config('growth.integrations.google_ads.ga4_measurement_id', ''));

        if ($id === '' || ! preg_match('/^G-[A-Z0-9]+$/', $id)) {
            return null;
        }

        return $id;
    }
}
