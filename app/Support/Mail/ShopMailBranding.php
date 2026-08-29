<?php

namespace App\Support\Mail;

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Storage;

final class ShopMailBranding
{
    public static function shopName(): string
    {
        $name = trim((string) (ShopSettings::current()->shop_name ?? ''));

        if ($name !== '') {
            return $name;
        }

        $appName = trim((string) config('app.name', ''));

        // Never expose framework defaults on customer mail.
        if ($appName !== '' && ! in_array(strtolower($appName), ['laravel', 'ark-sms', 'arksms'], true)) {
            return $appName;
        }

        return 'Demo Auto Repair';
    }

    public static function logoUrl(): ?string
    {
        $settings = ShopSettings::current();

        if (! $settings->logo_path || ! Storage::disk('public')->exists($settings->logo_path)) {
            return null;
        }

        return Storage::disk('public')->url($settings->logo_path);
    }

    public static function subject(string $purpose): string
    {
        return sprintf('%s - %s', self::shopName(), $purpose);
    }

    public static function from(): Address
    {
        return new Address(
            (string) config('mail.from.address'),
            self::shopName(),
        );
    }
}
