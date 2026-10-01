<?php

declare(strict_types=1);

namespace App\Ark\Operations\Printing;

use App\Ark\Operations\Settings\ShopSettings;

/**
 * Single-shop printing authority (parity with ARK-SMS TenantPrintingSettings).
 */
final class ShopPrintingSettings
{
    public static function isEnabled(): bool
    {
        return (bool) ShopSettings::current()->qz_printing_enabled;
    }

    public static function keyTagPrinter(): string
    {
        $saved = trim((string) (ShopSettings::current()->qz_printing_key_tag_printer ?? ''));

        return $saved !== ''
            ? $saved
            : (string) config('printing.key_tag_printer', 'Brother QL-800');
    }

    public static function oilStickerPrinter(): string
    {
        $saved = trim((string) (ShopSettings::current()->qz_printing_oil_sticker_printer ?? ''));

        if ($saved !== '') {
            return $saved;
        }

        return self::keyTagPrinter();
    }

    public static function keyTagLabelWidthMm(): float
    {
        $saved = ShopSettings::current()->qz_key_tag_label_width_mm;

        return $saved !== null
            ? (float) $saved
            : (float) config('printing.key_tag_qz_page.width_mm', 62.0);
    }

    public static function keyTagLabelHeightMm(): float
    {
        $saved = ShopSettings::current()->qz_key_tag_label_height_mm;

        return $saved !== null
            ? (float) $saved
            : (float) config('printing.key_tag_qz_page.height_mm', 38.1);
    }

    /**
     * @return array{width_mm: float, height_mm: float}
     */
    public static function keyTagQzPage(): array
    {
        return [
            'width_mm' => self::keyTagLabelWidthMm(),
            'height_mm' => self::keyTagLabelHeightMm(),
        ];
    }

    public static function oilStickerLabelWidthMm(): float
    {
        return self::keyTagLabelWidthMm();
    }

    public static function oilStickerLabelHeightMm(): float
    {
        return self::keyTagLabelHeightMm();
    }

    /**
     * @return array{width_mm: float, height_mm: float}
     */
    public static function oilStickerQzPage(): array
    {
        return [
            'width_mm' => self::oilStickerLabelWidthMm(),
            'height_mm' => self::oilStickerLabelHeightMm(),
        ];
    }

    public static function keyTagMediaType(): string
    {
        $value = trim((string) (ShopSettings::current()->qz_key_tag_media_type ?? 'mono'));

        return in_array($value, ['mono', 'red_black'], true) ? $value : 'mono';
    }

    /**
     * @return 'auto'|'portrait'|'landscape'
     */
    public static function keyTagQzOrientation(): string
    {
        $value = trim((string) (ShopSettings::current()->qz_key_tag_orientation ?? ''));
        $allowed = ['auto', 'portrait', 'landscape'];

        if (in_array($value, $allowed, true)) {
            return $value;
        }

        $configured = trim((string) config('printing.key_tag_qz_orientation', 'portrait'));

        return in_array($configured, $allowed, true) ? $configured : 'portrait';
    }

    public static function oilStickerQzOrientation(): string
    {
        return self::keyTagQzOrientation();
    }

    public static function qlKeyTagScaleContent(): bool
    {
        return false;
    }

    public static function qlOilStickerScaleContent(): bool
    {
        return false;
    }

    /**
     * @return 'last6'|'last8'|'full'
     */
    public static function keyTagVinDisplayMode(): string
    {
        $value = trim((string) (ShopSettings::current()->qz_key_tag_vin_display ?? 'last6'));
        $allowed = ['last6', 'last8', 'full'];

        return in_array($value, $allowed, true) ? $value : 'last6';
    }

    public static function shopKeyTagRasterDpiOverride(): ?int
    {
        $dpi = ShopSettings::current()->qz_raster_dpi;

        return in_array((int) $dpi, [203, 300], true) ? (int) $dpi : null;
    }

    public static function oilChangeNextDueMonths(): int
    {
        return max(1, (int) (ShopSettings::current()->oil_change_sticker_next_due_months ?? 6));
    }

    public static function oilChangeIntervalMiles(): int
    {
        $saved = (int) (ShopSettings::current()->oil_change_interval_miles ?? 0);

        return $saved > 0
            ? $saved
            : (int) config('vehicle_maintenance_intervals.intervals.oil.interval', 5000);
    }
}
