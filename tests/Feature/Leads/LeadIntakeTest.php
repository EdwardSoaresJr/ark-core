<?php

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Encounters\EncounterSource;
use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadContactPreference;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Ark\Runtime\Surfaces\SurfaceRouting;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update(['learn_training_gate_enabled' => false]);
});

test('public homepage returns 200', function (): void {
    $this->get(route('public.home'))
        ->assertOk()
        ->assertSee('Accurate Diagnostics', false)
        ->assertSee('Honest Repairs', false)
        ->assertSee('Book an Appointment', false)
        ->assertDontSee('A real advisor reviews every request', false)
        ->assertDontSee('No call center', false)
        ->assertDontSee('real person from the shop will follow up', false)
        ->assertSee('Check engine light', false)
        ->assertSee('Car won’t start', false)
        ->assertSee('View all problems', false)
        ->assertSee('View Auto Repair Services', false)
        ->assertSee('How an appointment works', false)
        ->assertSee('Request a day', false)
        ->assertSee('You approve the estimate', false)
        ->assertDontSee('After you book', false)
        ->assertDontSee('Ready when you are', false)
        ->assertSee('Still having the same problem?', false)
        ->assertSee('Read more reviews on Google', false)
        ->assertDontSee('Real updates — not radio silence', false);
});

test('public book page opens with SMS identity gate before any wizard', function (): void {
    ShopSettings::current()->update([
        'twilio_account_sid' => 'ACtestverify',
        'twilio_auth_token' => 'test-token',
        'telephony_inbound_number' => '7195559999',
    ]);
    ShopSettings::forgetCurrent();

    $this->get(route('public.book'))
        ->assertOk()
        ->assertSee('public-book-experience', false)
        ->assertSee('public-book-underlay', false)
        ->assertSee('data-public-book-overlay', false)
        ->assertSee('position:fixed', false)
        ->assertSee('Accurate Diagnostics.', false)
        ->assertSee('Let’s get your vehicle taken care of', false)
        ->assertSee('Enter your mobile number', false)
        ->assertSee('Send code', false)
        ->assertSee('Use email instead', false)
        ->assertDontSee('href="'.route('portal.access', ['return' => '/book']).'"', false)
        ->assertDontSee('I’m new here', false)
        ->assertDontSee('What can we help you with?', false)
        ->assertDontSee('Talk to a Service Advisor', false)
        ->assertDontSee('public-book-surface', false);

    // Unauthenticated guest query no longer unlocks the wizard.
    $this->get(route('public.book', ['guest' => 1]))
        ->assertOk()
        ->assertSee('Enter your mobile number', false)
        ->assertDontSee('What can we help you with?', false);
});

test('book appointment request stores preferred visit in lead without creating appointment', function (): void {
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-07-24 10:00:00', 'America/Denver'));

    ShopSettings::current()->update([
        'shop_timezone' => 'America/Denver',
        'twilio_account_sid' => 'ACtestverify',
        'twilio_auth_token' => 'test-token',
        'telephony_inbound_number' => '7195559999',
        'appointment_request_availability' => [
            'weekly' => [
                'monday' => ['enabled' => true],
                'tuesday' => ['enabled' => true],
                'wednesday' => ['enabled' => true],
                'thursday' => ['enabled' => true],
                'friday' => ['enabled' => true],
                'saturday' => ['enabled' => false],
                'sunday' => ['enabled' => false],
            ],
            'horizon_days' => 14,
            'minimum_notice_days' => 0,
        ],
    ]);
    ShopSettings::forgetCurrent();

    session([
        \App\Ark\Operations\Leads\Public\LeadPhoneVerification::SESSION_KEY => [
            'phone' => '7195550142',
            'verified_at' => now()->timestamp,
        ],
    ]);

    $this->post(route('public.leads.store'), [
        'concern' => 'Grinding when braking.',
        'preferred_date' => '2026-07-27', // Monday
        'preferred_period' => 'morning',
        'phone' => '719-555-0142',
        'first_name' => 'Alex',
        'last_name' => 'Morgan',
        'source' => LeadSource::Website->value,
        'form_rendered_at' => now()->subSeconds(5)->timestamp,
        'public_surface_page' => 'book',
        'public_surface_variant' => 'appointment_request',
        'public_surface_placement' => 'embedded_form',
    ])->assertRedirect(route('public.leads.thanks'));

    $lead = Lead::query()->sole();

    expect($lead->concern)->toStartWith('Preferred visit: Monday, July 27 · Morning')
        ->and($lead->concern)->toContain('Grinding when braking.')
        ->and(data_get($lead->metadata, 'appointment_request.preferred_date'))->toBe('2026-07-27')
        ->and(data_get($lead->metadata, 'appointment_request.preferred_period'))->toBe('morning')
        ->and(data_get($lead->metadata, 'appointment_request.preferred_availability'))->toBe('Monday, July 27 · Morning')
        ->and(data_get($lead->metadata, 'public_surface.page'))->toBe('book');

    expect(Appointment::query()->count())->toBe(0);

    $this->get(route('public.leads.thanks'))
        ->assertOk()
        ->assertSee('Appointment Requested', false)
        ->assertSee('review your request', false)
        ->assertSee('check availability', false)
        ->assertSee('contact you to confirm', false)
        ->assertSee('Monday, July 27 · Morning', false)
        ->assertDontSee('Your appointment is scheduled', false)
        ->assertDontSee('You are booked', false);

    \Illuminate\Support\Carbon::setTestNow();
});

test('book appointment request with single name never stores placeholder surname', function (): void {
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-07-24 10:00:00', 'America/Denver'));

    ShopSettings::current()->update([
        'shop_timezone' => 'America/Denver',
        'twilio_account_sid' => 'ACtestverify',
        'twilio_auth_token' => 'test-token',
        'telephony_inbound_number' => '7195559999',
        'appointment_request_availability' => [
            'weekly' => [
                'monday' => ['enabled' => true],
                'tuesday' => ['enabled' => true],
                'wednesday' => ['enabled' => true],
                'thursday' => ['enabled' => true],
                'friday' => ['enabled' => true],
                'saturday' => ['enabled' => false],
                'sunday' => ['enabled' => false],
            ],
            'horizon_days' => 14,
            'minimum_notice_days' => 0,
        ],
    ]);
    ShopSettings::forgetCurrent();

    session([
        \App\Ark\Operations\Leads\Public\LeadPhoneVerification::SESSION_KEY => [
            'phone' => '7195550199',
            'verified_at' => now()->timestamp,
        ],
    ]);

    $this->post(route('public.leads.store'), [
        'concern' => 'Brakes',
        'preferred_date' => '2026-07-27',
        'preferred_period' => 'any',
        'phone' => '719-555-0199',
        'first_name' => 'Alex',
        'last_name' => '',
        'source' => LeadSource::Website->value,
        'form_rendered_at' => now()->subSeconds(5)->timestamp,
        'public_surface_page' => 'book',
        'public_surface_variant' => 'appointment_request',
        'public_surface_placement' => 'embedded_form',
    ])->assertRedirect(route('public.leads.thanks'));

    $lead = Lead::query()->sole();

    expect($lead->contact_name)->toBe('Alex')
        ->and($lead->contact_name)->not->toContain('—')
        ->and(data_get($lead->metadata, 'appointment_request.preferred_period'))->toBe('any');

    $this->get(route('public.leads.thanks'))
        ->assertOk()
        ->assertSee('Thank you, Alex.', false)
        ->assertDontSee('Thank you, Alex —', false)
        ->assertDontSee('Alex —', false);

    \Illuminate\Support\Carbon::setTestNow();
});

test('guest book page never lists known vehicles without a signed-in session', function (): void {
    $customer = \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => 'Hidden',
        'last_name' => 'Owner',
        'phone' => '7195550111',
        'customer_type' => 'Retail',
    ]);

    $customer->vehicles()->create([
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Tacoma',
    ]);

    session([
        \App\Ark\Operations\Leads\Public\LeadPhoneVerification::SESSION_KEY => [
            'phone' => '7195550142',
            'verified_at' => now()->timestamp,
        ],
        \App\Ark\Operations\Customers\Recognition\CustomerRecognitionProjection::SESSION_GUEST_KEY => true,
    ]);

    $this->get(route('public.book'))
        ->assertOk()
        ->assertSee('Which vehicle?', false)
        ->assertSee('Year / Make / Model', false)
        ->assertDontSee('2020 Toyota Tacoma', false)
        ->assertDontSee('+ Another Vehicle', false);
});

test('post valid lead creates lead and conversation message', function (): void {
    $this->post(route('public.leads.store'), [
        'concern' => 'Brakes squeal when stopping.',
        'phone' => '719-555-0142',
        'first_name' => 'Alex',
        'last_name' => 'Morgan',
        'source' => LeadSource::Website->value,
    ])
        ->assertRedirect(route('public.leads.thanks'));

    $sessionId = session()->getId();

    $this->get(route('public.leads.thanks'))
        ->assertOk()
        ->assertSee('aria-label="Breadcrumb"', false)
        ->assertSee('Request received', false)
        ->assertSee('Thank you, Alex.', false)
        ->assertSee('Brakes squeal when stopping.', false)
        ->assertSee('review it and reach out with the next step', false)
        ->assertSee('What happens next', false)
        ->assertSee('independent repair', false)
        ->assertDontSee('We got it.', false)
        ->assertDontSee('Message received', false);

    $lead = Lead::query()->first();

    expect($lead)->not->toBeNull()
        ->and($lead->source)->toBe(LeadSource::Website)
        ->and($lead->state)->toBe(LeadState::Received)
        ->and($lead->concern)->toBe('Brakes squeal when stopping.')
        ->and($lead->contact_phone)->toBe('7195550142')
        ->and($lead->contact_name)->toBe('Alex Morgan')
        ->and($lead->contact_preference)->toBe(LeadContactPreference::Text)
        ->and($lead->conversation_id)->not->toBeNull();

    $message = ConversationMessage::query()->where('conversation_id', $lead->conversation_id)->first();

    expect($message)->not->toBeNull()
        ->and($message->body)->toBe('Brakes squeal when stopping.')
        ->and($message->metadata['website_lead'] ?? null)->toBeTrue();

    expect(\App\Ark\Operations\Leads\Public\PublicSurfaceEvent::query()->where('session_id', $sessionId)->pluck('event')->all())
        ->toContain('lead_submitted')
        ->toContain('lead_created');
});

test('honeypot submission is accepted silently without creating lead', function (): void {
    $this->post(route('public.leads.store'), [
        'concern' => 'Spam',
        'phone' => '719-555-0000',
        'company_website' => 'https://spam.test',
    ])
        ->assertRedirect(route('public.leads.thanks'));

    expect(Lead::query()->count())->toBe(0);
});

test('validation requires concern, phone, and name', function (): void {
    config()->set('public_lead.phone_verification_required', false);

    $this->from(route('public.home'))
        ->post(route('public.leads.store'), [
            'phone' => '7195550100',
        ])
        ->assertRedirect(route('public.home'));

    expect(Lead::query()->count())->toBe(0);

    $this->from(route('public.home'))
        ->post(route('public.leads.store'), [
            'concern' => 'Engine noise',
        ])
        ->assertRedirect(route('public.home'));

    expect(Lead::query()->count())->toBe(0);

    $this->from(route('public.home'))
        ->post(route('public.leads.store'), [
            'concern' => 'Engine noise',
            'phone' => '7195550100',
        ])
        ->assertRedirect(route('public.home'))
        ->assertSessionHasErrors(['first_name']);

    expect(Lead::query()->count())->toBe(0);

    $this->from(route('public.home'))
        ->post(route('public.leads.store'), [
            'concern' => 'Engine noise',
            'phone' => '123',
        ])
        ->assertRedirect(route('public.home'))
        ->assertSessionHasErrors('phone');

    expect(Lead::query()->count())->toBe(0);
});

test('email contact preference requires email address', function (): void {
    config()->set('public_lead.phone_verification_required', false);

    $this->from(route('public.home'))
        ->post(route('public.leads.store'), [
            'concern' => 'Need a quote for brake work.',
            'phone' => '7195550100',
            'first_name' => 'Alex',
            'last_name' => 'Morgan',
            'contact_preference' => LeadContactPreference::Email->value,
        ])
        ->assertRedirect(route('public.home'))
        ->assertSessionHasErrors('email');

    expect(Lead::query()->count())->toBe(0);
});

test('lead stores contact preference from public form', function (): void {
    config()->set('public_lead.phone_verification_required', false);

    $this->post(route('public.leads.store'), [
        'concern' => 'Check engine light is flashing.',
        'phone' => '719-555-0144',
        'first_name' => 'Jordan',
        'last_name' => 'Lee',
        'email' => 'jordan@example.com',
        'contact_preference' => LeadContactPreference::Email->value,
        'source' => LeadSource::Website->value,
    ])->assertRedirect(route('public.leads.thanks'));

    $lead = Lead::query()->first();

    expect($lead)->not->toBeNull()
        ->and($lead->contact_preference)->toBe(LeadContactPreference::Email)
        ->and($lead->contact_email)->toBe('jordan@example.com');
});

test('leads index redirects to communications needs attention', function (): void {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->get(route('operations.leads.index'))
        ->assertRedirect(\App\Ark\Operations\Communications\CommunicationsNeedsYou::url());
});

test('guest cannot view leads index', function (): void {
    $this->get(route('operations.leads.index'))
        ->assertRedirect();
});

test('advisor can mark lead contacted', function (): void {
    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Oil change',
        'contact_phone' => '7195550102',
    ]);

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->patch(route('operations.leads.state', $lead), ['state' => LeadState::Contacted->value])
        ->assertRedirect();

    $lead->refresh();

    expect($lead->state)->toBe(LeadState::Contacted)
        ->and($lead->first_contacted_at)->not->toBeNull();
});

test('advisor can mark lead lost', function (): void {
    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Wrong number',
        'contact_phone' => '7195550103',
    ]);

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->patch(route('operations.leads.state', $lead), [
            'state' => LeadState::Lost->value,
            'lost_reason' => 'Not a fit',
        ])
        ->assertRedirect();

    $lead->refresh();

    expect($lead->state)->toBe(LeadState::Lost)
        ->and($lead->lost_reason)->toBe('Not a fit')
        ->and($lead->lost_at)->not->toBeNull();
});

test('lead pressure counts new and not contacted', function (): void {
    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'One',
        'contact_phone' => '7195550104',
    ]);

    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Contacted,
        'concern' => 'Two',
        'contact_phone' => '7195550105',
        'first_contacted_at' => now(),
    ]);

    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $pressure = app(\App\Ark\Operations\Leads\LeadPressure::class)->resolve($user);

    expect($pressure['new_count'])->toBe(1)
        ->and($pressure['not_contacted_count'])->toBe(1)
        ->and($pressure['open_count'])->toBe(2);
});

test('website lead intake route creates lead and redirects with lead id', function (): void {
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($user)
        ->post(route('operations.intake.leads.store'), [
            'concern' => 'Need oil change and tire rotation this week.',
            'callback_name' => 'Jordan Lee',
            'callback_phone' => '555-0199',
            'rough_vehicle' => '2019 Subaru Outback',
            'source' => EncounterSource::Website->value,
        ])
        ->assertRedirect();

    $lead = Lead::query()->first();

    expect($lead)->not->toBeNull()
        ->and($lead->concern)->toContain('oil change');

    $response = $this->followingRedirects()->get(route('operations.intake.create', [
        'lead_id' => $lead->id,
        'phone' => '5550199',
        'concern' => $lead->concern,
        'q' => 'Jordan Lee',
    ]));

    $response->assertOk();
    expect($lead->concern)->toContain('oil change');
});

test('surface event recorder dedupes view per session', function (): void {
    $recorder = app(\App\Ark\Operations\Leads\Public\PublicSurfaceEventRecorder::class);

    $recorder->record('session-1', \App\Ark\Operations\Leads\Public\PublicSurfaceEventType::SurfaceViewed, attribution: 'direct');
    $recorder->record('session-1', \App\Ark\Operations\Leads\Public\PublicSurfaceEventType::SurfaceViewed, attribution: 'direct');

    expect(\App\Ark\Operations\Leads\Public\PublicSurfaceEvent::query()->count())->toBe(1);
});

test('surface event endpoint records client view', function (): void {
    $this->get(route('public.home'))->assertOk();

    $this->post(route('public.surface-events.store'), ['event' => 'surface_viewed'])
        ->assertNoContent();

    expect(\App\Ark\Operations\Leads\Public\PublicSurfaceEvent::query()->count())->toBe(1);
});

test('surface event rejects server-only events from client', function (): void {
    $this->post(route('public.surface-events.store'), ['event' => 'lead_created'])
        ->assertStatus(422);

    expect(\App\Ark\Operations\Leads\Public\PublicSurfaceEvent::query()->count())->toBe(0);
});

test('surface event records common problem link clicks with source and target', function (): void {
    $this->post(route('public.surface-events.store'), [
        'event' => 'common_problem_link_clicked',
        'page' => 'homepage',
        'source' => 'symptom_chooser',
        'target' => 'common-problems.car-wont-start',
    ])->assertNoContent();

    $event = \App\Ark\Operations\Leads\Public\PublicSurfaceEvent::query()->sole();

    expect($event->event)->toBe('common_problem_link_clicked')
        ->and($event->context)->toBe([
            'page' => 'homepage',
            'source' => 'symptom_chooser',
            'target' => 'common-problems.car-wont-start',
        ]);
});

test('surface event summary counts funnel sessions', function (): void {
    $recorder = app(\App\Ark\Operations\Leads\Public\PublicSurfaceEventRecorder::class);

    $recorder->record('session-a', \App\Ark\Operations\Leads\Public\PublicSurfaceEventType::SurfaceViewed, attribution: 'google');
    $recorder->record('session-a', \App\Ark\Operations\Leads\Public\PublicSurfaceEventType::LeadStarted);
    $recorder->record('session-b', \App\Ark\Operations\Leads\Public\PublicSurfaceEventType::SurfaceViewed, attribution: 'direct');
    $recorder->record('session-b', \App\Ark\Operations\Leads\Public\PublicSurfaceEventType::LeadStarted);
    $recorder->record('session-b', \App\Ark\Operations\Leads\Public\PublicSurfaceEventType::LeadSubmitted);
    $recorder->record('session-b', \App\Ark\Operations\Leads\Public\PublicSurfaceEventType::CallClicked);

    $summary = app(\App\Ark\Operations\Leads\Public\PublicSurfaceEventSummary::class)
        ->forPeriod(now()->subDay(), now()->addDay());

    expect($summary['visitors'])->toBe(2)
        ->and($summary['lead_starts'])->toBe(2)
        ->and($summary['lead_submissions'])->toBe(1)
        ->and($summary['abandoned_after_start'])->toBe(1)
        ->and($summary['call_clicks'])->toBe(1)
        ->and($summary['attribution']['google'])->toBe(1)
        ->and($summary['attribution']['direct'])->toBe(1);
});

test('website lead with full name prefills intake customer step', function (): void {
    config()->set('public_lead.phone_verification_required', false);

    $this->post(route('public.leads.store'), [
        'concern' => 'Rear brakes, shoes, turn drums — how much will this cost?',
        'phone' => '719-555-0180',
        'first_name' => 'Jeremiah',
        'last_name' => 'Seress',
        'vehicle_year' => 2022,
        'vehicle_make' => 'Nissan',
        'vehicle_model' => 'Versa',
        'source' => LeadSource::Website->value,
    ])->assertRedirect(route('public.leads.thanks'));

    $lead = Lead::query()->first();

    expect($lead)->not->toBeNull()
        ->and($lead->contact_name)->toBe('Jeremiah Seress');

    $user = actingAsLearnCurrentAdvisor();

    $this->actingAs($user)
        ->get(route('operations.leads.intake', $lead))
        ->assertRedirect();

    $this->actingAs($user)
        ->followingRedirects()
        ->get(route('operations.intake.create', ['lead_id' => $lead->id]))
        ->assertOk()
        ->assertSee('Jeremiah', false)
        ->assertSee('value="Jeremiah"', false)
        ->assertSee('value="Seress"', false);

    $this->actingAs($user)
        ->post(route('operations.customers.store'), [
            'intake' => 1,
            'lead_id' => $lead->id,
            'first_name' => 'Jeremiah',
            'last_name' => 'Seress',
            'phone' => '7195550180',
            'email' => 'jeremiah@example.com',
            'contact_preference' => LeadContactPreference::Text->value,
            'referral_source' => EncounterSource::Website->value,
            'customer_type' => 'Retail',
        ])
        ->assertRedirect();

    $customer = \App\Ark\Operations\Customers\Customer::query()->where('phone', '7195550180')->first();

    expect($customer)->not->toBeNull()
        ->and($customer->name)->toBe('Jeremiah Seress')
        ->and($customer->last_name)->toBe('Seress');

    $this->actingAs($user)
        ->post(route('operations.customers.vehicles.store', $customer), [
            'intake' => 1,
            'lead_id' => $lead->id,
            'year' => 2022,
            'make' => 'Nissan',
            'model' => 'Versa',
        ])
        ->assertRedirect();

    $vehicle = $customer->vehicles()->first();

    expect($vehicle)->not->toBeNull()
        ->and($vehicle->year)->toBe(2022)
        ->and($vehicle->make)->toBe('Nissan')
        ->and($vehicle->model)->toBe('Versa');

    $this->actingAs($user)
        ->post(route('operations.intake.store'), [
            'lead_id' => $lead->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'concerns' => [
                ['customer_states' => $lead->concern],
            ],
            'visit_mode' => 'drop_off',
            'billing_class' => 'Retail',
        ])
        ->assertRedirect();

    $lead->refresh();

    expect($lead->state)->toBe(LeadState::Converted)
        ->and($lead->repair_order_id)->not->toBeNull();
});

test('create contact from website lead accepts first name only', function (): void {
    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Brakes quote',
        'contact_phone' => '7195550181',
        'contact_name' => 'Jeremiah',
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.leads.create-contact', $lead))
        ->assertOk()
        ->assertSee('Optional when the lead only gave a first name', false);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->post(route('operations.leads.create-contact.store', $lead), [
            'first_name' => 'Jeremiah',
            'last_name' => '',
            'phone' => '7195550181',
            'referral_source' => EncounterSource::Website->value,
            'customer_type' => 'Retail',
        ])
        ->assertRedirect(\App\Ark\Operations\Communications\CommunicationsNeedsYou::url());

    $customer = \App\Ark\Operations\Customers\Customer::query()->where('phone', '7195550181')->first();

    expect($customer)->not->toBeNull()
        ->and($customer->name)->toBe('Jeremiah');
});

test('opening ro from lead intake auto converts lead', function (): void {
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Brakes grinding at low speed.',
        'contact_phone' => '7195550188',
        'contact_name' => 'Sam Rivera',
        'contact_email' => 'sam@example.com',
        'contact_preference' => LeadContactPreference::Call,
    ]);

    $customer = \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => 'Sam',
        'last_name' => 'Rivera',
        'phone' => '7195550188',
    ]);

    $vehicle = \App\Ark\Operations\Vehicles\Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'CR-V',
    ]);

    $this->actingAs($user)
        ->post(route('operations.intake.store'), [
            'lead_id' => $lead->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'concerns' => [
                ['customer_states' => 'Brakes grinding at low speed.'],
            ],
            'visit_mode' => 'drop_off',
            'billing_class' => 'Retail',
        ])
        ->assertRedirect();

    $lead->refresh();
    $repairOrder = \App\Ark\Operations\RepairOrders\RepairOrder::query()->sole();

    expect($lead->state)->toBe(LeadState::Converted)
        ->and($lead->repair_order_id)->toBe($repairOrder->id)
        ->and($lead->customer_id)->toBe($customer->id)
        ->and($lead->vehicle_id)->toBe($vehicle->id)
        ->and($lead->converted_at)->not->toBeNull();

    $customer->refresh();

    expect($customer->contact_preference)->toBe(LeadContactPreference::Call)
        ->and($customer->email)->toBe('sam@example.com');
});

test('intake without lead_id auto converts matching open lead', function (): void {
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Contacted,
        'concern' => 'Boos tube came off',
        'contact_phone' => '7197993060',
        'contact_name' => 'Mike',
        'vehicle_year' => 2017,
        'vehicle_make' => 'GMC',
        'vehicle_model' => 'Sierra 2500 HD',
        'first_contacted_at' => now()->subHour(),
    ]);

    $customer = \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => 'Mike',
        'last_name' => 'Driver',
        'phone' => '7197993060',
    ]);

    $vehicle = \App\Ark\Operations\Vehicles\Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2017,
        'make' => 'GMC',
        'model' => 'Sierra 2500 HD',
    ]);

    $this->actingAs($user)
        ->post(route('operations.intake.store'), [
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'concerns' => [
                ['customer_states' => 'Boos tube came off'],
            ],
            'visit_mode' => 'drop_off',
            'billing_class' => 'Retail',
        ])
        ->assertRedirect();

    $lead->refresh();
    $repairOrder = \App\Ark\Operations\RepairOrders\RepairOrder::query()->sole();

    expect($lead->state)->toBe(LeadState::Converted)
        ->and($lead->repair_order_id)->toBe($repairOrder->id);
});

test('customer hub draft RO auto converts matching open lead', function (): void {
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Overheating at idle.',
        'contact_phone' => '7195550199',
        'contact_name' => 'Jordan Lee',
        'vehicle_year' => 2015,
        'vehicle_make' => 'Toyota',
        'vehicle_model' => 'Camry',
    ]);

    $customer = \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => 'Jordan',
        'last_name' => 'Lee',
        'phone' => '7195550199',
    ]);

    $vehicle = \App\Ark\Operations\Vehicles\Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2015,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    $this->actingAs($user)
        ->post(route('operations.customers.repair-orders.drafts.store', $customer), [
            'vehicle_id' => $vehicle->id,
            'visit_reason' => 'Overheating at idle.',
        ])
        ->assertRedirect();

    $lead->refresh();
    $repairOrder = \App\Ark\Operations\RepairOrders\RepairOrder::query()->sole();

    expect($lead->state)->toBe(LeadState::Converted)
        ->and($lead->repair_order_id)->toBe($repairOrder->id);
});

test('intake does not convert when multiple open leads share phone without disambiguation', function (): void {
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Brakes grinding.',
        'contact_phone' => '7195550200',
        'vehicle_year' => 2010,
        'vehicle_make' => 'Ford',
        'vehicle_model' => 'F-150',
    ]);

    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'AC not cold.',
        'contact_phone' => '7195550200',
        'vehicle_year' => 2018,
        'vehicle_make' => 'Honda',
        'vehicle_model' => 'Accord',
    ]);

    $customer = \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => 'Pat',
        'last_name' => 'Shared',
        'phone' => '7195550200',
    ]);

    $vehicle = \App\Ark\Operations\Vehicles\Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Chevrolet',
        'model' => 'Silverado',
    ]);

    $this->actingAs($user)
        ->post(route('operations.intake.store'), [
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'concerns' => [
                ['customer_states' => 'Oil change due.'],
            ],
            'visit_mode' => 'drop_off',
            'billing_class' => 'Retail',
        ])
        ->assertRedirect();

    expect(Lead::query()->open()->count())->toBe(2);
});

test('reconcile open leads backfills existing repair order links', function (): void {
    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Contacted,
        'concern' => 'Boos tube came off',
        'contact_phone' => '7197993060',
        'contact_name' => 'Mike',
        'vehicle_year' => 2017,
        'vehicle_make' => 'GMC',
        'vehicle_model' => 'Sierra 2500 HD',
        'first_contacted_at' => now()->subHours(12),
        'created_at' => now()->subHours(12),
    ]);

    $customer = \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => 'Mike',
        'last_name' => 'Driver',
        'phone' => '7197993060',
    ]);

    $vehicle = \App\Ark\Operations\Vehicles\Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2017,
        'make' => 'GMC',
        'model' => 'Sierra 2500 HD',
    ]);

    $repairOrder = \App\Ark\Operations\RepairOrders\RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => \App\Ark\Operations\RepairOrders\RepairOrderStatus::Draft,
        'concern_summary' => 'Boos tube came off',
        'opened_at' => now()->subHours(2),
    ]);

    app(\App\Ark\Operations\Intake\RepairOrderIntakeConcerns::class)->seed(
        $repairOrder,
        'Boos tube came off',
    );

    $linked = app(\App\Ark\Operations\Leads\LeadConverter::class)->reconcileOpenLeads();

    expect($linked)->toHaveCount(1)
        ->and($linked[0]['lead_id'])->toBe($lead->id)
        ->and($linked[0]['shop_repair_order_id'])->toBe($repairOrder->repair_order_id);

    $lead->refresh();

    expect($lead->state)->toBe(LeadState::Converted)
        ->and($lead->repair_order_id)->toBe($repairOrder->id);
});

test('public host routes to public homepage when surface domains enabled', function (): void {
    $_ENV['SURFACE_DOMAINS_ENABLED'] = 'true';
    $_ENV['APP_DOMAIN'] = 'app.demo-auto.test';
    $_ENV['PORTAL_DOMAIN'] = 'portal.demo-auto.test';
    $_ENV['PUBLIC_DOMAIN'] = 'demo-auto.test';
    $_ENV['APP_URL'] = 'https://app.demo-auto.test';

    putenv('SURFACE_DOMAINS_ENABLED=true');
    putenv('APP_DOMAIN=app.demo-auto.test');
    putenv('PORTAL_DOMAIN=portal.demo-auto.test');
    putenv('PUBLIC_DOMAIN=demo-auto.test');
    putenv('APP_URL=https://app.demo-auto.test');

    $this->refreshApplication();

    expect(SurfaceRouting::publicHost())->toBe('demo-auto.test');

    $this->get('http://demo-auto.test/')
        ->assertOk()
        ->assertSee('Accurate Diagnostics', false)
        ->assertDontSee('real person from the shop will follow up', false)
        ->assertSee('Book an Appointment', false);

    $this->get('http://app.demo-auto.test/')
        ->assertRedirect(route('login'));
});

test('app host still routes portal paths to customer apex when surface domains enabled', function (): void {
    $_ENV['SURFACE_DOMAINS_ENABLED'] = 'true';
    $_ENV['APP_DOMAIN'] = 'app.demo-auto.test';
    $_ENV['PORTAL_DOMAIN'] = 'portal.demo-auto.test';
    $_ENV['PUBLIC_DOMAIN'] = 'demo-auto.test';
    $_ENV['APP_URL'] = 'https://app.demo-auto.test';

    putenv('SURFACE_DOMAINS_ENABLED=true');
    putenv('APP_DOMAIN=app.demo-auto.test');
    putenv('PORTAL_DOMAIN=portal.demo-auto.test');
    putenv('PUBLIC_DOMAIN=demo-auto.test');
    putenv('APP_URL=https://app.demo-auto.test');

    $this->refreshApplication();

    $this->get('http://app.demo-auto.test/portal/access')
        ->assertRedirect('https://demo-auto.test/portal/access');

    $this->get('http://portal.demo-auto.test/portal/access')
        ->assertRedirect('https://demo-auto.test/portal/access');
});
