<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Website\Catalog\PublicWebsiteCatalog;
use App\Ark\Website\PublishWebsiteCatalog;

function publishLegacySurface(): void
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
    ]);
    ShopSettings::forgetCurrent();

    app(PublishWebsiteCatalog::class)->publish('lugsnplugs.arksms.com', PublicWebsiteCatalog::document(), true);
}

test('llms txt describes the published site on the canonical host', function (): void {
    publishLegacySurface();

    $response = $this->get('http://lugsnplugs.arksms.com/llms.txt');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertHeader('X-ARK-Website', 'core')
        ->assertSee('LugsNPlugs Automotive')
        ->assertSee('Canonical: https://lugsnplugs.com/')
        ->assertSee('3445 Chelton Loop N')
        ->assertSee('Phone:')
        ->assertSee('hello@lugsnplugs.com')
        ->assertSee('https://lugsnplugs.com/book')
        ->assertSee('https://lugsnplugs.com/common-problems/p0420')
        ->assertSee('24 months / 24,000 miles')
        ->assertSee('12 months / 12,000 miles')
        ->assertDontSee('/app/')
        ->assertDontSee('/portal/')
        ->assertDontSee('lugsnplugs.arksms.com');

    $this->get('http://lugsnplugs.com/sitemap.xml')
        ->assertOk()
        ->assertSee('https://lugsnplugs.com/llms.txt', false);
});

test('useful legacy urls redirect and homepage dumps do not', function (): void {
    publishLegacySurface();

    $this->get('http://lugsnplugs.com/appointment?concern=Brakes')
        ->assertStatus(301)
        ->assertHeader('Location', 'https://lugsnplugs.com/book?concern=Brakes');

    $this->get('http://lugsnplugs.com/blog/flashing-check-engine-light-what-it-means')
        ->assertStatus(301)
        ->assertHeader('Location', 'https://lugsnplugs.com/common-problems/check-engine-light');

    $this->get('http://lugsnplugs.com/blog/some-unmatched-shop-note')
        ->assertStatus(301)
        ->assertHeader('Location', 'https://lugsnplugs.com/common-problems');

    $this->get('http://lugsnplugs.com/no-crank-no-start')
        ->assertStatus(301)
        ->assertHeader('Location', 'https://lugsnplugs.com/common-problems/car-wont-start');

    $this->get('http://lugsnplugs.com/common-problems/coolant-loss')
        ->assertStatus(301)
        ->assertHeader('Location', 'https://lugsnplugs.com/common-problems/engine-overheating');

    $this->get('http://lugsnplugs.com/tag/jeep')
        ->assertStatus(301)
        ->assertHeader('Location', 'https://lugsnplugs.com/common-problems/jeep-overheating');

    $this->get('http://lugsnplugs.com/about')
        ->assertOk()
        ->assertSee('A repair shop built around doing it right');
    $this->get('http://lugsnplugs.com/services/oil-change')->assertNotFound();
    $this->get('http://lugsnplugs.com/posts/hello')->assertNotFound();
    $this->get('http://lugsnplugs.com/tag/random')->assertNotFound();
    $this->get('http://lugsnplugs.com/quote')->assertNotFound();
});
