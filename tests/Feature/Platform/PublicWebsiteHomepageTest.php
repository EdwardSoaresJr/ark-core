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

test('site foundation exposes services, problems, and an appointment request', function (): void {
    publishHomepageSurface([
        'shop_services' => [
            'Auto Repair Colorado Springs',
            'Mechanic Colorado Springs',
            'Car Diagnostics Colorado Springs',
            'Brake Repair Colorado Springs',
            'Tune Up Colorado Springs',
            'Audi Repair Colorado Springs',
        ],
    ]);

    $home = $this->get('http://lugsnplugs.com/');

    $home->assertOk()
        ->assertSee('customer-header__brand', false)
        ->assertSee('href="https://lugsnplugs.com" class="customer-header__brand"', false)
        ->assertSee('Services')
        ->assertSee('Problems')
        ->assertSee('Warranty')
        ->assertSee('Financing')
        ->assertSee('Contact')
        ->assertSee('Request an appointment')
        ->assertSee('Sign In')
        ->assertSee('customer-header__nav--mobile', false)
        ->assertSee('customer-header__menu-toggle', false)
        ->assertSee('href="https://lugsnplugs.com/services"', false)
        ->assertDontSee('Demo City');

    $this->get('http://lugsnplugs.com/services')
        ->assertOk()
        ->assertSee('Diagnostics')
        ->assertSee('Brakes')
        ->assertSee('Maintenance')
        ->assertSee('We test the vehicle against how that system is supposed to work', false)
        ->assertSee('/common-problems/check-engine-light', false)
        ->assertSee('/common-problems/electrical-diagnostics', false)
        ->assertDontSee('Auto Repair Colorado Springs')
        ->assertSee('https://lugsnplugs.com/services', false)
        ->assertSee('href="https://lugsnplugs.com" class="text-[#0099cc] no-underline hover:text-[#0088b8]">Home', false)
        ->assertDontSee('href="https://lugsnplugs.arksms.com" class="customer-header__brand"', false)
        ->assertDontSee('href="https://lugsnplugs.arksms.com" class="text-[#0099cc]', false);

    $hub = $this->get('http://lugsnplugs.com/common-problems');
    $hub->assertOk()
        ->assertSee('Problems and symptoms')
        ->assertSee('Diagnostic codes')
        ->assertSee('Check Engine Light')
        ->assertSee('P0300 Check Engine Code')
        ->assertDontSee('Auto Repair Colorado Springs')
        ->assertDontSee('Electrical System Diagnostics')
        ->assertSee('/services', false);

    $this->get('http://lugsnplugs.com/common-problems/check-engine-light')
        ->assertOk()
        ->assertSee('Request an appointment')
        ->assertDontSee('>Book an appointment<', false)
        ->assertSee('concern=My%20check%20engine%20light%20is%20on.', false)
        ->assertSee('>Rough Idle</a>', false)
        ->assertDontSee('>rough-idle</a>', false);

    $this->get('http://lugsnplugs.com/common-problems/p0300')
        ->assertOk()
        ->assertSee('>Check Engine Light</a>', false)
        ->assertDontSee('>check-engine-light</a>', false);

    $this->get('http://lugsnplugs.com/contact')
        ->assertOk()
        ->assertSee('Request an appointment')
        ->assertSee('It does not reserve a bay.')
        ->assertDontSee('Book an appointment');

    $this->get('http://lugsnplugs.com/book')
        ->assertOk()
        ->assertSee('<title>Request an appointment', false)
        ->assertDontSee('Book an Appointment');

    $llms = $this->get('http://lugsnplugs.com/llms.txt')->assertOk()->getContent();
    $services = \Illuminate\Support\Str::between($llms, "## Services\n", "\n## ");
    expect($services)->toContain('Diagnostics: https://lugsnplugs.com/services/diagnostics')
        ->and($services)->toContain('Brakes: https://lugsnplugs.com/services/brakes')
        ->and($services)->toContain('Maintenance: https://lugsnplugs.com/services/maintenance')
        ->and($services)->toContain('Electrical: https://lugsnplugs.com/services#electrical')
        ->and($services)->not->toContain('Auto Repair Colorado Springs')
        ->and($services)->not->toContain('Mechanic Colorado Springs');

    $this->get('http://lugsnplugs.com/sitemap.xml')
        ->assertOk()
        ->assertSee('https://lugsnplugs.com/services', false);

    $this->get('http://lugsnplugs.com/services/oil-change')->assertNotFound();
});

test('diagnostics brakes and maintenance share one service page pattern', function (): void {
    publishHomepageSurface();

    $this->get('http://lugsnplugs.com/services')
        ->assertOk()
        ->assertSee('href="https://lugsnplugs.com/services/diagnostics"', false)
        ->assertSee('How a diagnosis works')
        ->assertSee('href="https://lugsnplugs.com/services/brakes"', false)
        ->assertSee('How brake repair works')
        ->assertSee('href="https://lugsnplugs.com/services/maintenance"', false)
        ->assertSee('How maintenance works')
        ->assertDontSee('Auto Repair Colorado Springs');

    $diagnostics = $this->get('http://lugsnplugs.com/services/diagnostics');
    $diagnostics->assertOk()
        ->assertSee('<h1 class="public-cp-title public-page-title">Diagnostics</h1>', false)
        ->assertSee('Complaint')
        ->assertSee('Testing')
        ->assertSee('Evidence')
        ->assertSee('Finding')
        ->assertSee('Recommendation')
        ->assertSee('Verification')
        ->assertSee('https://lugsnplugs.com/common-problems/check-engine-light', false)
        ->assertSee('https://lugsnplugs.com/common-problems/car-wont-start', false)
        ->assertSee('https://lugsnplugs.com/common-problems/electrical-diagnostics', false)
        ->assertSee('https://lugsnplugs.com/common-problems/p0300', false)
        ->assertSee('href="https://lugsnplugs.com/book?concern=I%20need%20a%20diagnosis%20before%20parts%20are%20recommended."', false)
        ->assertSee('At LugsNPlugs')
        ->assertSee('Not sure what your car needs?')
        ->assertSee('Tell us what it\'s doing. We\'ll start with the evidence.')
        ->assertSee('https://lugsnplugs.com/services/diagnostics', false)
        ->assertSee('Home')
        ->assertSee('Services')
        ->assertDontSee('Auto Repair Colorado Springs');

    $this->get('http://lugsnplugs.com/book?concern=I%20need%20a%20diagnosis%20before%20parts%20are%20recommended.')
        ->assertOk()
        ->assertSee('value="Diagnostics" selected', false)
        ->assertSee('I need a diagnosis before parts are recommended.');

    $this->get('http://lugsnplugs.com/services/brakes')
        ->assertOk()
        ->assertSee('Symptoms')
        ->assertSee('What we measure')
        ->assertSee('Repair standard')
        ->assertSee('https://lugsnplugs.com/common-problems/brake-noise', false)
        ->assertSee('https://lugsnplugs.com/common-problems/brake-fluid-service', false)
        ->assertSee('href="https://lugsnplugs.com/book?concern=My%20brakes%20need%20to%20be%20inspected."', false)
        ->assertSee('Not sure the noise is the brakes?');

    $this->get('http://lugsnplugs.com/book?concern=My%20brakes%20need%20to%20be%20inspected.')
        ->assertOk()
        ->assertSee('value="Brakes" selected', false)
        ->assertSee('My brakes need to be inspected.');

    $this->get('http://lugsnplugs.com/services/maintenance')
        ->assertOk()
        ->assertSee('Fluids')
        ->assertSee('Scheduled maintenance')
        ->assertSee('Inspection')
        ->assertSee('Tune-up')
        ->assertSee('https://lugsnplugs.com/common-problems/car-fluid-service', false)
        ->assertSee('https://lugsnplugs.com/common-problems/oil-leak', false)
        ->assertSee('href="https://lugsnplugs.com/book?concern=The%20car%20is%20due%20for%20maintenance."', false)
        ->assertSee('Not sure what service is due?')
        ->assertDontSee('30,000')
        ->assertDontSee('Tune Up Colorado Springs');

    $this->get('http://lugsnplugs.com/book?concern=The%20car%20is%20due%20for%20maintenance.')
        ->assertOk()
        ->assertSee('value="Maintenance" selected', false)
        ->assertSee('The car is due for maintenance.');

    $this->get('http://lugsnplugs.com/sitemap.xml')
        ->assertOk()
        ->assertSee('https://lugsnplugs.com/services/diagnostics', false)
        ->assertSee('https://lugsnplugs.com/services/brakes', false)
        ->assertSee('https://lugsnplugs.com/services/maintenance', false);
});

test('complaint and code pages keep their own presentations', function (): void {
    publishHomepageSurface();

    $checkEngine = $this->get('http://lugsnplugs.com/common-problems/check-engine-light');
    $checkEngine->assertOk()
        ->assertSee('Still dealing with this problem?')
        ->assertSee('Tell us what the vehicle is doing.')
        ->assertSee('Related service')
        ->assertSee('Related problems and codes')
        ->assertSee('href="https://lugsnplugs.com/services/diagnostics"', false)
        ->assertSee('See how we test a vehicle before recommending parts.')
        ->assertDontSee('Often confused with')
        ->assertDontSee('Repair overview')
        ->assertDontSee('https://lugsnplugs.com/services/brakes', false)
        ->assertDontSee('https://lugsnplugs.com/services/maintenance', false);
    $checkBody = $checkEngine->getContent();
    expect(strpos($checkBody, '>Common causes<'))->toBeLessThan(strpos($checkBody, 'Can I keep driving with the check engine light on?'));

    $this->get('http://lugsnplugs.com/common-problems/wheel-bearing-noise')
        ->assertOk()
        ->assertSee('Often confused with')
        ->assertSee('If you ignore it')
        ->assertSee('Related problems and codes')
        ->assertDontSee('Repair overview')
        ->assertDontSee('Related service')
        ->assertDontSee('/services/brakes', false);

    $this->get('http://lugsnplugs.com/common-problems/jeep-death-wobble')
        ->assertOk()
        ->assertSee('Repair overview')
        ->assertSee('Still dealing with this problem?')
        ->assertDontSee('Related service');

    $this->get('http://lugsnplugs.com/common-problems/brake-noise')
        ->assertOk()
        ->assertSee('href="https://lugsnplugs.com/services/brakes"', false)
        ->assertDontSee('href="https://lugsnplugs.com/services/diagnostics"', false);

    $this->get('http://lugsnplugs.com/common-problems/abs-light')
        ->assertOk()
        ->assertSee('href="https://lugsnplugs.com/services/brakes"', false)
        ->assertDontSee('Related problems and codes');

    $this->get('http://lugsnplugs.com/common-problems/oil-leak')
        ->assertOk()
        ->assertSee('Still dealing with this problem?')
        ->assertDontSee('Related service')
        ->assertDontSee('Related problems and codes');

    $this->get('http://lugsnplugs.com/common-problems/engine-overheating')
        ->assertDontSee('Related service')
        ->assertDontSee('/services/diagnostics', false);

    $p0300 = $this->get('http://lugsnplugs.com/common-problems/p0300');
    $p0300->assertOk()
        ->assertSee('Have this code on your vehicle?')
        ->assertSee('A code gives us a place to start.')
        ->assertSee('Related symptoms and codes')
        ->assertSee('href="https://lugsnplugs.com/services/diagnostics"', false)
        ->assertSee('What does P0300 mean?');
    $p0300Body = $p0300->getContent();
    expect(strpos($p0300Body, '>Often confused with<'))->toBeLessThan(strpos($p0300Body, '>If you ignore it<'))
        ->and(strpos($p0300Body, '>If you ignore it<'))->toBeLessThan(strpos($p0300Body, '>Repair overview<'));

    $this->get('http://lugsnplugs.com/common-problems/p0420')
        ->assertOk()
        ->assertSee('Do I always need a new catalytic converter for P0420?')
        ->assertSee('Have this code on your vehicle?')
        ->assertSee('href="https://lugsnplugs.com/services/diagnostics"', false);

    $this->get('http://lugsnplugs.com/common-problems/p0302')
        ->assertOk()
        ->assertSee('What does P0302 mean?')
        ->assertSee('Have this code on your vehicle?')
        ->assertDontSee('What does P0300 mean?');

    $this->get('http://lugsnplugs.com/common-problems/electrical-diagnostics')
        ->assertOk()
        ->assertDontSee('Still dealing with this problem?')
        ->assertDontSee('Have this code on your vehicle?')
        ->assertDontSee('public-problem', false)
        ->assertSee('What happens next');

    $this->get('http://lugsnplugs.com/common-problems/tune-up-colorado-springs')
        ->assertOk()
        ->assertDontSee('Still dealing with this problem?')
        ->assertDontSee('Have this code on your vehicle?')
        ->assertSee('What is included in a tune-up today?');
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
