<?php

namespace App\Support\Branding;

final class BrandingAssetRegistry
{
    public const ASSET_ROOT = 'assets/ARK_SMS_FINAL_DROP_IN_PACK';

    /**
     * @var array<string, string>
     */
    private const LOGOS = [
        'horizontal' => 'ark_logo_horizontal.png',
        'full_white' => 'ark_logo_full_white.png',
        'transparent_light' => 'ark_logo_transparent_light.png',
        'white_with_teal' => 'ark_logo_white_with_teal.png',
        'icon_master' => 'ark_icon_master_1024.png',
    ];

    /**
     * @var array<string, string>
     */
    private const FAVICONS = [
        'ico' => 'favicon/favicon.ico',
        '16' => 'favicon/ark-16x16.png',
        '32' => 'favicon/ark-32x32.png',
        '48' => 'favicon/ark-48x48.png',
    ];

    /**
     * @var array<string, string>
     */
    private const PWA_ICONS = [
        '72' => 'pwa/ark-72x72.png',
        '96' => 'pwa/ark-96x96.png',
        '128' => 'pwa/ark-128x128.png',
        '144' => 'pwa/ark-144x144.png',
        '152' => 'pwa/ark-152x152.png',
        '192' => 'pwa/ark-192x192.png',
        '384' => 'pwa/ark-384x384.png',
        '512' => 'pwa/ark-512x512.png',
    ];

    /**
     * @var array<string, string>
     */
    private const ANDROID_ICONS = [
        '48' => 'android/ark-48x48.png',
        '72' => 'android/ark-72x72.png',
        '96' => 'android/ark-96x96.png',
        '144' => 'android/ark-144x144.png',
        '192' => 'android/ark-192x192.png',
        '512' => 'android/ark-512x512.png',
    ];

    /**
     * @var array<string, string>
     */
    private const IOS_ICONS = [
        '120' => 'ios/ark-120x120.png',
        '152' => 'ios/ark-152x152.png',
        '167' => 'ios/ark-167x167.png',
        '180' => 'ios/ark-180x180.png',
        '1024' => 'ios/ark-1024x1024.png',
    ];

    public function assetPath(string $relative): string
    {
        return self::ASSET_ROOT.'/'.ltrim($relative, '/');
    }

    public function assetUrl(string $relative): string
    {
        return asset($this->assetPath($relative));
    }

    public function logo(string $variant = 'horizontal'): string
    {
        return $this->assetUrl($this->resolve(self::LOGOS, $variant, 'horizontal'));
    }

    public function favicon(string $variant = 'ico'): string
    {
        return $this->assetUrl($this->resolve(self::FAVICONS, $variant, 'ico'));
    }

    public function loginImage(): string
    {
        return $this->logo('transparent_light');
    }

    public function sidebarIcon(): string
    {
        return $this->logo('icon_master');
    }

    public function sidebarLogo(): string
    {
        return $this->logo('transparent_light');
    }

    public function emailLogo(): string
    {
        return $this->logo('horizontal');
    }

    public function pdfPlatformLogo(): string
    {
        return $this->logo('horizontal');
    }

    public function appleTouchIcon(): string
    {
        return $this->assetUrl($this->resolve(self::IOS_ICONS, '180', '180'));
    }

    public function manifestUrl(): string
    {
        return $this->assetUrl('manifest.json');
    }

    public function pwaIcon(string $size): string
    {
        return $this->assetUrl($this->resolve(self::PWA_ICONS, $size, '192'));
    }

    public function androidIcon(string $size): string
    {
        return $this->assetUrl($this->resolve(self::ANDROID_ICONS, $size, '192'));
    }

    public function iosIcon(string $size): string
    {
        return $this->assetUrl($this->resolve(self::IOS_ICONS, $size, '180'));
    }

    public function publicDiskPath(string $relative): string
    {
        return public_path($this->assetPath($relative));
    }

    public function exists(string $relative): bool
    {
        return is_file($this->publicDiskPath($relative));
    }

    /**
     * @return list<array{key: string, relative: string, url: string, exists: bool}>
     */
    public function inventory(): array
    {
        $entries = [];

        foreach ($this->flattenInventoryMap('logo', self::LOGOS) as $entry) {
            $entries[] = $entry;
        }

        foreach ($this->flattenInventoryMap('favicon', self::FAVICONS) as $entry) {
            $entries[] = $entry;
        }

        foreach ($this->flattenInventoryMap('pwa', self::PWA_ICONS) as $entry) {
            $entries[] = $entry;
        }

        foreach ($this->flattenInventoryMap('android', self::ANDROID_ICONS) as $entry) {
            $entries[] = $entry;
        }

        foreach ($this->flattenInventoryMap('ios', self::IOS_ICONS) as $entry) {
            $entries[] = $entry;
        }

        $entries[] = [
            'key' => 'manifest',
            'relative' => 'manifest.json',
            'url' => $this->manifestUrl(),
            'exists' => $this->exists('manifest.json'),
        ];

        return $entries;
    }

    /**
     * @param  array<string, string>  $map
     * @return list<array{key: string, relative: string, url: string, exists: bool}>
     */
    private function flattenInventoryMap(string $prefix, array $map): array
    {
        $entries = [];

        foreach ($map as $key => $relative) {
            $entries[] = [
                'key' => $prefix.'.'.$key,
                'relative' => $relative,
                'url' => $this->assetUrl($relative),
                'exists' => $this->exists($relative),
            ];
        }

        return $entries;
    }

    /**
     * @param  array<string, string>  $map
     */
    private function resolve(array $map, string $key, string $fallback): string
    {
        return $map[$key] ?? $map[$fallback];
    }
}
