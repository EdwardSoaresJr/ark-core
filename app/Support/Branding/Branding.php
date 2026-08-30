<?php

namespace App\Support\Branding;

final class Branding
{
    private static ?BrandingAssetRegistry $registry = null;

    public static function registry(): BrandingAssetRegistry
    {
        return self::$registry ??= new BrandingAssetRegistry;
    }

    public static function url(string $relative): string
    {
        return self::registry()->assetUrl($relative);
    }

    public static function logo(string $variant = 'horizontal'): string
    {
        return self::registry()->logo($variant);
    }

    public static function favicon(string $variant = 'ico'): string
    {
        return self::registry()->favicon($variant);
    }

    public static function loginImage(): string
    {
        return self::registry()->loginImage();
    }

    public static function sidebarIcon(): string
    {
        return self::registry()->sidebarIcon();
    }

    public static function sidebarLogo(): string
    {
        return self::registry()->sidebarLogo();
    }

    public static function emailLogo(): string
    {
        return self::registry()->emailLogo();
    }

    public static function pdfPlatformLogo(): string
    {
        return self::registry()->pdfPlatformLogo();
    }

    public static function appleTouchIcon(): string
    {
        return self::registry()->appleTouchIcon();
    }

    public static function manifest(): string
    {
        return self::registry()->manifestUrl();
    }

    public static function pwaIcon(string $size): string
    {
        return self::registry()->pwaIcon($size);
    }

    public static function tabTitle(): string
    {
        return 'ARK-SMS';
    }

    public static function learnName(): string
    {
        return 'ARKademy';
    }
}
