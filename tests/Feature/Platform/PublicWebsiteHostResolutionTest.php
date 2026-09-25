<?php

use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Platform\Website\WebsitePublication;
use App\Ark\Platform\Website\WebsiteSite;
use App\Ark\Website\PublishedWebsiteResolver;
use App\Ark\Website\PublishWebsiteCatalog;
use Illuminate\Support\Facades\Http;

function publishHostedWebsite(string $host, string $headline): WebsitePublication
{
    return app(PublishWebsiteCatalog::class)->publish($host, [
        'headline' => $headline,
        'positioning_lede' => 'We find the real problem first.',
        'seo' => [
            'home' => ['title' => $headline, 'description' => 'Shop website.'],
            'book' => ['title' => 'Book', 'description' => 'Request a visit.'],
            'contact' => ['title' => 'Contact', 'description' => 'Call or write.'],
        ],
        'common_problems' => [[
            'slug' => 'check-engine-light',
            'title' => 'Check Engine Light',
            'tier' => 1,
            'meta_description' => 'Check engine light diagnosis.',
        ]],
    ], force: true);
}

function useLugsNPlugsWebsite(bool $preferred = true): void
{
    config([
        'app.asset_url' => null,
        'surfaces.public_aliases' => ['www.lugsnplugs.com', 'lugsnplugs.arksms.com'],
        'website.custom_domains' => [[
            'domain' => 'lugsnplugs.com',
            'site_host' => 'lugsnplugs.arksms.com',
            'preferred' => $preferred,
        ]],
    ]);
}

test('native host and custom domain resolve one publication', function (): void {
    useLugsNPlugsWebsite();
    $publication = publishHostedWebsite('lugsnplugs.arksms.com', 'Native headline');
    publishHostedWebsite('lugsnplugs.com', 'Stray custom-domain headline');

    $resolver = app(PublishedWebsiteResolver::class);
    $fromNative = $resolver->forHost('lugsnplugs.arksms.com');
    $fromCustom = $resolver->forHost('LugsNPlugs.com');

    expect($fromNative)->not->toBeNull()
        ->and($fromCustom)->not->toBeNull()
        ->and($fromNative->publication->id)->toBe($publication->id)
        ->and($fromCustom->publication->id)->toBe($publication->id)
        ->and($fromNative->site->public_host)->toBe('lugsnplugs.arksms.com')
        ->and($fromCustom->headline())->toBe('Native headline')
        ->and($fromNative->canonicalHost())->toBe('lugsnplugs.com')
        ->and($fromCustom->canonicalHost())->toBe('lugsnplugs.com')
        ->and($resolver->forHost('www.lugsnplugs.com'))->toBeNull()
        ->and($resolver->forHost('evil.example'))->toBeNull()
        ->and(WebsiteSite::query()->where('public_host', 'lugsnplugs.arksms.com')->count())->toBe(1);
});

test('preferred custom domain is the public canonical on both hosts', function (): void {
    useLugsNPlugsWebsite();
    publishHostedWebsite('lugsnplugs.arksms.com', 'Native headline');
    Http::fake();

    $this->get('http://lugsnplugs.arksms.com/')
        ->assertOk()
        ->assertSee('Native headline')
        ->assertSee('rel="canonical" href="https://lugsnplugs.com/"', false)
        ->assertSee('property="og:url" content="https://lugsnplugs.com/"', false)
        ->assertSee('"url":"https://lugsnplugs.com/"', false)
        ->assertSee('href="https://lugsnplugs.arksms.com/assets/', false)
        ->assertDontSee('https://lugsnplugs.com/build', false);

    $this->get('http://lugsnplugs.com/')
        ->assertOk()
        ->assertSee('Native headline')
        ->assertSee('rel="canonical" href="https://lugsnplugs.com/"', false)
        ->assertSee('href="https://lugsnplugs.com/assets/', false)
        ->assertDontSee('Stray custom-domain headline');

    $this->get('http://lugsnplugs.com/book')
        ->assertOk()
        ->assertSee('action="/leads"', false)
        ->assertSee('rel="canonical" href="https://lugsnplugs.com/book"', false);

    $this->get('http://lugsnplugs.arksms.com/sitemap.xml')
        ->assertOk()
        ->assertSee('https://lugsnplugs.com/', false)
        ->assertSee('https://lugsnplugs.com/common-problems/check-engine-light', false)
        ->assertDontSee('lugsnplugs.arksms.com', false);

    $this->get('http://lugsnplugs.com/robots.txt')
        ->assertOk()
        ->assertSee('Sitemap: https://lugsnplugs.com/sitemap.xml', false);

    Http::assertNothingSent();
});

test('native host is canonical when the shop has no custom domain', function (): void {
    config([
        'website.custom_domains' => [],
        'app.asset_url' => null,
    ]);
    publishHostedWebsite('joesgarage.arksms.com', 'Joes headline');

    $this->get('http://joesgarage.arksms.com/')
        ->assertOk()
        ->assertSee('rel="canonical" href="https://joesgarage.arksms.com/"', false);

    $this->get('http://joesgarage.com/')
        ->assertNotFound()
        ->assertDontSee('Joes headline');

    $this->get('http://joesgarage.arksms.com/sitemap.xml')
        ->assertOk()
        ->assertSee('https://joesgarage.arksms.com/', false)
        ->assertDontSee('joesgarage.com', false);
});

test('a custom domain that is not preferred still resolves the native site', function (): void {
    useLugsNPlugsWebsite(preferred: false);
    publishHostedWebsite('lugsnplugs.arksms.com', 'Native headline');

    $website = app(PublishedWebsiteResolver::class)->forHost('lugsnplugs.com');

    expect($website)->not->toBeNull()
        ->and($website->canonicalHost())->toBe('lugsnplugs.arksms.com')
        ->and($website->site->public_host)->toBe('lugsnplugs.arksms.com');

    $this->get('http://lugsnplugs.com/')
        ->assertOk()
        ->assertSee('rel="canonical" href="https://lugsnplugs.arksms.com/"', false);
});

test('www redirects to the custom domain and does not render', function (): void {
    useLugsNPlugsWebsite();
    publishHostedWebsite('lugsnplugs.arksms.com', 'Native headline');

    $this->get('http://www.lugsnplugs.com/book?concern=brakes')
        ->assertStatus(301)
        ->assertRedirect('https://lugsnplugs.com/book?concern=brakes');

    expect(app(PublishedWebsiteResolver::class)->forHost('www.lugsnplugs.com'))->toBeNull();
});

test('one shops custom domain cannot resolve another shops publication', function (): void {
    config([
        'website.custom_domains' => [
            [
                'domain' => 'lugsnplugs.com',
                'site_host' => 'lugsnplugs.arksms.com',
                'preferred' => true,
            ],
            [
                'domain' => 'joesgarage.com',
                'site_host' => 'joesgarage.arksms.com',
                'preferred' => true,
            ],
            [
                'domain' => 'shared.example',
                'site_host' => 'lugsnplugs.arksms.com',
                'preferred' => false,
            ],
            [
                'domain' => 'shared.example',
                'site_host' => 'joesgarage.arksms.com',
                'preferred' => false,
            ],
        ],
    ]);
    publishHostedWebsite('lugsnplugs.arksms.com', 'Lugs headline');
    publishHostedWebsite('joesgarage.arksms.com', 'Joes headline');

    $this->get('http://lugsnplugs.com/')
        ->assertOk()
        ->assertSee('Lugs headline')
        ->assertDontSee('Joes headline');

    $this->get('http://joesgarage.arksms.com/')
        ->assertOk()
        ->assertSee('Joes headline')
        ->assertDontSee('Lugs headline')
        ->assertSee('rel="canonical" href="https://joesgarage.com/"', false);

    $this->get('http://shared.example/')
        ->assertNotFound();

    $this->get('http://evil.example/')
        ->assertNotFound()
        ->assertDontSee('Lugs headline')
        ->assertDontSee('Joes headline');
});

test('leads from the native host and the custom domain reach the same site', function (): void {
    useLugsNPlugsWebsite();
    $publication = publishHostedWebsite('lugsnplugs.arksms.com', 'Native headline');
    Http::fake();

    $this->post('http://lugsnplugs.arksms.com/leads', [
        'contact_name' => 'Pat Driver',
        'contact_phone' => '7195550142',
        'concern' => 'From the ARK host.',
        'page' => 'book',
    ])->assertRedirect();

    $this->post('http://lugsnplugs.com/leads', [
        'contact_name' => 'Sam Driver',
        'contact_phone' => '7195550143',
        'concern' => 'From the custom domain.',
        'page' => 'contact',
    ])->assertRedirect();

    $leads = Lead::query()->orderBy('id')->get();
    expect($leads)->toHaveCount(2)
        ->and($leads[0]->source)->toBe(LeadSource::Website)
        ->and($leads[0]->metadata['public_host'] ?? null)->toBe('lugsnplugs.arksms.com')
        ->and($leads[0]->metadata['canonical_host'] ?? null)->toBe('lugsnplugs.com')
        ->and($leads[1]->metadata['public_host'] ?? null)->toBe('lugsnplugs.com')
        ->and($leads[1]->metadata['canonical_host'] ?? null)->toBe('lugsnplugs.com')
        ->and(WebsitePublication::query()->where('is_current', true)->where('website_site_id', $publication->website_site_id)->count())->toBe(1);

    $this->post('http://evil.example/leads', [
        'contact_phone' => '7195550142',
        'concern' => 'Should not land.',
    ])->assertNotFound();

    expect(Lead::query()->count())->toBe(2);
    Http::assertNothingSent();
});

test('website host resolution does not treat route aliases as site identity', function (): void {
    $resolver = file_get_contents(app_path('Ark/Website/PublishedWebsiteResolver.php'));
    $hosts = file_get_contents(app_path('Ark/Website/WebsiteHosts.php'));

    expect($resolver)->not->toContain('public_aliases')
        ->and($resolver)->not->toContain('surfaces.public')
        ->and($resolver)->not->toContain('Foundry')
        ->and($hosts)->not->toContain('SURFACE_PUBLIC_ALIASES')
        ->and($hosts)->not->toContain('Foundry');
});
