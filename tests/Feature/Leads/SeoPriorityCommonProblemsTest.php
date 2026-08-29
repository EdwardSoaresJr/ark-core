<?php

use App\Ark\Growth\PublicSurface\PublicMarketingUrl;
use App\Ark\Operations\Leads\Public\CommonProblemRegistry;

test('p0171 common problem page is indexable with faq schema', function (): void {
    expect(CommonProblemRegistry::find('p0171'))->not->toBeNull()
        ->and(CommonProblemRegistry::find('p0171')['tier'])->toBe(1);

    $this->get(route('public.common-problems.show', 'p0171'))
        ->assertOk()
        ->assertSee('P0171 Check Engine Code', false)
        ->assertSee('What does P0171 mean?', false)
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSee(
            '<link rel="canonical" href="'.PublicMarketingUrl::baseUrl().'/common-problems/p0171">',
            false,
        );
});

test('subaru overheating common problem page is live', function (): void {
    $this->get(route('public.common-problems.show', 'subaru-overheating'))
        ->assertOk()
        ->assertSee('Subaru Overheating', false)
        ->assertSee('Is it always a head gasket?', false)
        ->assertSee('Engine Overheating', false);
});

test('honda timing belt common problem page is live', function (): void {
    $this->get(route('public.common-problems.show', 'honda-timing-belt'))
        ->assertOk()
        ->assertSee('Honda Timing Belt', false)
        ->assertSee('When should a Honda timing belt be replaced?', false);
});

test('jeep overheating common problem page is live', function (): void {
    expect(CommonProblemRegistry::find('jeep-overheating'))->not->toBeNull()
        ->and(CommonProblemRegistry::find('jeep-overheating')['tier'])->toBe(1);

    $this->get(route('public.common-problems.show', 'jeep-overheating'))
        ->assertOk()
        ->assertSee('Jeep Overheating', false)
        ->assertSee('Is it always a head gasket on a Jeep?', false)
        ->assertSee('Engine Overheating', false);
});

test('jeep death wobble common problem page is live', function (): void {
    expect(CommonProblemRegistry::find('jeep-death-wobble'))->not->toBeNull()
        ->and(CommonProblemRegistry::find('jeep-death-wobble')['tier'])->toBe(1);

    $this->get(route('public.common-problems.show', 'jeep-death-wobble'))
        ->assertOk()
        ->assertSee('Jeep Death Wobble', false)
        ->assertSee('Will a steering stabilizer fix death wobble?', false)
        ->assertSee('Wheel Bearing Noise', false);
});

test('jeep expert pages appear in sitemap', function (): void {
    $response = $this->get(route('sitemap.xml'))->assertOk();

    foreach (['jeep-overheating', 'jeep-death-wobble'] as $slug) {
        $response->assertSee(
            '<loc>'.PublicMarketingUrl::baseUrl().'/common-problems/'.$slug.'</loc>',
            false,
        );
    }
});

test('legacy jeep tag urls redirect to jeep expert pages', function (): void {
    $this->get('/tag/jeep')
        ->assertRedirect('/common-problems/jeep-overheating');

    $this->get('/tag/death-wobble')
        ->assertRedirect('/common-problems/jeep-death-wobble');
});

test('seo priority common problem pages appear in sitemap', function (): void {
    $response = $this->get(route('sitemap.xml'))->assertOk();

    foreach (['p0171', 'p0300', 'p0420', 'subaru-overheating', 'honda-timing-belt'] as $slug) {
        $response->assertSee(
            '<loc>'.PublicMarketingUrl::baseUrl().'/common-problems/'.$slug.'</loc>',
            false,
        );
    }
});

test('popular dtc common problem pages are indexable with faq schema', function (): void {
    foreach (['p0300', 'p0420', 'p0442', 'p0128'] as $slug) {
        expect(CommonProblemRegistry::find($slug))->not->toBeNull();

        $this->get(route('public.common-problems.show', $slug))
            ->assertOk()
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee(
                '<link rel="canonical" href="'.PublicMarketingUrl::baseUrl().'/common-problems/'.$slug.'">',
                false,
            );
    }
});

test('gsc intent common problem pages are live with faq schema', function (): void {
    foreach ([
        'car-fluid-service',
        'audi-repair-demo-city',
        'burnt-transmission-fluid',
        'misfire-under-load',
        'transmission-fluid-change',
        'brake-fluid-service',
        'electrical-diagnostics',
    ] as $slug) {
        expect(CommonProblemRegistry::find($slug))->not->toBeNull()
            ->and(CommonProblemRegistry::find($slug)['tier'])->toBe(1);

        $this->get(route('public.common-problems.show', $slug))
            ->assertOk()
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee(
                '<link rel="canonical" href="'.PublicMarketingUrl::baseUrl().'/common-problems/'.$slug.'">',
                false,
            );
    }
});

test('gsc intent pages appear in sitemap', function (): void {
    $response = $this->get(route('sitemap.xml'))->assertOk();

    foreach ([
        'car-fluid-service',
        'audi-repair-demo-city',
        'misfire-under-load',
        'electrical-diagnostics',
    ] as $slug) {
        $response->assertSee(
            '<loc>'.PublicMarketingUrl::baseUrl().'/common-problems/'.$slug.'</loc>',
            false,
        );
    }
});

test('local service intent pages are live with ctr-focused titles', function (): void {
    $pages = [
        'auto-repair-demo-city' => 'Auto Repair Demo City | Test First',
        'mechanic-demo-city' => 'Mechanic Demo City | Diagnostics First',
        'car-diagnostics-demo-city' => 'Car Diagnostics Demo City | Live Scan',
        'brake-repair-demo-city' => 'Brake Repair Demo City | Inspected Before',
        'tune-up-demo-city' => 'Tune Up Demo City | Maintenance With Evidence',
    ];

    foreach ($pages as $slug => $titleFragment) {
        expect(CommonProblemRegistry::find($slug))->not->toBeNull()
            ->and(CommonProblemRegistry::find($slug)['tier'])->toBe(1);

        $this->get(route('public.common-problems.show', $slug))
            ->assertOk()
            ->assertSee($titleFragment, false)
            ->assertSee('"@type":"FAQPage"', false);
    }
});

test('homepage uses intent-first seo title', function (): void {
    $this->get(route('public.home'))
        ->assertOk()
        ->assertSee('<title>Auto Repair Demo City | Verified Diagnostics</title>', false);
});

test('legacy estimate and local service urls redirect correctly', function (): void {
    $this->get('/request-estimate')->assertRedirect('/');

    $this->get('/auto-repair')
        ->assertRedirect('/common-problems/auto-repair-demo-city');

    $this->get('/mechanic-demo-city')
        ->assertRedirect('/common-problems/mechanic-demo-city');
});

test('local service pages appear in sitemap', function (): void {
    $response = $this->get(route('sitemap.xml'))->assertOk();

    foreach ([
        'auto-repair-demo-city',
        'mechanic-demo-city',
        'car-diagnostics-demo-city',
        'brake-repair-demo-city',
        'tune-up-demo-city',
    ] as $slug) {
        $response->assertSee(
            '<loc>'.PublicMarketingUrl::baseUrl().'/common-problems/'.$slug.'</loc>',
            false,
        );
    }
});

test('legacy gsc slugs redirect to new intent pages', function (): void {
    $this->get('/misfire-under-load')
        ->assertRedirect('/common-problems/misfire-under-load');

    $this->get('/electrical-diagnostics')
        ->assertRedirect('/common-problems/electrical-diagnostics');

    $this->get('/car-fluid-service')
        ->assertRedirect('/common-problems/car-fluid-service');
});
