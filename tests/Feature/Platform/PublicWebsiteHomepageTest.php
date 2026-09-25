<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Website\Catalog\PublicWebsiteCatalog;
use App\Ark\Website\PublishWebsiteCatalog;

function publishHomepageSurface(array $documentOverrides = []): void
{
    config([
        'website.custom_domains' => [[
            'domain' => 'lugsnplugs.com',
            'site_host' => 'lugsnplugs.arksms.com',
            'preferred' => true,
        ]],
    ]);

    ShopSettings::current()->update([
        'shop_name' => 'LugsNPlugs Automotive',
        'phone' => '7194136227',
        'email' => 'hello@lugsnplugs.com',
        'address_line_1' => '3445 Chelton Loop N',
        'address_line_2' => 'D',
        'city' => 'Colorado Springs',
        'state' => 'CO',
        'postal_code' => '80909',
        'google_reviews_url' => 'https://g.page/r/Cf8J_e1XmXpMEAE/review',
    ]);
    ShopSettings::forgetCurrent();

    $document = PublicWebsiteCatalog::document();
    $document['shop_photos'] = [
        ['alt' => 'Inside the LugsNPlugs service bays', 'path' => 'public-surface-photos/bay.jpg'],
        ['alt' => 'The LugsNPlugs Team', 'path' => 'public-surface-photos/team.webp'],
        ['alt' => 'Technician pressure testing a vehicle cooling system', 'path' => 'public-surface-photos/pressure.jpg'],
        ['alt' => 'Technician verifying findings before advising', 'path' => 'public-surface-photos/findings.jpg'],
    ];
    $document['trust_signals'] = [
        'financing_available' => true,
        'wisetack_url' => 'https://wisetack.us/example',
        'synchrony_url' => 'https://www.synchrony.com/example',
    ];
    $document = array_replace_recursive($document, $documentOverrides);

    app(PublishWebsiteCatalog::class)->publish('lugsnplugs.arksms.com', $document, true);
}

test('homepage tells the shop story with the published photos', function (): void {
    publishHomepageSurface();

    $home = $this->get('http://lugsnplugs.com/');

    $home->assertOk()
        ->assertSee('Accurate Diagnostics. Honest Repairs.')
        ->assertSee('We find the problem before we sell the repair.')
        ->assertSee('Request an appointment')
        ->assertDontSee('>Book an appointment<', false)
        ->assertSee('Before we recommend replacing parts, we test the vehicle against how that system is supposed to work.', false)
        ->assertSee('public-surface-photos/bay.jpg', false)
        ->assertSee('public-surface-photos/team.webp', false)
        ->assertSee('public-surface-photos/pressure.jpg', false)
        ->assertSee('public-surface-photos/findings.jpg', false)
        ->assertSee('The LugsNPlugs Automotive team.')
        ->assertDontSee('Edward and Molly Soares with the LugsNPlugs team')
        ->assertSee('Pressure testing the cooling system before deciding what to replace.')
        ->assertSee('Checking the findings before the recommendation.')
        ->assertSee('Pick the closest thing.')
        ->assertSee('/common-problems/check-engine-light', false)
        ->assertSee('/common-problems/car-wont-start', false)
        ->assertSee('/common-problems/engine-overheating', false)
        ->assertSee('/common-problems/brake-noise', false)
        ->assertSee('/common-problems/wheel-bearing-noise', false)
        ->assertSee('/common-problems/suspension-noise', false)
        ->assertSee('/common-problems/ac-not-cold', false)
        ->assertSee('/common-problems/electrical-diagnostics', false)
        ->assertSee('/common-problems/misfire-under-load', false)
        ->assertDontSee('shaking or vibrating')
        ->assertSee('Expecting the worst but Edward and Caleb were great and found it only needed a proper trans service that another shop said they did but left seriously underfilled.', false)
        ->assertSee('Edward is exceptionally meticulous and methodical in his approach to vehicle repair, while also prioritizing a truly comfortable and transparent customer experience.', false)
        ->assertSee('4.9 from 56 Google reviews.')
        ->assertSee('https://g.page/r/Cf8J_e1XmXpMEAE/review', false)
        ->assertSee('24 months or 24,000 miles')
        ->assertSee('12 months or 12,000 miles')
        ->assertSee('If the job qualifies, Wisetack and Synchrony Car Care can spread the cost out.')
        ->assertSee('It does not reserve a bay.')
        ->assertSee('3445 Chelton Loop N D')
        ->assertSee('Colorado Springs, CO 80909')
        ->assertSee('href="tel:7194136227"', false)
        ->assertSee('href="sms:7194136227"', false);

    $this->get('http://lugsnplugs.com/book?concern=Something%20Else')
        ->assertOk()
        ->assertSee('value="Something Else" selected', false);
});

test('about page uses the team photo and shop facts', function (): void {
    publishHomepageSurface();

    $about = $this->get('http://lugsnplugs.com/about');

    $about->assertOk()
        ->assertSee('About LugsNPlugs', false)
        ->assertSee('A repair shop built around doing it right')
        ->assertSee('public-surface-photos/team.webp', false)
        ->assertSee('The LugsNPlugs Automotive team.')
        ->assertSee('3445 Chelton Loop N D')
        ->assertSee('Request an appointment')
        ->assertSee('https://lugsnplugs.com/about', false);

    $this->get('http://lugsnplugs.com/sitemap.xml')
        ->assertOk()
        ->assertSee('https://lugsnplugs.com/about', false);

    $this->get('http://lugsnplugs.com/llms.txt')
        ->assertOk()
        ->assertSee('https://lugsnplugs.com/about', false);
});

test('homepage omits financing names that are not offered', function (): void {
    publishHomepageSurface([
        'trust_signals' => ['financing_available' => false],
    ]);

    $this->get('http://lugsnplugs.com/')
        ->assertOk()
        ->assertDontSee('Wisetack')
        ->assertDontSee('Synchrony');
});
