<?php

use App\Ark\Growth\PublicSurface\PublicMarketingUrl;
use App\Ark\Operations\Leads\Public\CommonProblemRegistry;

test('wheel bearing noise page answers the questions Colorado Springs drivers ask', function (): void {
    $problem = CommonProblemRegistry::find('wheel-bearing-noise');

    expect($problem)->not->toBeNull()
        ->and($problem['tier'])->toBe(1);

    $response = $this->get(route('public.common-problems.show', 'wheel-bearing-noise'))
        ->assertOk()
        ->assertSee('Wheel Bearing Noise', false)
        ->assertSee('Is it safe to drive with a bad wheel bearing?', false)
        ->assertSee('Often sounds like', false)
        ->assertSee('If you wait', false)
        ->assertSee('What repair usually involves', false)
        ->assertSee('What is wheel bearing noise?', false)
        ->assertSee('What does a bad wheel bearing sound like?', false)
        ->assertSee('Wheel bearing noise in Colorado Springs?', false)
        ->assertSee('How do you tell bearing noise from tire noise?', false)
        ->assertSee('Brake Noise', false)
        ->assertSee('<link rel="canonical"', false);

    $response->assertSee(
        '<link rel="canonical" href="'.PublicMarketingUrl::baseUrl().'/common-problems/wheel-bearing-noise">',
        false,
    );
});

test('wheel bearing noise page emits faq json-ld when faqs are present', function (): void {
    $this->get(route('public.common-problems.show', 'wheel-bearing-noise'))
        ->assertOk()
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSee('What does a bad wheel bearing sound like?', false);
});

test('wheel bearing noise is listed on the common problems index as tier one', function (): void {
    $this->get(route('public.common-problems.index'))
        ->assertOk()
        ->assertSee('Wheel Bearing Noise', false);
});

test('wheel bearing noise appears in sitemap', function (): void {
    $this->get(route('sitemap.xml'))
        ->assertOk()
        ->assertSee('<loc>'.PublicMarketingUrl::baseUrl().'/common-problems/wheel-bearing-noise</loc>', false);
});

test('wheel bearing opportunity rule no longer recommends creating an existing page', function (): void {
    expect(CommonProblemRegistry::find('wheel-bearing-noise'))->not->toBeNull();
});
