<?php

use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadContactPreference;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\Leads\Public\LeadThanksProjection;
use App\Ark\Operations\Leads\Public\PublicAppointmentRequest;
use App\Ark\Operations\Settings\ShopSettings;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update(['learn_training_gate_enabled' => false]);
});

test('lead thanks projection personalizes request summary', function (): void {
    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Front brake maintenance',
        'contact_name' => 'Corrine Smith',
        'contact_phone' => '7195550142',
        'contact_preference' => LeadContactPreference::Text,
        'vehicle_year' => 2017,
        'vehicle_make' => 'Toyota',
        'vehicle_model' => 'RAV4 LE',
    ]);

    $projection = LeadThanksProjection::forLead($lead)->data;

    expect($projection['has_lead'])->toBeTrue()
        ->and($projection['appointment_request'])->toBeFalse()
        ->and($projection['status_eyebrow'])->toBe('Request received')
        ->and($projection['first_name'])->toBe('Corrine')
        ->and($projection['vehicle_label'])->toBe('2017 Toyota RAV4 LE')
        ->and($projection['concern'])->toBe('Front brake maintenance')
        ->and($projection)->not->toHaveKey('reference')
        ->and($projection['related_common_problem']['title'] ?? null)->toBe('Brake Noise')
        ->and($projection['next_steps'])->toHaveCount(4);
});

test('appointment request thanks projection does not imply a scheduled appointment', function (): void {
    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => PublicAppointmentRequest::composeConcern('Brake noise on highway.', 'Within a few days'),
        'contact_name' => 'Alex Morgan',
        'contact_phone' => '7195550142',
        'contact_preference' => LeadContactPreference::Text,
        'metadata' => PublicAppointmentRequest::metadataWithAvailability(
            ['public_surface' => ['page' => 'book', 'variant' => 'appointment_request']],
            '2026-07-28',
            'morning',
            'Tuesday, July 28 · Morning',
        ),
    ]);

    $projection = LeadThanksProjection::forLead($lead)->data;

    expect($projection['appointment_request'])->toBeTrue()
        ->and($projection['status_eyebrow'])->toBe('Appointment Requested')
        ->and($projection['preferred_availability'])->toBe('Tuesday, July 28 · Morning')
        ->and($projection['summary_line'])->toContain('review your request')
        ->and($projection['summary_line'])->toContain('check availability')
        ->and($projection['summary_line'])->toContain('contact you to confirm')
        ->and($projection['next_steps'])->toHaveCount(3)
        ->and($projection['next_steps'][2])->toContain('confirm');
});

test('lead thanks page shows personalized onboarding after submit', function (): void {
    expect(Lead::query()->count())->toBe(0);

    $this->post(route('public.leads.store'), [
        'concern' => 'Front brake maintenance',
        'phone' => '719-555-0142',
        'first_name' => 'Corrine',
        'last_name' => 'Smith',
        'vehicle_year' => 2017,
        'vehicle_make' => 'Toyota',
        'vehicle_model' => 'RAV4 LE',
        'source' => LeadSource::Website->value,
        'form_rendered_at' => now()->subSeconds(5)->timestamp,
    ])->assertRedirect(route('public.leads.thanks'));

    $lead = Lead::query()->sole();

    expect($lead->contact_name)->toBe('Corrine Smith')
        ->and($lead->contact_phone)->toBe('7195550142')
        ->and($lead->concern)->toBe('Front brake maintenance')
        ->and($lead->source)->toBe(LeadSource::Website)
        ->and($lead->state)->not->toBe(LeadState::Spam);

    $this->get(route('public.leads.thanks'))
        ->assertOk()
        ->assertSee('Thank you, Corrine.', false)
        ->assertSee('2017 Toyota RAV4 LE', false)
        ->assertSee('Front brake maintenance', false)
        ->assertDontSee('LEAD-', false)
        ->assertSee('review it and reach out with the next step', false)
        ->assertSee('A real advisor will follow up', false)
        ->assertSee('While you wait', false)
        ->assertSee('What happens next', false)
        ->assertSee('RepairPal', false);

    expect(Lead::query()->count())->toBe(1)
        ->and(Lead::query()->sole()->is($lead))->toBeTrue();
});

test('direct thanks page visit without lead shows generic onboarding', function (): void {
    $this->get(route('public.leads.thanks'))
        ->assertOk()
        ->assertSee('Thank you for reaching out.', false)
        ->assertSee('While you wait', false)
        ->assertDontSee('LEAD-', false);
});
