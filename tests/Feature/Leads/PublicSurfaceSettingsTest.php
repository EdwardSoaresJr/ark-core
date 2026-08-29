<?php

use App\Ark\Operations\Leads\Public\PublicSurfaceSettings;
use App\Ark\Operations\Leads\Public\ShopSocialProfiles;
use App\Ark\Operations\Leads\Public\SynchronyCarCareUrls;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('public surface settings default from shop settings when column empty', function (): void {
    expect(PublicSurfaceSettings::current()['google_review_count'])->toBe(24)
        ->and(PublicSurfaceSettings::current()['positioning_lede'])->toContain('real problem first')
        ->and(PublicSurfaceSettings::current()['local_tagline'])->toBe('Family owned in Demo City.')
        ->and(PublicSurfaceSettings::current()['business_hours_label'])->toBe('Mon–Fri: 9:00 AM – 6:00 PM · Sat–Sun: Closed')
        ->and(PublicSurfaceSettings::current()['customer_quote'])->toContain('no upsells')
        ->and(PublicSurfaceSettings::reviewsForDisplay())->toHaveCount(4)
        ->and(PublicSurfaceSettings::reviewsForDisplay()[1]['attribution'])->toBe('B. Customer')
        ->and(PublicSurfaceSettings::photosForDisplay())->toHaveCount(4)
        ->and(PublicSurfaceSettings::current()['composition_photos'][PublicSurfaceSettings::PHOTO_ROLE_DIAGNOSTIC_EVIDENCE])->toBe(1);
});

test('composition photo roles resolve by named gallery assignment not array position alone', function (): void {
    ShopSettings::current()->update([
        'public_surface_settings' => array_merge(PublicSurfaceSettings::DEFAULTS, [
            'shop_photos' => [
                ['path' => 'shop-photos/shop-bay.webp', 'alt' => 'Bay overview'],
                ['path' => 'shop-photos/scan-data.webp', 'alt' => 'Live scan data on the tool'],
                ['path' => 'shop-photos/lift-inspection.webp', 'alt' => 'Vehicle on the lift'],
                ['path' => 'shop-photos/verifying-findings.webp', 'alt' => 'Pressure testing the cooling system'],
            ],
            'composition_photos' => [
                PublicSurfaceSettings::PHOTO_ROLE_HERO => 0,
                PublicSurfaceSettings::PHOTO_ROLE_DIAGNOSTIC_EVIDENCE => 3,
                PublicSurfaceSettings::PHOTO_ROLE_APPOINTMENT_PROCESS => 2,
            ],
        ]),
    ]);

    $diagnostic = PublicSurfaceSettings::photoForComposition(PublicSurfaceSettings::PHOTO_ROLE_DIAGNOSTIC_EVIDENCE);

    expect($diagnostic['gallery_index'])->toBe(3)
        ->and($diagnostic['alt'])->toBe('Pressure testing the cooling system')
        ->and($diagnostic['path'])->toBe('shop-photos/verifying-findings.webp')
        ->and(PublicSurfaceSettings::photoForComposition(PublicSurfaceSettings::PHOTO_ROLE_HERO)['gallery_index'])->toBe(0)
        ->and(PublicSurfaceSettings::photoForComposition(PublicSurfaceSettings::PHOTO_ROLE_APPOINTMENT_PROCESS)['alt'])->toBe('Vehicle on the lift');
});

test('composition photo roles fall back to legacy gallery indices when unset so production imagery stays', function (): void {
    ShopSettings::current()->update([
        'public_surface_settings' => array_merge(PublicSurfaceSettings::DEFAULTS, [
            'shop_photos' => [
                ['path' => 'public-surface-photos/hero-upload.jpg', 'alt' => 'Hero bay'],
                ['path' => 'public-surface-photos/diagnostic-upload.jpg', 'alt' => 'Cooling system pressure testing'],
                ['path' => 'public-surface-photos/process-upload.jpg', 'alt' => 'Lift inspection'],
                ['path' => 'shop-photos/verifying-findings.webp', 'alt' => 'Other'],
            ],
            // Simulate production before composition_photos existed.
        ]),
    ]);

    $raw = ShopSettings::current()->public_surface_settings;
    unset($raw['composition_photos']);
    ShopSettings::current()->update(['public_surface_settings' => $raw]);

    $diagnostic = PublicSurfaceSettings::photoForComposition(PublicSurfaceSettings::PHOTO_ROLE_DIAGNOSTIC_EVIDENCE);

    expect($diagnostic['gallery_index'])->toBe(1)
        ->and($diagnostic['path'])->toBe('public-surface-photos/diagnostic-upload.jpg')
        ->and($diagnostic['alt'])->toBe('Cooling system pressure testing');
});

test('composition photo alt never invents diagnostic activity when empty', function (): void {
    ShopSettings::current()->update([
        'public_surface_settings' => array_merge(PublicSurfaceSettings::DEFAULTS, [
            'shop_photos' => [
                ['path' => 'shop-photos/shop-bay.webp', 'alt' => ''],
                ['path' => 'shop-photos/scan-data.webp', 'alt' => ''],
                ['path' => 'shop-photos/lift-inspection.webp', 'alt' => ''],
                ['path' => 'shop-photos/verifying-findings.webp', 'alt' => ''],
            ],
        ]),
    ]);

    expect(PublicSurfaceSettings::photoForComposition(PublicSurfaceSettings::PHOTO_ROLE_DIAGNOSTIC_EVIDENCE)['alt'])
        ->toBe('Shop photo from Demo Auto Repair')
        ->and(PublicSurfaceSettings::photoForComposition(PublicSurfaceSettings::PHOTO_ROLE_DIAGNOSTIC_EVIDENCE)['alt'])
        ->not->toContain('live diagnostic data');
});

test('public surface business hours label follows telephony weekly hours', function (): void {
    $flow = ShopSettings::current()->telephony_call_flow ?? ShopSettings::defaultTelephonyCallFlow();
    $flow['weekly_hours']['monday']['open'] = '08:00';
    $flow['weekly_hours']['tuesday']['open'] = '08:00';
    $flow['weekly_hours']['wednesday']['open'] = '08:00';
    $flow['weekly_hours']['thursday']['open'] = '08:00';
    $flow['weekly_hours']['friday']['open'] = '08:00';

    ShopSettings::current()->update(['telephony_call_flow' => $flow]);

    expect(PublicSurfaceSettings::current()['business_hours_label'])->toBe('Mon–Fri: 8:00 AM – 6:00 PM · Sat–Sun: Closed');
});

test('admin can update public surface settings and homepage reflects google rating', function (): void {
    ShopSettings::current()->update(['learn_training_gate_enabled' => false]);

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value))
        ->patch(route('website.manage.update'), [
            'google_rating' => '4.8',
            'google_review_count' => 57,
            'google_reviews_url' => 'https://example.com/reviews',
            'instrumentation_enabled' => '1',
            'photo_alt' => [
                'Bay',
                'Scan',
                'Lift',
                'Tech',
            ],
        ])
        ->assertRedirect(route('website.manage'));

    expect(PublicSurfaceSettings::current()['google_review_count'])->toBe(57);

    $featuredReview = \App\Ark\Operations\Leads\Public\PublicFeaturedReviewProjection::rotating(
        PublicSurfaceSettings::reviewsForDisplay(),
    );

    $this->get(route('public.home'))
        ->assertOk()
        ->assertSee('4.8 on Google', false)
        ->assertDontSee('57 Google reviews', false)
        ->assertSee('Read more reviews on Google', false)
        ->assertSee('https://example.com/reviews', false)
        ->assertSee($featuredReview['attribution'], false)
        ->assertSee('public-photo-hero', false)
        ->assertSee('public-book-band__image', false)
        ->assertDontSee('shop-photo-grid', false);

    $this->get(route('public.common-problems.index'))
        ->assertOk()
        ->assertSee('4.8 on Google', false)
        ->assertDontSee('57 Google reviews', false)
        ->assertSee('Read reviews on Google', false);

    $this->get(route('public.common-problems.show', 'check-engine-light'))
        ->assertOk()
        ->assertSee('4.8 stars on Google', false)
        ->assertDontSee('57 Google reviews', false)
        ->assertSee('Read reviews on Google', false);
});

test('guest cannot update public surface settings', function (): void {
    $this->patch(route('website.manage.update'), [
        'google_rating' => '3.0',
        'google_review_count' => 1,
        'google_reviews_url' => 'https://example.com/reviews',
    ])->assertRedirect();
});

test('admin can update financing links for the public website', function (): void {
    ShopSettings::current()->update(['learn_training_gate_enabled' => false]);

    $wisetackUrl = 'https://wisetack.us/#/prequal/demo-auto-example';
    $synchronyUrl = SynchronyCarCareUrls::linkUrl();

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value))
        ->patch(route('website.manage.update'), [
            'google_rating' => '4.9',
            'google_review_count' => 56,
            'google_reviews_url' => 'https://www.google.com/maps/search/?api=1&query=Demo+Auto+Repair',
            'wisetack_url' => $wisetackUrl,
            'synchrony_url' => $synchronyUrl,
            'photo_alt' => ['Bay', 'Scan', 'Lift', 'Tech'],
        ])
        ->assertRedirect(route('website.manage'));

    expect(PublicSurfaceSettings::current()['trust_signals']['wisetack_url'])->toBe($wisetackUrl)
        ->and(PublicSurfaceSettings::current()['trust_signals']['synchrony_url'])->toBe($synchronyUrl);

    $this->get(route('public.financing'))
        ->assertOk()
        ->assertSee($wisetackUrl, false)
        ->assertSee($synchronyUrl, false);
});

test('admin can update social profile urls for the public website', function (): void {
    ShopSettings::current()->update(['learn_training_gate_enabled' => false]);

    $facebook = 'https://www.facebook.com/demo-auto';
    $instagram = 'https://www.instagram.com/demo-auto';
    $nextdoor = 'https://nextdoor.com/pages/demo-auto-automotive';

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value))
        ->patch(route('website.manage.update'), [
            'google_rating' => '4.9',
            'google_review_count' => 56,
            'google_reviews_url' => 'https://www.google.com/maps/search/?api=1&query=Demo+Auto+Repair',
            'facebook_url' => $facebook,
            'instagram_url' => $instagram,
            'nextdoor_url' => $nextdoor,
            'photo_alt' => ['Bay', 'Scan', 'Lift', 'Tech'],
        ])
        ->assertRedirect(route('website.manage'));

    $profiles = PublicSurfaceSettings::current()['social_profiles'];

    expect($profiles['facebook_url'])->toBe($facebook)
        ->and($profiles['instagram_url'])->toBe($instagram)
        ->and($profiles['nextdoor_url'])->toBe($nextdoor)
        ->and(ShopSocialProfiles::sameAsUrls())->toBe([$facebook, $instagram, $nextdoor]);

    $this->get(route('public.home'))
        ->assertOk()
        ->assertSee('Follow us', false)
        ->assertSee($facebook, false)
        ->assertSee($instagram, false)
        ->assertSee('"@type":"Organization"', false)
        ->assertSee('"sameAs"', false)
        ->assertSee($facebook, false);

    $this->get(route('public.leads.thanks'))
        ->assertOk()
        ->assertSee('While you wait', false)
        ->assertSee($facebook, false);
});

test('shop social profiles omit empty channels from display and sameAs', function (): void {
    // Demo defaults ship without a Google Business review URL; sameAs stays empty until configured.
    expect(ShopSocialProfiles::forDisplay())
        ->toBeEmpty()
        ->and(ShopSocialProfiles::sameAsUrls())->toBe([]);
});
