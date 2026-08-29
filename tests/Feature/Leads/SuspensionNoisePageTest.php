<?php

use App\Ark\Growth\PublicSurface\PublicMarketingUrl;
use App\Ark\Operations\Leads\Public\CommonProblemRegistry;

test('suspension noise page answers the questions Demo City drivers ask', function (): void {
    $problem = CommonProblemRegistry::find('suspension-noise');

    expect($problem)->not->toBeNull()
        ->and($problem['tier'])->toBe(1);

    $response = $this->get(route('public.common-problems.show', 'suspension-noise'))
        ->assertOk()
        ->assertSee('Suspension Noise', false)
        ->assertSee('Is it safe to drive with suspension noise?', false)
        ->assertSee('Often sounds like', false)
        ->assertSee('If you wait', false)
        ->assertSee('How we check it', false)
        ->assertSee('What does suspension noise sound like?', false)
        ->assertSee('How do you find the source of suspension noise?', false)
        ->assertSee('Wheel Bearing Noise', false)
        ->assertSee('<link rel="canonical"', false);

    $response->assertSee(
        '<link rel="canonical" href="'.PublicMarketingUrl::baseUrl().'/common-problems/suspension-noise">',
        false,
    );
});

test('suspension noise page emits faq json-ld when faqs are present', function (): void {
    $this->get(route('public.common-problems.show', 'suspension-noise'))
        ->assertOk()
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSee('Is suspension noise the same as death wobble?', false);
});

test('suspension noise is listed on the common problems index as tier one', function (): void {
    $this->get(route('public.common-problems.index'))
        ->assertOk()
        ->assertSee('Suspension Noise', false);
});

test('suspension noise appears in sitemap', function (): void {
    $this->get(route('sitemap.xml'))
        ->assertOk()
        ->assertSee('<loc>'.PublicMarketingUrl::baseUrl().'/common-problems/suspension-noise</loc>', false);
});

test('suspension noise slug serves directly without legacy redirect to rough idle', function (): void {
    $this->get('/common-problems/suspension-noise')
        ->assertOk()
        ->assertSee('Suspension Noise', false);
});
