<?php

use App\Ark\Platform\CoreApplicationOrigin;

it('uses the operations host even when a website domain is configured', function (): void {
    config()->set([
        'surfaces.enabled' => true,
        'surfaces.app' => 'lugsnplugs.arksms.com',
        'surfaces.portal' => 'portal.lugsnplugs.com',
        'surfaces.public' => 'lugsnplugs.com',
        'shop.base_url' => 'https://lugsnplugs.arksms.com',
    ]);

    expect(CoreApplicationOrigin::host())->toBe('lugsnplugs.arksms.com')
        ->and(CoreApplicationOrigin::origin())->toBe('https://lugsnplugs.arksms.com')
        ->and(CoreApplicationOrigin::url('go/abc'))->toBe('https://lugsnplugs.arksms.com/go/abc');
});

it('uses the configured diy origin instead of the website', function (): void {
    config()->set([
        'surfaces.enabled' => true,
        'surfaces.app' => 'ark.joesauto.com',
        'surfaces.portal' => 'portal.joesauto.com',
        'surfaces.public' => 'www.joesauto.com',
        'shop.base_url' => 'https://ark.joesauto.com',
    ]);

    expect(CoreApplicationOrigin::host())->toBe('ark.joesauto.com')
        ->and(CoreApplicationOrigin::origin())->toBe('https://ark.joesauto.com');
});
