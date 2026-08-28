<?php

use App\Ark\Growth\PublicSurface\PublicMarketingUrl;
use App\Ark\Operations\Leads\Public\PublicLegacyRedirect;
use App\Ark\Operations\Leads\Public\PublicSurfaceSettings;
use App\Ark\Operations\Leads\Public\SynchronyCarCareUrls;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Surfaces\SurfaceRouting;

beforeEach(function (): void {
    ShopSettings::current()->update(['learn_training_gate_enabled' => false]);
});

test('public homepage includes technical seo tags and auto repair json-ld', function (): void {
    ShopSettings::current()->update([
        'address_line_1' => '100 Main Street',
        'address_line_2' => 'Unit D',
        'city' => 'Colorado Springs',
        'state' => 'CO',
        'postal_code' => '80909',
        'phone' => '7194136227',
    ]);

    $response = $this->get(route('public.home'))
        ->assertOk();

    $response
        ->assertSee('<meta name="color-scheme" content="light dark">', false)
        ->assertSee('data-public-surface-theme-toggle', false)
        ->assertSee('ark-customer-theme', false)
        ->assertSee('<title>Auto Repair Colorado Springs | Verified Diagnostics</title>', false)
        ->assertSee('Colorado Springs auto repair with testing and live data', false)
        ->assertSee('<meta name="description"', false)
        ->assertSee('<link rel="canonical"', false)
        ->assertSee('<meta property="og:title"', false)
        ->assertSee('<meta property="og:description"', false)
        ->assertSee('<meta property="og:url"', false)
        ->assertSee('"@type":"AutoRepair"', false)
        ->assertSee('"@type":"Organization"', false)
        ->assertSee('"streetAddress":"100 Main Street Suite A"', false)
        ->assertSee('"telephone":"+17194136227"', false)
        ->assertDontSee('aggregateRating', false)
        ->assertDontSee('"@type":"Review"', false)
        ->assertDontSee('ratingValue', false);
});

test('public homepage json-ld keeps canonical nap when shop street and phone are empty', function (): void {
    ShopSettings::current()->update([
        'shop_name' => '',
        'address_line_1' => '',
        'address_line_2' => '',
        'city' => '',
        'state' => '',
        'postal_code' => '',
        'phone' => '',
    ]);

    $this->get(route('public.home'))
        ->assertOk()
        ->assertSee('Demo Auto Repair', false)
        ->assertSee('"streetAddress":"100 Main Street Suite A"', false)
        ->assertSee('"telephone":"+17194136227"', false);
});

test('llms.txt is available on the public marketing host', function (): void {
    ShopSettings::current()->update([
        'shop_name' => 'Demo Auto Repair',
        'address_line_1' => '100 Main Street',
        'address_line_2' => 'Unit D',
        'city' => 'Colorado Springs',
        'state' => 'CO',
        'postal_code' => '80909',
        'phone' => '7194136227',
        'email' => 'hello@demo-auto.test',
    ]);

    $this->get(route('llms.txt'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Demo Auto Repair', false)
        ->assertSee('100 Main Street Suite A', false)
        ->assertSee('(719) 413-6227', false)
        ->assertSee('https://demo-auto.test/common-problems', false)
        ->assertSee('testing and live data', false)
        ->assertSee('24 month / 24,000 mile shop warranty', false)
        ->assertSee('RepairPal Certified warranty is 12 months / 12,000 miles', false);
});

test('thanks page is noindex but still canonicalized', function (): void {
    $this->get(route('public.leads.thanks'))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, follow">', false)
        ->assertSee('/leads/thanks', false);
});

test('sitemap lists public homepage', function (): void {
    $this->get(route('sitemap.xml'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>'.PublicMarketingUrl::baseUrl().'/</loc>', false);
});

test('robots on public marketing host references sitemap', function (): void {
    $this->get(route('robots.txt'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Sitemap: '.PublicMarketingUrl::baseUrl().'/sitemap.xml', false)
        ->assertSee('Allow: /', false);
});

test('legacy botble service urls redirect to homepage', function (): void {
    $this->get('/services/ac-repair')
        ->assertRedirect('/');
});

test('legacy botble blog and concern urls redirect to common problems', function (): void {
    $this->get('/blog/ac-not-cold')
        ->assertRedirect('/common-problems/ac-not-cold');

    $this->get('/blog/why-your-car-shakes-when-the-check-engine-light-flashes')
        ->assertRedirect('/common-problems/check-engine-light');

    $this->get('/no-crank-no-start')
        ->assertRedirect('/common-problems/car-wont-start');

    $this->get('/engine-overheating')
        ->assertRedirect('/common-problems/engine-overheating');

    $this->get('/common-problems/no-crank-no-start')
        ->assertRedirect('/common-problems/car-wont-start');
});

test('unknown legacy path returns 404', function (): void {
    $this->get('/winter-car-care-guide')->assertNotFound();
});

test('unmatched legacy blog posts redirect to common problems index', function (): void {
    $this->get('/blog/winter-car-care-guide')
        ->assertRedirect('/common-problems');

    $this->get('/blog')
        ->assertRedirect('/common-problems');
});

test('legacy blog keyword patterns redirect to matching concern pages', function (): void {
    $this->get('/blog/p0171-system-too-lean-explained')
        ->assertRedirect('/common-problems/p0171');

    $this->get('/blog/subaru-overheating-at-highway-speeds')
        ->assertRedirect('/common-problems/subaru-overheating');

    $this->get('/blog/front-suspension-clunk-over-bumps')
        ->assertRedirect('/common-problems/suspension-noise');
});

test('public legacy redirect resolver maps gsc top botble urls', function (): void {
    expect(PublicLegacyRedirect::resolve('blog/honda-ac-compressor-clutch-what-we-check-first'))
        ->toBe('/common-problems/ac-not-cold');

    expect(PublicLegacyRedirect::resolve('blog/what-transmission-slip-feels-like-vs-engine-misfire'))
        ->toBe('/common-problems/transmission-slipping');

    expect(PublicLegacyRedirect::resolve('symptoms'))
        ->toBe('/common-problems');

    expect(PublicLegacyRedirect::resolve('auto-repair/colorado'))
        ->toBe('/common-problems/auto-repair-colorado-springs');

    expect(PublicLegacyRedirect::resolve('brake-repair'))
        ->toBe('/common-problems/brake-repair-colorado-springs');
});

test('legacy service urls redirect to common problem pages', function (): void {
    $this->get('/auto-repair/colorado')
        ->assertRedirect('/common-problems/auto-repair-colorado-springs');

    $this->get('/brake-repair')
        ->assertRedirect('/common-problems/brake-repair-colorado-springs');
});

test('book form prefills concern from query string', function (): void {
    $this->get('/book?concern=AC+not+cold')
        ->assertOk()
        ->assertSee('AC not cold', false);
});

test('public host robots and sitemap work when surface domains enabled', function (): void {
    $_ENV['SURFACE_DOMAINS_ENABLED'] = 'true';
    $_ENV['APP_DOMAIN'] = 'app.demo-auto.test';
    $_ENV['PORTAL_DOMAIN'] = 'portal.demo-auto.test';
    $_ENV['PUBLIC_DOMAIN'] = 'demo-auto.test';
    $_ENV['APP_URL'] = 'https://app.demo-auto.test';

    putenv('SURFACE_DOMAINS_ENABLED=true');
    putenv('APP_DOMAIN=app.demo-auto.test');
    putenv('PORTAL_DOMAIN=portal.demo-auto.test');
    putenv('PUBLIC_DOMAIN=demo-auto.test');
    putenv('APP_URL=https://app.demo-auto.test');

    $this->refreshApplication();

    $this->get('http://demo-auto.test/sitemap.xml')
        ->assertOk()
        ->assertSee('<loc>https://demo-auto.test/</loc>', false);

    $this->get('http://demo-auto.test/robots.txt')
        ->assertOk()
        ->assertSee('Sitemap: https://demo-auto.test/sitemap.xml', false);

    $home = rtrim(PublicMarketingUrl::baseUrl(), '/');

    $appointment = $this->get('http://demo-auto.test/appointment');
    $appointment->assertStatus(301);
    expect($appointment->headers->get('Location'))->toStartWith($home);

    $appointments = $this->get('http://demo-auto.test/appointments');
    $appointments->assertStatus(301);
    expect($appointments->headers->get('Location'))->toStartWith($home);

    putenv('SURFACE_DOMAINS_ENABLED=false');
    putenv('APP_URL=http://localhost');
    putenv('APP_DOMAIN=localhost');
    putenv('PORTAL_DOMAIN=');
    putenv('PUBLIC_DOMAIN=');
    unset(
        $_ENV['SURFACE_DOMAINS_ENABLED'],
        $_ENV['APP_DOMAIN'],
        $_ENV['PORTAL_DOMAIN'],
        $_ENV['PUBLIC_DOMAIN'],
        $_ENV['APP_URL'],
    );

    $this->refreshApplication();
});

test('public homepage shows photo hero trust and booking conversion', function (): void {
    $this->get(route('public.home'))
        ->assertOk()
        ->assertSee('public-photo-hero', false)
        ->assertSee('4.9 on Google', false)
        ->assertSee('24/24 shop warranty', false)
        ->assertSee('RepairPal Certified', false)
        ->assertSee('Accurate Diagnostics', false)
        ->assertSee('Honest Repairs', false)
        ->assertSee('Book an Appointment', false)
        ->assertSee(route('public.book'), false);
});

test('opens in new tab only for off-site http links', function (): void {
    $financingUrl = PublicMarketingUrl::absolute('/financing');

    expect(PublicMarketingUrl::opensInNewTab($financingUrl))->toBeFalse()
        ->and(PublicMarketingUrl::opensInNewTab(PublicMarketingUrl::absolute('/warranty')))->toBeFalse()
        ->and(PublicMarketingUrl::opensInNewTab(PublicMarketingUrl::absolute('/repairpal-certified')))->toBeFalse()
        ->and(PublicMarketingUrl::opensInNewTab('/financing'))->toBeFalse()
        ->and(PublicMarketingUrl::opensInNewTab('https://www.repairpal.com/repair-shops'))->toBeTrue()
        ->and(PublicMarketingUrl::opensInNewTab('https://www.google.com/maps'))->toBeTrue();
});

test('internal financing warranty and repairpal trust links stay in same tab', function (): void {
    $financingUrl = route('public.financing');
    $warrantyUrl = route('public.warranty');
    $repairPalCertifiedUrl = route('public.repairpal.certified');

    $this->get(route('public.home'))
        ->assertOk()
        ->assertDontSee('href="'.$financingUrl.'" target="_blank"', false)
        ->assertDontSee('href="'.$warrantyUrl.'" target="_blank"', false)
        ->assertDontSee('href="'.$repairPalCertifiedUrl.'" target="_blank"', false)
        ->assertSee($repairPalCertifiedUrl, false);
});

test('public footer reinforces trust with contact navigation and social connect', function (): void {
    $this->get(route('public.home'))
        ->assertOk()
        ->assertSee('customer-footer', false)
        ->assertDontSee('Explore', false)
        ->assertDontSee('Customer Portal', false)
        ->assertDontSee('Why customers choose us', false)
        ->assertSee('Helpful links', false)
        ->assertSee('Follow us', false)
        ->assertDontSee('Still having trouble finding the problem?', false)
        ->assertDontSee('replace parts because a computer guessed', false)
        ->assertDontSee('We verify the problem before recommending repair', false)
        ->assertSee('Diagnostics', false)
        ->assertSee('Common Problems', false)
        ->assertSee('Financing', false)
        ->assertSee('Warranty', false)
        ->assertSee('RepairPal', false)
        ->assertSee('Directions', false)
        ->assertDontSee('4.9 Google rating', false)
        ->assertDontSee('Family owned in Colorado Springs.', false)
        ->assertSee('Privacy', false)
        ->assertSee('Terms', false)
        ->assertSee(route('public.financing'), false)
        ->assertSee(route('public.warranty'), false)
        ->assertSee(route('public.repairpal.certified'), false)
        ->assertSee(route('public.privacy'), false)
        ->assertSee(route('public.terms'), false)
        ->assertSee(route('public.common-problems.show', 'car-diagnostics-colorado-springs'), false);
});

test('financing is visible across public pages below the main content', function (): void {
    $financingUrl = route('public.financing');
    $wisetackUrl = PublicSurfaceSettings::WISETACK_PREQUAL_URL;

    $this->get(route('public.home'))
        ->assertOk()
        ->assertSee('customer-header__nav-link', false)
        ->assertSee($financingUrl, false)
        ->assertDontSee('public-site-financing-callout', false)
        ->assertDontSee('public-financing-note--featured', false)
        ->assertSee('public-financing-inline-panel', false)
        ->assertSee('Unexpected repair?', false)
        ->assertSee('public-wisetack-prequal-button', false)
        ->assertSee('Prequalify now', false)
        ->assertSee(SynchronyCarCareUrls::APPLY_BUTTON_IMAGE, false)
        ->assertSee('Financing details →', false)
        ->assertSee($wisetackUrl, false)
        ->assertSee(SynchronyCarCareUrls::embedUrl(), false)
        ->assertSee('public-photo-hero__trust', false);

    $this->get(route('public.common-problems.index'))
        ->assertOk()
        ->assertDontSee('public-site-financing-callout', false)
        ->assertSee('public-financing-inline-panel', false)
        ->assertSee('public-wisetack-prequal-button', false)
        ->assertSee(SynchronyCarCareUrls::APPLY_BUTTON_IMAGE, false)
        ->assertSee('Financing details →', false);

    $this->get(route('public.common-problems.show', 'engine-overheating'))
        ->assertOk()
        ->assertDontSee('public-site-financing-callout', false)
        ->assertSee('public-financing-inline-panel', false)
        ->assertSee('public-wisetack-prequal-button', false);

    $this->get(route('public.warranty'))
        ->assertOk()
        ->assertDontSee('public-site-financing-callout', false)
        ->assertSee('public-financing-inline-panel', false)
        ->assertSee('public-wisetack-prequal-button', false)
        ->assertSee('Financing details →', false);

    $this->get(route('public.financing'))
        ->assertOk()
        ->assertDontSee('public-financing-inline-panel', false)
        ->assertDontSee('public-site-financing-callout', false)
        ->assertSee('>Financing</span>', false)
        ->assertSee('aria-label="Breadcrumb"', false);
});

test('public homepage prioritizes booking over sticky lead rail', function (): void {
    $this->get(route('public.home'))
        ->assertOk()
        ->assertSee('public-book-band', false)
        ->assertSee('Book an Appointment', false)
        ->assertSee('confirm the time', false)
        ->assertDontSee('customer-page-split--public', false)
        ->assertDontSee('customer-page-split__rail--sticky', false);
});

test('legacy privacy and terms urls redirect to new pages', function (): void {
    $this->get('/privacy-policy')
        ->assertRedirect('/privacy');

    $this->get('/terms-of-service')
        ->assertRedirect('/terms');
});

test('warranty privacy and terms pages render', function (): void {
    $this->get(route('public.warranty'))
        ->assertOk()
        ->assertSee('24 month / 24,000 mile', false);

    $this->get(route('public.privacy'))
        ->assertOk()
        ->assertSee('Privacy policy', false);

    $this->get(route('public.terms'))
        ->assertOk()
        ->assertSee('Terms of use', false);
});

test('public homepage omits google ads tag when not configured', function (): void {
    config()->set('growth.integrations.google_ads.tag_id', null);

    $this->get(route('public.home'))
        ->assertOk()
        ->assertDontSee('googletagmanager.com/gtag/js', false)
        ->assertDontSee('AW-', false);
});

test('public homepage includes google ads tag when configured', function (): void {
    config()->set('growth.integrations.google_ads.tag_id', 'AW-17485686048');
    config()->set('growth.integrations.google_ads.ga4_measurement_id', 'G-HR9MP6ENEP');

    $this->get(route('public.home'))
        ->assertOk()
        ->assertSee('https://www.googletagmanager.com/gtag/js?id=AW-17485686048', false)
        ->assertSee("gtag('config', \"AW-17485686048\")", false)
        ->assertSee("gtag('config', \"G-HR9MP6ENEP\")", false);
});
