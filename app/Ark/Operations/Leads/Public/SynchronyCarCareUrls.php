<?php

namespace App\Ark\Operations\Leads\Public;

/**
 * Demo Auto Repair Synchrony Car Care merchant links — site codes track channel attribution.
 *
 * 401 — website text links
 * 402 — QR codes (signage, printed materials, estimate QR later)
 * 403 — web embeds / official Apply button from the marketing center
 */
final class SynchronyCarCareUrls
{
    public const MERCHANT_ID = 'CR000000000';

    public const SITE_CODE_LINK = 'demo401';

    public const SITE_CODE_QR = 'demo402';

    public const SITE_CODE_EMBED = 'demo403';

    public const APPLY_BUTTON_IMAGE = 'https://www.synchrony.com/mmc/assets/syf_apply_218.png';

    public static function linkUrl(): string
    {
        return self::forSiteCode(self::SITE_CODE_LINK);
    }

    public static function qrUrl(): string
    {
        return self::forSiteCode(self::SITE_CODE_QR);
    }

    public static function embedUrl(): string
    {
        return self::forSiteCode(self::SITE_CODE_EMBED);
    }

    public static function forSiteCode(string $siteCode): string
    {
        return sprintf(
            'https://www.synchrony.com/mmc/%s?sitecode=%s',
            self::MERCHANT_ID,
            $siteCode,
        );
    }

    /**
     * @return array{
     *     link_url: string,
     *     qr_url: string,
     *     embed_url: string,
     *     apply_button_image: string,
     * }
     */
    public static function channels(): array
    {
        return [
            'link_url' => self::linkUrl(),
            'qr_url' => self::qrUrl(),
            'embed_url' => self::embedUrl(),
            'apply_button_image' => self::APPLY_BUTTON_IMAGE,
        ];
    }
}
