<?php

use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\Leads\Public\CommonProblemFormCopy;
use App\Ark\Operations\Leads\Public\CommonProblemFormCopySummary;
use App\Ark\Operations\Leads\Public\CommonProblemRegistry;
use App\Ark\Operations\Leads\Public\PublicSurfaceContext;
use App\Ark\Operations\Leads\Public\PublicSurfaceEvent;
use App\Ark\Operations\Leads\Public\PublicSurfaceEventRecorder;
use App\Ark\Operations\Leads\Public\PublicSurfaceEventType;
use Illuminate\Support\Facades\Cache;

test('common problems index offers book appointment without form-copy exposure', function (): void {
    Cache::forget('public_surface.common_problem_variant_counter');

    $this->get(route('public.common-problems.index'))
        ->assertOk()
        ->assertSee('Book an Appointment', false)
        ->assertSee(route('public.book'), false)
        ->assertDontSee('name="public_surface_variant"', false)
        ->assertDontSee('action="'.route('public.leads.store').'"', false)
        ->assertDontSee('Describe what your vehicle is doing on the homepage', false);

    expect(PublicSurfaceEvent::query()
        ->where('event', PublicSurfaceEventType::FormExposed->value)
        ->where('context->page', 'common-problems.index')
        ->exists())->toBeFalse();
});

test('common problem show page offers book appointment with concern handoff', function (): void {
    $problem = CommonProblemRegistry::find('car-wont-start');

    $this->get(route('public.common-problems.show', 'car-wont-start'))
        ->assertOk()
        ->assertSee('Book an Appointment', false)
        ->assertSee('We’ll start with', false)
        ->assertSee(e($problem['concern_prefill']), false)
        ->assertSee(route('public.book', ['concern' => $problem['concern_prefill']]), false)
        ->assertDontSee('Talk to a Service Advisor', false)
        ->assertDontSee('Need help with', false)
        ->assertDontSee('name="first_name"', false);
});

test('common problem lead submission stores surface variant metadata', function (): void {
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

    $lead = Lead::query()->sole();

    expect($lead->metadata['public_surface'])->toBe([
        'page' => 'common-problems.ac-not-cold',
        'variant' => 'contextual',
        'placement' => 'embedded_form',
    ]);
});

test('common problem form copy summary groups exposures through outcomes by variant', function (): void {
    $recorder = app(PublicSurfaceEventRecorder::class);
    $contextA = PublicSurfaceContext::commonProblemsIndex('a')->toArray();
    $contextC = PublicSurfaceContext::commonProblemShow('car-wont-start')->toArray();
    $from = now()->subDay();
    $to = now()->addDay();

    $recorder->record('session-a', PublicSurfaceEventType::FormExposed, context: $contextA);
    $recorder->record('session-a', PublicSurfaceEventType::LeadStarted, context: $contextA);
    $recorder->record('session-a', PublicSurfaceEventType::LeadSubmitted, context: $contextA);
    $recorder->record('session-b', PublicSurfaceEventType::FormExposed, context: $contextC);
    $recorder->record('session-b', PublicSurfaceEventType::LeadSubmitted, context: $contextC);

    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Scheduled,
        'concern' => 'My car won\'t start.',
        'contact_phone' => '7195550101',
        'metadata' => ['public_surface' => $contextC],
        'scheduled_at' => now(),
        'created_at' => now(),
    ]);

    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Something else.',
        'contact_phone' => '7195550102',
        'metadata' => ['public_surface' => $contextA],
        'created_at' => now(),
    ]);

    $summary = app(CommonProblemFormCopySummary::class)->forPeriod($from, $to);
    $variantA = collect($summary['variants'])->firstWhere('variant', 'a');
    $variantContextual = collect($summary['variants'])->firstWhere('variant', 'contextual');

    expect($variantA['exposures'])->toBe(1)
        ->and($variantA['starts'])->toBe(1)
        ->and($variantA['submits'])->toBe(1)
        ->and($variantA['leads'])->toBe(1)
        ->and($variantA['start_rate'])->toBe(100.0)
        ->and($variantA['submit_from_start_rate'])->toBe(100.0)
        ->and($variantContextual['exposures'])->toBe(1)
        ->and($variantContextual['submits'])->toBe(1)
        ->and($variantContextual['scheduled'])->toBe(1)
        ->and($variantContextual['scheduled_rate'])->toBe(100.0);
});

test('common problem form copy variants use quiet customer-facing language', function (): void {
    $copy = CommonProblemFormCopy::render('a', 'Demo Auto Repair');

    expect($copy['heading'])->toBe('Book an Appointment')
        ->and($copy['subheading'])->toContain('If you don\'t see your symptom listed')
        ->and($copy['submit_label'])->toBe(\App\Ark\Operations\Leads\Public\PublicLeadFormCopy::BOOK_SUBMIT_LABEL);

    $variantC = CommonProblemFormCopy::render('c', 'Demo Auto Repair');

    expect($variantC['heading'])->toBe('Book an Appointment')
        ->and($variantC['submit_label'])->toBe(\App\Ark\Operations\Leads\Public\PublicLeadFormCopy::BOOK_SUBMIT_LABEL);
});
