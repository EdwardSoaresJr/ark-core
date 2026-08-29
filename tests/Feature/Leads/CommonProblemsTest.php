<?php

use App\Ark\Operations\Leads\Public\CommonProblemRegistry;
use App\Ark\Operations\Leads\Public\PublicSurfaceSettings;
use App\Ark\Growth\PublicSurface\PublicMarketingUrl;

test('common problems index lists curated high-intent problems', function (): void {
    expect(CommonProblemRegistry::all())->toHaveCount(
        count(config('common_problems', [])) + count(config('common_problem_dtc_codes', []))
    );

    expect(CommonProblemRegistry::featuredForIndexSymptoms())->toHaveCount(8)
        ->and(CommonProblemRegistry::featuredLocalServices())->toHaveCount(6)
        ->and(PublicSurfaceSettings::shopServicesForDisplay())->toHaveCount(6);

    $this->get(route('public.common-problems.index'))
        ->assertOk()
        ->assertSee('Common car problems', false)
        ->assertSee('What&apos;s wrong with your car?', false)
        ->assertSee('Looking for a specific service?', false)
        ->assertSee('View Auto Repair Services', false)
        ->assertSee(route('public.common-problems.show', 'car-diagnostics-demo-city'), false)
        ->assertSee(route('public.common-problems.show', 'brake-repair-demo-city'), false)
        ->assertSee(route('public.common-problems.show', 'tune-up-demo-city'), false)
        ->assertSee(route('public.common-problems.show', 'auto-repair-demo-city'), false)
        ->assertDontSee('Auto repair &amp; services in Demo City', false)
        ->assertDontSee('Search services', false)
        ->assertDontSee('public-problems-local-services', false)
        ->assertDontSee('id="local-services-search"', false)
        ->assertSee('aria-label="Breadcrumb"', false)
        ->assertSee('Pick the symptom closest to yours', false)
        ->assertSee('Search problems', false)
        ->assertDontSee('High-intent concerns', false)
        ->assertDontSee('Also common', false)
        ->assertSee('Demo City independent repair', false)
        ->assertSee('public-cp-quiet-proof', false)
        ->assertSee('on Google', false)
        ->assertSee('24/24 shop warranty', false)
        ->assertSee('RepairPal Certified', false)
        ->assertDontSee('public-hero-trust-chips', false)
        ->assertDontSee('Read reviews on Google', false)
        ->assertDontSee('Describe what your vehicle is doing on the homepage', false)
        ->assertSee('Check Engine Light', false)
        ->assertSee('Wheel Bearing Noise', false)
        // Non-featured titles live in the live-search catalog JSON; assert they are not featured links.
        ->assertDontSee('data-public-surface-target="common-problems.transmission-slipping"', false)
        ->assertDontSee('data-public-surface-target="common-problems.rough-idle"', false)
        ->assertDontSee('data-public-surface-target="common-problems.p0301-check-engine-code"', false)
        ->assertDontSee('data-public-surface-target="common-problems.burnt-transmission-fluid"', false)
        ->assertSee('\u0022slug\u0022:\u0022transmission-slipping\u0022', false);
});

test('common problems index uses book appointment conversion without embedded lead form', function (): void {
    $html = $this->get(route('public.common-problems.index'))
        ->assertOk()
        ->assertSee('Ready to have it looked at?', false)
        ->assertSee('Book an Appointment', false)
        ->assertSee(route('public.book'), false)
        ->assertSee('public-cp-index-book', false)
        ->assertDontSee('public-problem-book-cta', false)
        ->assertDontSee('action="'.route('public.leads.store').'"', false)
        ->assertDontSee('name="first_name"', false)
        ->assertDontSee('Tell us a little about yourself', false)
        ->assertDontSee('Talk to a Service Advisor', false)
        ->getContent();

    expect(substr_count($html, 'public-cp-index-book'))->toBeGreaterThan(0)
        ->and(substr_count($html, 'id="public-cp-index-book-heading"'))->toBe(1);
});

test('each common problem page renders structure and prefilled concern', function (): void {
    foreach (CommonProblemRegistry::slugs() as $slug) {
        $problem = CommonProblemRegistry::find($slug);

        $html = $this->get(route('public.common-problems.show', $slug))
            ->assertOk()
            ->assertSee(e($problem['title']), false)
            ->assertSee('Book an Appointment', false)
            ->assertSee('We’ll start with', false)
            ->assertSee(route('public.book', ['concern' => $problem['concern_prefill']]), false)
            ->assertDontSee('Talk to a Service Advisor', false)
            ->assertSee('public-cp-answer', false)
            ->assertSee(e($problem['problem']), false)
            ->assertSee('public-cp-quiet-proof', false)
            ->assertDontSee('public-hero-trust-chips', false)
            ->assertDontSee('Read reviews on Google', false)
            ->assertSee(e($problem['can_drive_heading']), false)
            ->assertSee('Common causes', false)
            ->assertSee('What happens next', false)
            ->assertSee(e($problem['concern_prefill']), false)
            ->assertSee('<link rel="canonical"', false)
            ->getContent();

        $isDriveSafety = (bool) preg_match(
            '/\b(can i keep|is it safe to|keep trying|keep driving|safe to drive)\b/i',
            (string) ($problem['can_drive_heading'] ?? ''),
        );

        if ($isDriveSafety) {
            expect($html)->toContain('public-cp-callout--drive');
        } else {
            expect($html)->not->toContain('public-cp-callout--drive');
        }
    }
});

test('unknown common problem slug returns 404', function (): void {
    $this->get('/common-problems/not-a-real-problem')->assertNotFound();
});

test('sitemap includes common problems index and all problem pages', function (): void {
    $response = $this->get(route('sitemap.xml'))->assertOk();

    $response->assertSee('<loc>'.PublicMarketingUrl::baseUrl().'/common-problems</loc>', false);

    foreach (CommonProblemRegistry::slugs() as $slug) {
        $response->assertSee('<loc>'.PublicMarketingUrl::baseUrl().'/common-problems/'.$slug.'</loc>', false);
    }
});

test('common problem slug serves page instead of legacy concern redirect', function (): void {
    $this->get(route('public.common-problems.show', 'check-engine-light'))
        ->assertOk()
        ->assertSee('My check engine light is on', false);

    $this->get(route('public.common-problems.show', 'ac-not-cold'))
        ->assertOk()
        ->assertSee('My AC isn', false)
        ->assertSee('blowing cold', false);
});

test('common problem page canonical points to itself not homepage', function (): void {
    $expected = PublicMarketingUrl::baseUrl().'/common-problems/check-engine-light';

    $this->get(route('public.common-problems.show', 'check-engine-light'))
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.$expected.'">', false);
});

test('common problem prefilled concern persists through lead submission', function (): void {
    $prefill = CommonProblemRegistry::find('ac-not-cold')['concern_prefill'];

    $this->from(route('public.common-problems.show', 'ac-not-cold'))
        ->post(route('public.leads.store'), [
            'concern' => $prefill,
            'phone' => '719-555-0142',
            'first_name' => 'Alex',
            'last_name' => 'Morgan',
            'source' => 'website',
            'public_surface_page' => 'common-problems.ac-not-cold',
            'public_surface_variant' => 'contextual',
            'public_surface_placement' => 'embedded_form',
        ])
        ->assertRedirect(route('public.leads.thanks'));

    $lead = \App\Ark\Operations\Leads\Lead::query()->sole();

    expect($lead->source)->toBe(\App\Ark\Operations\Leads\LeadSource::Website)
        ->and($lead->concern)->toBe($prefill)
        ->and($lead->conversation_id)->not->toBeNull();
});
