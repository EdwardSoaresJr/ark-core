<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Support\Mail\ShopMailBranding;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

test('customer emails use shop branded subject from name and shop footer layout', function () {
    Storage::fake('public');
    Storage::disk('public')->put('shop-logos/logo.png', 'logo');

    ShopSettings::current()->update([
        'shop_name' => 'Demo Auto Repair',
        'logo_path' => 'shop-logos/logo.png',
    ]);

    expect(ShopMailBranding::shopName())->toBe('Demo Auto Repair')
        ->and(ShopMailBranding::from()->name)->toBe('Demo Auto Repair')
        ->and(ShopMailBranding::logoUrl())->toContain('/storage/shop-logos/logo.png');
});

test('shop mail branding never falls back to Laravel', function () {
    config(['app.name' => 'Laravel']);

    ShopSettings::current()->update([
        'shop_name' => null,
    ]);
    ShopSettings::forgetCurrent();

    expect(ShopMailBranding::shopName())->toBe('Demo Auto Repair')
        ->and(ShopMailBranding::from()->name)->toBe('Demo Auto Repair')
        ->and(ShopMailBranding::shopName())->not->toBe('Laravel');
});
