<?php

use App\Support\Branding\Branding;
use App\Support\Branding\BrandingAssetRegistry;
use Illuminate\Support\Facades\Blade;

test('branding registry resolves canonical ARK-SMS asset urls', function () {
    $registry = new BrandingAssetRegistry;

    expect(Branding::loginImage())
        ->toContain('/assets/ARK_SMS_FINAL_DROP_IN_PACK/ark_logo_transparent_light.png')
        ->and(Branding::sidebarIcon())
        ->toContain('/assets/ARK_SMS_FINAL_DROP_IN_PACK/ark_icon_master_1024.png')
        ->and(Branding::sidebarLogo())
        ->toContain('/assets/ARK_SMS_FINAL_DROP_IN_PACK/ark_logo_transparent_light.png')
        ->and(Branding::emailLogo())
        ->toContain('/assets/ARK_SMS_FINAL_DROP_IN_PACK/ark_logo_horizontal.png')
        ->and(Branding::favicon('16'))
        ->toContain('/assets/ARK_SMS_FINAL_DROP_IN_PACK/favicon/ark-16x16.png')
        ->and(Branding::appleTouchIcon())
        ->toContain('/assets/ARK_SMS_FINAL_DROP_IN_PACK/ios/ark-180x180.png')
        ->and(Branding::manifest())
        ->toContain('/assets/ARK_SMS_FINAL_DROP_IN_PACK/manifest.json');

    foreach ($registry->inventory() as $entry) {
        expect($entry['exists'])->toBeTrue("Missing branding asset: {$entry['relative']}");
    }
});

test('guest and operations layouts reference branding authority', function () {
    $guestHtml = view('layouts.guest', ['slot' => ''])->render();

    expect($guestHtml)
        ->toContain(Branding::loginImage())
        ->toContain(Branding::favicon('ico'));

    // Operations shell needs Vite build assets; skip when the manifest is absent locally.
    if (! file_exists(public_path('build/manifest.json'))) {
        return;
    }

    $operationsHtml = Blade::render('<x-operations.app>test</x-operations.app>');

    expect($operationsHtml)
        ->toContain(Branding::sidebarLogo())
        ->toContain('ops-rail-brand-logo')
        ->not->toContain('ops-rail-version">v2</span>')
        ->toContain('<title>'.Branding::tabTitle().'</title>')
        ->toContain(Branding::favicon('32'));
});
