<?php

use App\Ark\Growth\PublicSurface\PublicMarketingUrl;
use App\Ark\Operations\Leads\Public\PublicSurfaceSettings;
use App\Ark\Operations\Leads\Public\PublicTrustSignalsProjection;
use App\Ark\Operations\Leads\Public\SynchronyCarCareUrls;
use App\Ark\Operations\Settings\ShopSettings;

test('trust signals projection includes repairpal financing and warranty proof', function (): void {
    $signals = app(PublicTrustSignalsProjection::class)->forDisplay();

    expect($signals['proof_items'])->not->toBeEmpty()
        ->and(collect($signals['proof_items'])->pluck('label')->join(' '))->toContain('RepairPal')
        ->and(collect($signals['proof_items'])->pluck('label')->join(' '))->toContain('Financing')
        ->and(collect($signals['proof_items'])->pluck('label')->join(' '))->toContain('warranty')
        ->and(collect($signals['proof_items'])->pluck('label')->join(' '))->toContain('Family owned')
        ->and(collect($signals['proof_items'])->first()['detail'])->not->toContain('Family owned')
        ->and(collect($signals['header_pills'])->pluck('label')->join(' '))->toContain('RepairPal')
        ->and(collect($signals['hero_chips'])->pluck('label')->join(' '))->toContain('Financing')
        ->and(collect($signals['hero_chips'])->pluck('label')->join(' '))->toContain('We test first')
        ->and(collect($signals['hero_chips'])->pluck('label')->join(' '))->not->toContain('Family owned')
        ->and($signals['financing']['learn_more_url'])->toContain('/financing');

    $repairPalChip = collect($signals['hero_chips'])->firstWhere('label', 'RepairPal Certified');

    expect($repairPalChip)->not->toBeNull()
        ->and($repairPalChip['href'])->toBe(route('public.repairpal.certified'));
});

test('stored generic repairpal directory url resolves to shop listing', function (): void {
    ShopSettings::current()->update([
        'public_surface_settings' => array_merge(PublicSurfaceSettings::DEFAULTS, [
            'trust_signals' => array_merge(PublicSurfaceSettings::DEFAULTS['trust_signals'], [
                'repairpal_url' => 'https://www.repairpal.com/repair-shops',
            ]),
        ]),
    ]);

    expect(PublicSurfaceSettings::current()['trust_signals']['repairpal_url'])
        ->toBe(PublicSurfaceSettings::REPAIRPAL_LISTING_URL);
});

test('stored legacy financing urls resolve to working destinations', function (): void {
    ShopSettings::current()->update([
        'public_surface_settings' => array_merge(PublicSurfaceSettings::DEFAULTS, [
            'trust_signals' => array_merge(PublicSurfaceSettings::DEFAULTS['trust_signals'], [
                'wisetack_url' => 'https://www.wisetack.com/for-customers',
                'synchrony_url' => 'https://www.mysynchrony.com/merchants/car-care-financing.html',
            ]),
        ]),
    ]);

    expect(PublicSurfaceSettings::current()['trust_signals']['wisetack_url'])->toBeNull()
        ->and(PublicSurfaceSettings::current()['trust_signals']['synchrony_url'])
        ->toBe(SynchronyCarCareUrls::linkUrl());
});

test('shop-specific synchrony merchant url is preserved as link channel', function (): void {
    ShopSettings::current()->update([
        'public_surface_settings' => array_merge(PublicSurfaceSettings::DEFAULTS, [
            'trust_signals' => array_merge(PublicSurfaceSettings::DEFAULTS['trust_signals'], [
                'synchrony_url' => 'https://www.synchrony.com/mmc/CR000000000?sitecode=demo403',
            ]),
        ]),
    ]);

    expect(PublicSurfaceSettings::current()['trust_signals']['synchrony_url'])
        ->toBe(SynchronyCarCareUrls::linkUrl())
        ->and(PublicSurfaceSettings::current()['trust_signals']['synchrony_embed_url'])
        ->toBe(SynchronyCarCareUrls::embedUrl());
});

test('empty stored wisetack url falls back to shop prequal link', function (): void {
    ShopSettings::current()->update([
        'public_surface_settings' => array_merge(PublicSurfaceSettings::DEFAULTS, [
            'trust_signals' => array_merge(PublicSurfaceSettings::DEFAULTS['trust_signals'], [
                'wisetack_url' => '',
            ]),
        ]),
    ]);

    expect(PublicSurfaceSettings::current()['trust_signals']['wisetack_url'])
        ->toBe(PublicSurfaceSettings::WISETACK_PREQUAL_URL);
});

test('shop-specific wisetack prequal url is preserved', function (): void {
    $prequalUrl = 'https://wisetack.us/#/prequal/demo-auto-example';

    ShopSettings::current()->update([
        'public_surface_settings' => array_merge(PublicSurfaceSettings::DEFAULTS, [
            'trust_signals' => array_merge(PublicSurfaceSettings::DEFAULTS['trust_signals'], [
                'wisetack_url' => $prequalUrl,
            ]),
        ]),
    ]);

    expect(PublicSurfaceSettings::current()['trust_signals']['wisetack_url'])->toBe($prequalUrl);
});

test('public financing page renders programs and trust proof', function (): void {
    $this->get(route('public.financing'))
        ->assertOk()
        ->assertSee('Financing for unexpected repairs', false)
        ->assertSee('Wisetack', false)
        ->assertSee('Synchrony Car Care', false)
        ->assertSee(SynchronyCarCareUrls::linkUrl(), false)
        ->assertSee(SynchronyCarCareUrls::embedUrl(), false)
        ->assertSee(SynchronyCarCareUrls::APPLY_BUTTON_IMAGE, false)
        ->assertSee(PublicSurfaceSettings::WISETACK_PREQUAL_URL, false)
        ->assertSee('Prequalify with Wisetack', false)
        ->assertSee('public-wisetack-prequal-button', false)
        ->assertSee('Prequalify now', false)
        ->assertSee('Apply or prequalify with Synchrony Car Care', false)
        ->assertDontSee('https://www.wisetack.com/for-customers', false)
        ->assertDontSee('https://www.mysynchrony.com/merchants/car-care-financing.html', false)
        ->assertSee('RepairPal Certified', false)
        ->assertSee('Question about financing?', false)
        ->assertSee('Contact Us', false)
        ->assertSee(route('public.contact'), false)
        ->assertSee('public-cta--primary', false)
        ->assertSee('Ready to bring the car in?', false)
        ->assertSee('Book an Appointment', false)
        ->assertSee(route('public.book'), false)
        ->assertSee('public-cta--secondary', false)
        ->assertDontSee('Questions about financing?', false)
        ->assertDontSee('Describe your vehicle and repair concern', false)
        ->assertDontSee('Talk to a Service Advisor', false)
        ->assertDontSee('name="first_name"', false)
        ->assertDontSee('action="'.route('public.leads.store').'"', false);
});

test('public homepage surfaces booking conversion and common problems', function (): void {
    $this->get(route('public.home'))
        ->assertOk()
        ->assertSee('We find the real problem first', false)
        ->assertSee('Book an Appointment', false)
        ->assertSee('How an appointment works', false)
        ->assertSee('Request a day', false)
        ->assertSee('We inspect and test', false)
        ->assertDontSee('After you book', false)
        ->assertSee('Still having the same problem?', false)
        ->assertSee('Not sure what’s wrong?', false)
        ->assertSee('Check engine light', false)
        ->assertSee('Noise or vibration', false)
        ->assertSee(route('public.book', ['concern' => 'Check Engine Light']), false)
        ->assertSee('View all problems', false)
        ->assertSee('View Auto Repair Services', false)
        ->assertDontSee('Ready when you are', false)
        ->assertSee('What our customers say', false)
        ->assertDontSee('Auto repair in Demo City', false)
        ->assertDontSee('Warm air? It may be a leak, compressor, or electrical issue.', false)
        ->assertDontSee('Dealer-Level Diagnostics', false)
        ->assertSee('4.9 on Google', false)
        ->assertSee('24/24 shop warranty', false)
        ->assertSee('RepairPal Certified', false)
        ->assertSee('Financing', false)
        ->assertSee('Unexpected repair?', false)
        ->assertDontSee('High-intent concerns', false)
        ->assertDontSee('Talk to a Service Advisor', false)
        ->assertDontSee('Need help with your vehicle?', false)
        ->assertDontSee('shop-photo-grid', false);
});

test('sitemap includes financing page', function (): void {
    $this->get(route('sitemap.xml'))
        ->assertOk()
        ->assertSee('<loc>'.PublicMarketingUrl::baseUrl().'/financing</loc>', false);
});

test('repairpal authority pages render and link to official profile', function (): void {
    $profileUrl = PublicSurfaceSettings::REPAIRPAL_LISTING_URL;

    $this->get(route('public.repairpal'))
        ->assertOk()
        ->assertSee('RepairPal at', false)
        ->assertSee(route('public.repairpal.certified'), false)
        ->assertSee(route('public.repairpal.reviews'), false)
        ->assertSee(route('public.repairpal.warranty'), false)
        ->assertSee($profileUrl, false)
        ->assertSee('data-public-surface-repairpal-profile', false)
        ->assertSee('Talk to a service advisor', false)
        ->assertSee('customer-page-split--public', false);

    $this->get(route('public.repairpal.certified'))
        ->assertOk()
        ->assertSee('RepairPal Certified', false)
        ->assertSee('What RepairPal is', false)
        ->assertSee('How we diagnose', false)
        ->assertSee($profileUrl, false)
        ->assertSee('aria-label="Breadcrumb"', false)
        ->assertSee('>Certified</span>', false);

    $this->get(route('public.repairpal.reviews'))
        ->assertOk()
        ->assertSee('RepairPal reviews', false)
        ->assertSee('We do not republish RepairPal review text', false)
        ->assertSee($profileUrl, false);

    $this->get(route('public.repairpal.warranty'))
        ->assertOk()
        ->assertSee('RepairPal nationwide warranty', false)
        ->assertSee('12 months / 12,000 miles', false)
        ->assertSee('24 months / 24,000 miles', false)
        ->assertSee(route('public.warranty'), false)
        ->assertSee($profileUrl, false);
});

test('sitemap includes repairpal authority pages', function (): void {
    $base = PublicMarketingUrl::baseUrl();

    $this->get(route('sitemap.xml'))
        ->assertOk()
        ->assertSee('<loc>'.$base.'/repairpal</loc>', false)
        ->assertSee('<loc>'.$base.'/repairpal-certified</loc>', false)
        ->assertSee('<loc>'.$base.'/repairpal-reviews</loc>', false)
        ->assertSee('<loc>'.$base.'/repairpal-warranty</loc>', false);
});
