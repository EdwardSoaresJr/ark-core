<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Mail\PortalAccessCodeMail;
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

    expect(ShopMailBranding::subject('Access Code'))->toBe('Demo Auto Repair - Access Code')
        ->and(ShopMailBranding::from()->name)->toBe('Demo Auto Repair')
        ->and(ShopMailBranding::logoUrl())->toContain('/storage/shop-logos/logo.png');

    Mail::fake();

    Mail::to('customer@example.test')->send(new PortalAccessCodeMail(
        shopName: 'Demo Auto Repair',
        plainCode: '123456',
        customerFirstName: 'Edward',
    ));

    Mail::assertSent(PortalAccessCodeMail::class, function (PortalAccessCodeMail $mail): bool {
        $from = $mail->envelope()->from;

        return $mail->hasTo('customer@example.test')
            && $mail->envelope()->subject === 'Demo Auto Repair - Access Code'
            && $from instanceof \Illuminate\Mail\Mailables\Address
            && $from->name === 'Demo Auto Repair';
    });
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
