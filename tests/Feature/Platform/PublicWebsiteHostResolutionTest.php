<?php

use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Platform\Website\WebsitePublication;
use App\Ark\Runtime\Surfaces\SurfaceRouting;
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

function useLugsNPlugsHosts(): void
{
    config([
        'surfaces.public' => 'lugsnplugs.com',
        'surfaces.public_aliases' => ['lugsnplugs.arksms.com', 'lugsnplugs.com'],
        'app.asset_url' => null,
    ]);
}

test('canonical host and alias resolve the same publication', function (): void {
    useLugsNPlugsHosts();
    $canonical = publishHostedWebsite('lugsnplugs.com', 'Canonical headline');
    publishHostedWebsite('lugsnplugs.arksms.com', 'Stray alias headline');

    $resolver = app(PublishedWebsiteResolver::class);
    $fromCanonical = $resolver->forHost('lugsnplugs.com');
    $fromAlias = $resolver->forHost('LugsnPlugs.arkSMS.com');

    expect($fromCanonical)->not->toBeNull()
        ->and($fromAlias)->not->toBeNull()
        ->and($fromAlias->publication->id)->toBe($canonical->id)
        ->and($fromCanonical->publication->id)->toBe($canonical->id)
        ->and($fromAlias->headline())->toBe('Canonical headline')
        ->and($resolver->forHost('evil.example'))->toBeNull()
        ->and($resolver->forHost('not a host'))->toBeNull();
});

test('alias request renders canonical urls and a canonical-only sitemap', function (): void {
    useLugsNPlugsHosts();
    publishHostedWebsite('lugsnplugs.com', 'Canonical headline');
    Http::fake();

    $this->get('http://lugsnplugs.arksms.com/')
        ->assertOk()
        ->assertSee('Canonical headline')
        ->assertSee('rel="canonical" href="https://lugsnplugs.com/"', false)
        ->assertSee('property="og:url" content="https://lugsnplugs.com/"', false)
        ->assertSee('"url":"https://lugsnplugs.com/"', false)
        ->assertSee('href="https://lugsnplugs.arksms.com/assets/', false)
        ->assertDontSee('https://lugsnplugs.com/build', false)
        ->assertDontSee('https://lugsnplugs.com/assets/', false);

    $this->get('http://lugsnplugs.arksms.com/book')
        ->assertOk()
        ->assertSee('action="/leads"', false)
        ->assertSee('rel="canonical" href="https://lugsnplugs.com/book"', false);

    $this->get('http://lugsnplugs.arksms.com/sitemap.xml')
        ->assertOk()
        ->assertSee('https://lugsnplugs.com/', false)
        ->assertSee('https://lugsnplugs.com/common-problems/check-engine-light', false)
        ->assertDontSee('lugsnplugs.arksms.com', false);

    $this->get('http://lugsnplugs.arksms.com/robots.txt')
        ->assertOk()
        ->assertSee('Sitemap: https://lugsnplugs.com/sitemap.xml', false);

    expect(config('app.asset_url'))->toBeNull()
        ->and(SurfaceRouting::publicHosts())->toContain('lugsnplugs.com', 'lugsnplugs.arksms.com');

    Http::assertNothingSent();
});

test('unknown host cannot read another shops publication', function (): void {
    useLugsNPlugsHosts();
    publishHostedWebsite('lugsnplugs.com', 'Canonical headline');
    publishHostedWebsite('other-shop.example', 'Other shop headline');

    $this->get('http://evil.example/')
        ->assertNotFound()
        ->assertDontSee('Canonical headline')
        ->assertDontSee('Other shop headline');

    $this->get('http://other-shop.example/')
        ->assertOk()
        ->assertSee('Other shop headline')
        ->assertDontSee('Canonical headline')
        ->assertSee('rel="canonical" href="https://other-shop.example/"', false);

    $this->get('http://evil.example/sitemap.xml')
        ->assertOk()
        ->assertDontSee('lugsnplugs.com')
        ->assertDontSee('other-shop.example');
});

test('lead from an accepted alias stays on the canonical website', function (): void {
    useLugsNPlugsHosts();
    $publication = publishHostedWebsite('lugsnplugs.com', 'Canonical headline');
    Http::fake();

    $this->post('http://lugsnplugs.arksms.com/leads', [
        'contact_name' => 'Pat Driver',
        'contact_phone' => '7195550142',
        'concern' => 'Check engine light is on.',
        'page' => 'book',
    ])->assertRedirect();

    $lead = Lead::query()->first();
    expect($lead)->not->toBeNull()
        ->and($lead->source)->toBe(LeadSource::Website)
        ->and($lead->contact_phone)->toBe('7195550142')
        ->and($lead->metadata['public_host'] ?? null)->toBe('lugsnplugs.arksms.com')
        ->and($lead->metadata['canonical_host'] ?? null)->toBe('lugsnplugs.com')
        ->and(WebsitePublication::query()->whereKey($publication->id)->value('is_current'))->toBeTrue()
        ->and(WebsitePublication::query()->where('is_current', true)->where('website_site_id', $publication->website_site_id)->count())->toBe(1);

    $this->post('http://evil.example/leads', [
        'contact_phone' => '7195550142',
        'concern' => 'Should not land.',
    ])->assertNotFound();

    expect(Lead::query()->count())->toBe(1);

    Http::assertNothingSent();
});

test('public website resolution does not depend on foundry', function (): void {
    $resolver = file_get_contents(app_path('Ark/Website/PublishedWebsiteResolver.php'));
    $controller = file_get_contents(app_path('Ark/Website/Http/PublicWebsiteController.php'));
    $shell = file_get_contents(resource_path('views/components/customer/shell.blade.php'));

    expect($resolver)->not->toContain('Foundry')
        ->and($resolver)->not->toContain('Http::')
        ->and($controller)->not->toContain('Foundry')
        ->and($controller)->not->toContain('Http::')
        ->and($shell)->toContain('@vite')
        ->and($shell)->not->toContain('lugsnplugs.com/build');
});
