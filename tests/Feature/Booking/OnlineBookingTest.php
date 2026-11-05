<?php

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentRequestException;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Appointments\SchedulingHours;
use App\Ark\Operations\Booking\AppointmentRequest;
use App\Ark\Operations\Booking\AppointmentRequestStatus;
use App\Ark\Operations\Booking\BookingConstraints;
use App\Ark\Operations\Booking\BookingMode;
use App\Ark\Operations\Booking\BookingQuestion;
use App\Ark\Operations\Booking\BookingService;
use App\Ark\Operations\Booking\BookingSettings;
use App\Ark\Operations\Booking\Contracts\BookingAvailabilityConstraint;
use App\Ark\Operations\Booking\Contracts\BookingCandidate;
use App\Ark\Operations\Booking\Events\AppointmentRequestDecided;
use App\Ark\Operations\Booking\Events\AppointmentRequestReceived;
use App\Ark\Operations\Booking\ServiceLocationType;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadFormRenderStamp;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    ShopSettings::forgetCurrent();
    ShopSettings::current()->update([
        'shop_timezone' => 'America/Denver',
        'appointments_enabled' => true,
        'appointment_slot_minutes' => 30,
        'appointment_capacity_basis' => 'bays',
        'appointment_scheduling_target_percent' => 100,
        'appointment_capacity_enforcement' => 'warn',
        'scheduling_hours' => SchedulingHours::defaultWeekly(),
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
        'online_booking' => null,
    ]);
    ShopSettings::forgetCurrent();
    config(['website.book_identity_verification' => false]);

    Workstation::query()->create([
        'shop_settings_id' => ShopSettings::current()->id,
        'name' => 'Bay 1',
        'is_active' => true,
        'accepts_scheduled_work' => true,
    ]);

    // Monday 8 AM in the shop's time zone.
    Carbon::setTestNow(Carbon::parse('2026-11-09 08:00:00', 'America/Denver'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function bookingSettings(array $changes): void
{
    $settings = ShopSettings::current();
    $settings->forceFill(['online_booking' => BookingSettings::normalize(array_replace_recursive(BookingSettings::forShop($settings), $changes))])->save();
    ShopSettings::forgetCurrent();
}

function bookingPost(array $overrides = []): array
{
    return array_replace_recursive([
        'form_rendered_at' => LeadFormRenderStamp::issue(now()->subMinute()),
        'concern' => 'Grinding noise when braking',
        'preferred_date' => '2026-11-10',
        'preferred_period' => 'morning',
        'contact_name' => 'Pat Example',
        'contact_phone' => '719-555-0142',
        'contact_email' => 'pat@example.test',
        'vehicles' => [['year' => 2016, 'make' => 'Honda', 'model' => 'Civic']],
        'book_intent' => 'submit',
    ], $overrides);
}

function brakesService(array $attributes = []): BookingService
{
    return BookingService::query()->create(array_merge([
        'name' => 'Brakes',
        'description' => 'Brake inspection and repair.',
        'duration_minutes' => 90,
        'location_mode' => 'shop',
        'is_public' => true,
        'options' => [['label' => 'Front'], ['label' => 'Rear', 'duration_minutes' => 120]],
    ], $attributes));
}

test('the booking form shows what the shop configured in settings', function (): void {
    $brakes = brakesService();
    BookingService::query()->create(['name' => 'Fleet PM', 'duration_minutes' => 60, 'is_public' => false]);
    BookingQuestion::query()->create(['label' => 'Is it under warranty?', 'type' => 'yes_no', 'required' => true]);
    BookingQuestion::query()->create(['label' => 'Which pads do you prefer?', 'type' => 'choice', 'choices' => ['Ceramic', 'Semi-metallic'], 'service_ids' => [$brakes->id]]);

    $this->actingAs($this->admin)->patch(route('operations.settings.booking.update'), [
        'section' => 'form',
        'enabled' => '1',
        'mode' => 'request',
        'title' => 'Book your brake visit',
        'intro' => 'We answer within one business hour.',
        'instructions' => 'Drop keys in the box by the door.',
        'request_confirmation' => 'Thanks, we will call you.',
        'instant_confirmation' => 'See you then.',
        'concern_choices' => "Squealing\nPulsing pedal",
        'fields' => ['email' => 'hidden', 'vehicle' => 'required', 'concern' => 'optional', 'vin' => '1', 'contact_preference' => '0', 'drivability' => '1'],
        'max_vehicles' => 1,
    ])->assertRedirect();

    $page = $this->get('/book')->assertOk();

    $page->assertSee('Book your brake visit')
        ->assertSee('We answer within one business hour.')
        ->assertSee('Drop keys in the box by the door.')
        ->assertSee('Brakes')
        ->assertDontSee('Fleet PM')
        ->assertSee('Squealing')
        ->assertDontSee('Check Engine Light')
        ->assertSee('Is it under warranty?')
        ->assertSee('Which pads do you prefer?')
        ->assertSee('It is not safe to drive')
        ->assertSee('name="vehicles[0][vin]"', false)
        ->assertDontSee('name="contact_email"', false)
        ->assertDontSee('name="contact_preference"', false)
        ->assertSee('This is a request, not a confirmed appointment.');
});

test('service and problem pages prefill the concern or the service', function (): void {
    brakesService(['slug' => 'brakes']);
    bookingSettings(['concern_choices' => ['Brakes', 'Strange Noise']]);

    $this->get('/book?concern=Brakes')->assertOk()
        ->assertSee('value="Brakes" checked', false);

    $this->get('/book?service=brakes')->assertOk()
        ->assertSee('data-concern-prompt=""'."\n".'                                        checked', false);

    $this->get('/book?concern='.urlencode('Grinding when I stop'))->assertOk()
        ->assertSee('>Grinding when I stop</textarea>', false);
});

test('a customer request waits for the shop and reserves nothing', function (): void {
    Event::fake([AppointmentRequestReceived::class]);
    $brakes = brakesService();

    $response = $this->post('/book', bookingPost(['service_id' => $brakes->id, 'service_option' => 'Rear']));
    $response->assertStatus(303);

    $request = AppointmentRequest::query()->sole();
    expect($request->status)->toBe(AppointmentRequestStatus::Pending)
        ->and($request->mode)->toBe(BookingMode::Request)
        ->and($request->appointment_id)->toBeNull()
        ->and($request->serviceLabel())->toBe('Brakes · Rear')
        ->and($request->duration_minutes)->toBe(120)
        ->and($request->preferred_date->toDateString())->toBe('2026-11-10')
        ->and($request->lead->state)->toBe(LeadState::Received)
        ->and(Appointment::query()->count())->toBe(0);

    Event::assertDispatched(AppointmentRequestReceived::class);

    $this->get($response->headers->get('Location'))->assertOk()
        ->assertSee('Request received')
        ->assertSee('Not confirmed yet.');
});

test('staff see the request on the schedule, schedule it, and the request is answered', function (): void {
    Event::fake([AppointmentRequestDecided::class]);
    $this->post('/book', bookingPost())->assertStatus(303);
    $request = AppointmentRequest::query()->sole();

    $this->actingAs($this->admin)->get(route('operations.appointments.index', ['day' => '2026-11-10']))
        ->assertOk()
        ->assertSee('Online requests waiting')
        ->assertSee('Pat Example')
        ->assertSee('Tue, Nov 10 · Morning');

    $this->actingAs($this->admin)->get(route('operations.schedule', ['lead' => $request->lead_id, 'appointment_request' => $request->id, 'preferred_date' => '2026-11-10']))
        ->assertOk()
        ->assertSee('Online request')
        ->assertSee('name="appointment_request_id" value="'.$request->id.'"', false);

    // Staff picks 1 PM, outside the customer's morning preference: the shop decides the time.
    $this->actingAs($this->admin)->post(route('operations.appointments.store'), [
        'lead_id' => $request->lead_id,
        'appointment_request_id' => $request->id,
        'contact_name' => 'Pat Example',
        'contact_phone' => '719-555-0142',
        'starts_date' => '2026-11-10',
        'starts_time' => '13:00',
        'duration_minutes' => 60,
        'concern' => 'Grinding noise when braking',
    ])->assertRedirect();

    $request->refresh();
    $appointment = Appointment::query()->sole();
    expect($request->status)->toBe(AppointmentRequestStatus::Scheduled)
        ->and($request->appointment_id)->toBe($appointment->id)
        ->and($request->decided_by_user_id)->toBe($this->admin->id)
        ->and($request->lead->state)->toBe(LeadState::Scheduled)
        ->and(ShopDisplayTimezone::format($appointment->starts_at, 'Y-m-d H:i'))->toBe('2026-11-10 13:00');

    Event::assertDispatched(AppointmentRequestDecided::class);

    $this->actingAs($this->admin)->get(route('operations.appointments.index', ['day' => '2026-11-10']))
        ->assertDontSee('Online requests waiting');
});

test('staff can decline a request and it closes the lead', function (): void {
    $this->post('/book', bookingPost())->assertStatus(303);
    $request = AppointmentRequest::query()->sole();

    $this->actingAs($this->admin)->post(route('operations.appointment-requests.decline', $request), ['reason' => 'Fully booked'])
        ->assertRedirect();

    $request->refresh();
    expect($request->status)->toBe(AppointmentRequestStatus::Declined)
        ->and($request->decline_reason)->toBe('Fully booked')
        ->and($request->lead->state)->toBe(LeadState::Lost);

    $this->actingAs($this->admin)->post(route('operations.appointment-requests.decline', $request))
        ->assertSessionHas('status', 'That request was already handled.');
});

test('closed dates and closed weekdays are not offered and cannot be submitted', function (): void {
    AppointmentRequestException::query()->create(['date' => '2026-11-11', 'mode' => 'disable', 'reason' => 'Veterans Day']);

    $json = $this->getJson('/book/availability')->assertOk()->json();
    $dates = array_column($json['dates'], 'date');

    expect($dates)->not->toContain('2026-11-11')
        ->and($dates)->not->toContain('2026-11-14')
        ->and($dates)->toContain('2026-11-12');

    $this->post('/book', bookingPost(['preferred_date' => '2026-11-11']))->assertStatus(422)->assertSee('That day is no longer available.');
    $this->post('/book', bookingPost(['preferred_date' => '2026-11-14']))->assertStatus(422);
    expect(AppointmentRequest::query()->count())->toBe(0);
});

test('a daily intake limit fills a day', function (): void {
    bookingSettings(['limits' => ['daily_requests' => 2]]);

    $this->post('/book', bookingPost(['contact_phone' => '719-555-0101']))->assertStatus(303);
    $this->post('/book', bookingPost(['contact_phone' => '719-555-0102']))->assertStatus(303);
    $this->post('/book', bookingPost(['contact_phone' => '719-555-0103']))->assertStatus(422)->assertSee('Fully booked');

    $day = collect($this->getJson('/book/availability')->json('dates'))->firstWhere('date', '2026-11-10');
    expect($day['available'])->toBeFalse()->and($day['reason'])->toBe('Fully booked');
});

test('a day at its scheduling target is not offered', function (): void {
    Appointment::query()->create([
        'contact_name' => 'Existing',
        'contact_phone' => '7195550199',
        'created_by_user_id' => $this->admin->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-11-10 08:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-11-10 16:30')->utc(),
        'estimated_labor_hours' => 8.5,
        'concern' => 'Engine swap',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $day = collect($this->getJson('/book/availability')->json('dates'))->firstWhere('date', '2026-11-10');
    expect($day['available'])->toBeFalse();

    bookingSettings(['limits' => ['respect_capacity' => false]]);
    $day = collect($this->getJson('/book/availability')->json('dates'))->firstWhere('date', '2026-11-10');
    expect($day['available'])->toBeTrue();
});

test('instant booking confirms only an open time, and never twice', function (): void {
    bookingSettings(['mode' => 'instant', 'limits' => ['concurrent_appointments' => 1]]);
    $oil = BookingService::query()->create(['name' => 'Oil change', 'duration_minutes' => 60, 'is_public' => true]);

    $slots = $this->getJson('/book/availability?service_id='.$oil->id.'&date=2026-11-10')->json('slots');
    expect(array_column($slots, 'value'))->toContain('08:00')->toContain('16:00')->not->toContain('16:30');

    $first = $this->post('/book', bookingPost(['service_id' => $oil->id, 'preferred_time' => '09:00', 'preferred_period' => null]));
    $first->assertStatus(303);

    $appointment = Appointment::query()->sole();
    $request = AppointmentRequest::query()->sole();
    expect($appointment->status)->toBe(AppointmentStatus::Scheduled)
        ->and($appointment->created_by_user_id)->toBeNull()
        ->and($request->status)->toBe(AppointmentRequestStatus::Scheduled)
        ->and($request->appointment_id)->toBe($appointment->id)
        ->and($request->lead->state)->toBe(LeadState::Scheduled)
        ->and(ShopDisplayTimezone::format($appointment->starts_at, 'H:i'))->toBe('09:00');

    $this->get($first->headers->get('Location'))->assertSee('Your appointment is booked');

    $this->post('/book', bookingPost(['service_id' => $oil->id, 'preferred_time' => '09:00', 'contact_phone' => '719-555-0177']))
        ->assertStatus(422)
        ->assertSee('That time was just taken.');
    $this->post('/book', bookingPost(['service_id' => $oil->id, 'preferred_time' => '09:30', 'contact_phone' => '719-555-0177']))
        ->assertStatus(422);
    expect(Appointment::query()->count())->toBe(1);

    $slots = array_column($this->getJson('/book/availability?service_id='.$oil->id.'&date=2026-11-10')->json('slots'), 'value');
    expect($slots)->not->toContain('09:00')->not->toContain('08:30')->toContain('10:00');
});

test('a service that needs approval stays a request even when the shop takes instant bookings', function (): void {
    bookingSettings(['mode' => 'instant']);
    $diag = BookingService::query()->create(['name' => 'Diagnostics', 'duration_minutes' => 60, 'is_public' => true, 'requires_approval' => true]);

    expect($this->getJson('/book/availability?service_id='.$diag->id)->json('mode'))->toBe('request');

    $this->post('/book', bookingPost(['service_id' => $diag->id, 'preferred_time' => '09:00']))->assertStatus(303);

    expect(Appointment::query()->count())->toBe(0)
        ->and(AppointmentRequest::query()->sole()->status)->toBe(AppointmentRequestStatus::Pending);
});

test('mobile service requires an address inside the service area and carries it to the appointment', function (): void {
    bookingSettings(['service_area' => ['postal_codes' => ['80903', '80904'], 'message' => 'We only drive to central Springs.']]);
    $mobile = BookingService::query()->create(['name' => 'Mobile battery swap', 'duration_minutes' => 60, 'is_public' => true, 'location_mode' => 'mobile']);

    $this->post('/book', bookingPost(['service_id' => $mobile->id]))->assertStatus(422)->assertSee('street address');

    $this->post('/book', bookingPost(['service_id' => $mobile->id, 'address' => ['line1' => '1 Far Rd', 'city' => 'Pueblo', 'postal_code' => '81001']]))
        ->assertStatus(422)
        ->assertSee('We only drive to central Springs.');

    $this->post('/book', bookingPost(['service_id' => $mobile->id, 'address' => ['line1' => '12 Pine St', 'city' => 'Colorado Springs', 'state' => 'CO', 'postal_code' => '80903', 'notes' => 'Blue gate']]))
        ->assertStatus(303);

    $request = AppointmentRequest::query()->sole();
    expect($request->locationType())->toBe(ServiceLocationType::CustomerSite)
        ->and($request->service_address['postal_code'])->toBe('80903')
        ->and($request->staffNotes())->toContain('Blue gate');

    $this->actingAs($this->admin)->post(route('operations.appointments.store'), [
        'lead_id' => $request->lead_id,
        'contact_name' => 'Pat Example',
        'contact_phone' => '719-555-0142',
        'starts_date' => '2026-11-10',
        'starts_time' => '10:00',
        'duration_minutes' => 60,
        'concern' => 'Battery',
    ])->assertRedirect();

    $appointment = Appointment::query()->sole();
    expect($appointment->service_location_type)->toBe(ServiceLocationType::CustomerSite)
        ->and($appointment->service_address['line1'])->toBe('12 Pine St')
        ->and($request->refresh()->status)->toBe(AppointmentRequestStatus::Scheduled);

    $this->actingAs($this->admin)->get(route('operations.appointments.show', $appointment))
        ->assertSee('At the customer&#039;s location', false)
        ->assertSee('12 Pine St');
});

test('a fleet can request several vehicles in one visit and the workload scales', function (): void {
    bookingSettings(['max_vehicles' => 3]);
    $pm = BookingService::query()->create(['name' => 'Fleet PM', 'duration_minutes' => 60, 'labor_hours' => 1.5, 'is_public' => true, 'location_mode' => 'either']);

    $this->post('/book', bookingPost([
        'service_id' => $pm->id,
        'location' => 'fleet_site',
        'address' => ['line1' => '400 Yard Way', 'city' => 'Colorado Springs', 'postal_code' => '80915'],
        'vehicles' => [
            ['year' => 2020, 'make' => 'Ford', 'model' => 'Transit'],
            ['year' => 2021, 'make' => 'Ford', 'model' => 'Transit'],
            ['year' => 2019, 'make' => 'Ram', 'model' => 'ProMaster', 'vin' => '3c6trvdg0ke500001'],
        ],
    ]))->assertStatus(303);

    $request = AppointmentRequest::query()->sole();
    expect($request->vehicle_count)->toBe(3)
        ->and((float) $request->workload_hours)->toBe(4.5)
        ->and($request->duration_minutes)->toBe(180)
        ->and($request->locationType())->toBe(ServiceLocationType::FleetSite)
        ->and($request->vehicleLabels()[2])->toContain('VIN 3C6TRVDG0KE500001');
});

test('a resubmitted form or a second request for the same day saves one request', function (): void {
    $payload = bookingPost(['submission_key' => str_repeat('a', 40)]);
    $this->post('/book', $payload)->assertStatus(303);
    $this->post('/book', $payload)->assertStatus(303);
    $this->post('/book', bookingPost())->assertStatus(303);

    expect(AppointmentRequest::query()->count())->toBe(1)
        ->and(Lead::query()->count())->toBe(1);
});

test('spam is caught without creating a request', function (): void {
    $this->post('/book', bookingPost(['company_website' => 'https://spam.example']))->assertStatus(303);
    expect(Lead::query()->count())->toBe(0);

    $this->post('/book', bookingPost(['form_rendered_at' => '']))->assertStatus(303);
    expect(AppointmentRequest::query()->count())->toBe(0)
        ->and(Lead::query()->sole()->state)->toBe(LeadState::Spam);

    $this->post('/book', bookingPost(['form_rendered_at' => LeadFormRenderStamp::issue(now())]))
        ->assertStatus(422)
        ->assertSee('Wait a few seconds');
});

test('online booking can be turned off', function (): void {
    bookingSettings(['enabled' => false]);

    $this->get('/book')->assertOk()->assertSee('Online booking is not available right now.')->assertDontSee('data-booking-form', false);
    $this->post('/book', bookingPost())->assertStatus(422);
    expect(AppointmentRequest::query()->count())->toBe(0);
});

test('the popup page may only be framed by the shop and its listed websites', function (): void {
    bookingSettings(['popup' => ['allowed_domains' => ['lugsdemo-site.example']]]);

    $embed = $this->get('/book?embed=1')->assertOk();
    expect($embed->headers->get('Content-Security-Policy'))
        ->toBe("frame-ancestors 'self' https://lugsdemo-site.example https://www.lugsdemo-site.example")
        ->and($embed->headers->has('X-Frame-Options'))->toBeFalse();
    $embed->assertDontSee('customer-shell', false)->assertSee('name="embed" value="1"', false);

    expect($this->get('/book')->headers->get('Content-Security-Policy'))->toBe("frame-ancestors 'self'");

    $script = $this->get('/book/popup.js')->assertOk();
    expect($script->headers->get('Content-Type'))->toContain('javascript')
        ->and($script->getContent())->toContain('"allowedHosts":["lugsdemo-site.example"]')
        ->and($script->getContent())->toContain('ark-book-dialog');
});

test('a host can add an availability rule such as travel time', function (): void {
    app()->bind('test.travel', fn () => new class implements BookingAvailabilityConstraint
    {
        public function refuse(BookingCandidate $candidate): ?string
        {
            return $candidate->date === '2026-11-12' ? 'No technician near you that day' : null;
        }
    });
    app()->tag(['test.travel'], BookingConstraints::TAG);

    $day = collect($this->getJson('/book/availability')->json('dates'))->firstWhere('date', '2026-11-12');
    expect($day['available'])->toBeFalse()->and($day['reason'])->toBe('No technician near you that day');

    $this->post('/book', bookingPost(['preferred_date' => '2026-11-12']))->assertStatus(422)->assertSee('No technician near you that day');
});

test('settings manage services, questions, and closed dates', function (): void {
    $this->actingAs($this->admin)->post(route('operations.settings.booking.services.store'), [
        'name' => 'Mobile diagnostic',
        'duration_minutes' => 90,
        'location_mode' => 'mobile',
        'is_public' => '1',
        'requires_approval' => '1',
        'options' => [['label' => 'Check engine light', 'duration_minutes' => 60, 'requires_details' => '1'], ['label' => '']],
    ])->assertRedirect(route('operations.settings.booking', ['tab' => 'services']));

    $service = BookingService::query()->sole();
    expect($service->slug)->toBe('mobile-diagnostic')
        ->and($service->requires_approval)->toBeTrue()
        ->and($service->optionList())->toHaveCount(1);

    $this->actingAs($this->admin)->post(route('operations.settings.booking.questions.store'), [
        'label' => 'Where is it parked?', 'type' => 'choice', 'choices' => "Driveway\nStreet", 'required' => '1', 'service_ids' => [$service->id],
    ])->assertRedirect();
    expect(BookingQuestion::query()->sole()->choiceList())->toBe(['Driveway', 'Street']);

    $this->actingAs($this->admin)->post(route('operations.settings.booking.closed-dates.store'), ['date' => '2026-11-26', 'reason' => 'Thanksgiving'])->assertRedirect();
    expect(AppointmentRequestException::query()->sole()->disablesRequests())->toBeTrue();

    $this->actingAs($this->admin)->get(route('operations.settings.booking', ['tab' => 'availability']))->assertOk()->assertSee('Thanksgiving');
    $this->actingAs($this->admin)->get(route('operations.settings.booking', ['tab' => 'services']))->assertOk()->assertSee('Mobile diagnostic');

    $this->actingAs($this->admin)->delete(route('operations.settings.booking.services.destroy', $service))->assertRedirect();
    $this->get('/book')->assertDontSee('Mobile diagnostic');
});

test('staff without settings access cannot change online booking', function (): void {
    $tech = User::factory()->create()->assignRole(ArkRole::Technician->value);

    $this->actingAs($tech)->get(route('operations.settings.booking'))->assertRedirect();
    $this->actingAs($tech)->patch(route('operations.settings.booking.update'), ['section' => 'form', 'enabled' => '0'])->assertRedirect();

    expect(BookingSettings::forShop()['enabled'])->toBeTrue();
});
