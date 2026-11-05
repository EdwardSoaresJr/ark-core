<?php

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentRequestException;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Appointments\SchedulingHours;
use App\Ark\Operations\Booking\AppointmentRequest;
use App\Ark\Operations\Booking\AppointmentRequestStatus;
use App\Ark\Operations\Booking\BookingAvailability;
use App\Ark\Operations\Booking\BookingConstraints;
use App\Ark\Operations\Booking\BookingContext;
use App\Ark\Operations\Booking\BookingMode;
use App\Ark\Operations\Booking\BookingRefused;
use App\Ark\Operations\Booking\BookingSettings;
use App\Ark\Operations\Booking\Contracts\AdvisoryBookingConstraint;
use App\Ark\Operations\Booking\Contracts\BookingAvailabilityConstraint;
use App\Ark\Operations\Booking\Contracts\BookingCandidate;
use App\Ark\Operations\Booking\Events\AppointmentRequestDecided;
use App\Ark\Operations\Booking\Events\AppointmentRequestReceived;
use App\Ark\Operations\Booking\OnlineBooking;
use App\Ark\Operations\Booking\OnlineBookingRequest;
use App\Ark\Operations\Booking\ServiceLocationType;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;

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

function bookingPolicy(array $changes): void
{
    $settings = ShopSettings::current();
    $settings->forceFill(['online_booking' => BookingSettings::normalize(array_replace_recursive(BookingSettings::forShop($settings), $changes))])->save();
    ShopSettings::forgetCurrent();
}

function brakesContext(array $overrides = []): BookingContext
{
    return new BookingContext(...array_merge([
        'serviceName' => 'Brakes',
        'option' => 'Rear',
        'perVehicleMinutes' => 120,
        'serviceReference' => 'svc-brakes',
    ], $overrides));
}

function onlineRequest(array $overrides = []): OnlineBookingRequest
{
    return new OnlineBookingRequest(...array_merge([
        'booking' => brakesContext(),
        'contactName' => 'Pat Example',
        'contactPhone' => '719-555-0142',
        'contactEmail' => 'pat@example.test',
        'preferredDate' => '2026-11-10',
        'preferredPeriod' => 'morning',
        'concern' => 'Grinding noise when braking',
        'vehicles' => [['year' => 2016, 'make' => 'Honda', 'model' => 'Civic']],
    ], $overrides));
}

function submitOnline(array $overrides = []): AppointmentRequest
{
    return app(OnlineBooking::class)->submit(onlineRequest($overrides))->request;
}

function refusal(array $overrides): BookingRefused
{
    try {
        submitOnline($overrides);
    } catch (BookingRefused $e) {
        return $e;
    }

    throw new RuntimeException('Expected the booking to be refused.');
}

test('a request waits for the shop, reserves nothing, and keeps what the booking product sent', function (): void {
    Event::fake([AppointmentRequestReceived::class]);

    $request = submitOnline([
        'answers' => [['label' => 'Under warranty?', 'answer' => 'Yes']],
        'context' => ['host' => 'shop.example.test', 'embedded' => true],
        'unsafeToDrive' => true,
    ]);

    expect($request->status)->toBe(AppointmentRequestStatus::Pending)
        ->and($request->mode)->toBe(BookingMode::Request)
        ->and($request->appointment_id)->toBeNull()
        ->and($request->serviceLabel())->toBe('Brakes · Rear')
        ->and($request->service_reference)->toBe('svc-brakes')
        ->and($request->duration_minutes)->toBe(120)
        ->and((float) $request->workload_hours)->toBe(2.0)
        ->and($request->preferred_date->toDateString())->toBe('2026-11-10')
        ->and($request->concern)->toContain('Brakes (Rear)')->toContain('Grinding noise')->toContain('Unsafe to drive')
        ->and($request->staffNotes())->toContain('Under warranty? Yes')
        ->and($request->context['host'])->toBe('shop.example.test')
        ->and($request->lead->state)->toBe(LeadState::Received)
        ->and(Appointment::query()->count())->toBe(0);

    Event::assertDispatched(AppointmentRequestReceived::class);
});

test('staff see the request on the schedule, schedule it, and the request is answered', function (): void {
    Event::fake([AppointmentRequestDecided::class]);
    $request = submitOnline();

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
    $request = submitOnline();

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

    $dates = array_column(app(OnlineBooking::class)->availability(brakesContext())['dates'], 'date');
    expect($dates)->not->toContain('2026-11-11')->not->toContain('2026-11-14')->toContain('2026-11-12');

    expect(refusal(['preferredDate' => '2026-11-11'])->getMessage())->toBe('That day is no longer available. Pick another day.')
        ->and(refusal(['preferredDate' => '2026-11-14'])->field)->toBe('preferred_date')
        ->and(AppointmentRequest::query()->count())->toBe(0);
});

test('a daily intake limit fills a day', function (): void {
    bookingPolicy(['limits' => ['daily_requests' => 2]]);

    submitOnline(['contactPhone' => '719-555-0101']);
    submitOnline(['contactPhone' => '719-555-0102']);
    expect(refusal(['contactPhone' => '719-555-0103'])->getMessage())->toBe('Fully booked');

    $day = collect(app(OnlineBooking::class)->availability(brakesContext())['dates'])->firstWhere('date', '2026-11-10');
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

    $day = fn () => collect(app(OnlineBooking::class)->availability(brakesContext())['dates'])->firstWhere('date', '2026-11-10');
    expect($day()['available'])->toBeFalse();

    bookingPolicy(['limits' => ['respect_capacity' => false]]);
    expect($day()['available'])->toBeTrue();
});

test('confirmed booking takes only an open time, and never twice', function (): void {
    bookingPolicy(['mode' => 'instant', 'limits' => ['concurrent_appointments' => 1]]);
    $oil = new BookingContext(serviceName: 'Oil change', perVehicleMinutes: 60);

    $slots = array_column(app(OnlineBooking::class)->availability($oil, '2026-11-10')['slots'], 'value');
    expect($slots)->toContain('08:00')->toContain('16:00')->not->toContain('16:30');

    $request = submitOnline(['booking' => $oil, 'preferredTime' => '09:00', 'preferredPeriod' => null]);
    $appointment = Appointment::query()->sole();
    expect($appointment->status)->toBe(AppointmentStatus::Scheduled)
        ->and($appointment->created_by_user_id)->toBeNull()
        ->and($request->status)->toBe(AppointmentRequestStatus::Scheduled)
        ->and($request->appointment_id)->toBe($appointment->id)
        ->and($request->lead->state)->toBe(LeadState::Scheduled)
        ->and(ShopDisplayTimezone::format($appointment->starts_at, 'H:i'))->toBe('09:00');

    expect(refusal(['booking' => $oil, 'preferredTime' => '09:00', 'contactPhone' => '719-555-0177'])->getMessage())->toBe('That time was just taken. Pick another time.');
    refusal(['booking' => $oil, 'preferredTime' => '09:30', 'contactPhone' => '719-555-0177']);
    expect(Appointment::query()->count())->toBe(1);

    $slots = array_column(app(OnlineBooking::class)->availability($oil, '2026-11-10')['slots'], 'value');
    expect($slots)->not->toContain('09:00')->not->toContain('08:30')->toContain('10:00');
});

test('a booking that needs approval stays a request even when the shop takes confirmed bookings', function (): void {
    bookingPolicy(['mode' => 'instant']);
    $diag = new BookingContext(serviceName: 'Diagnostics', perVehicleMinutes: 60, requiresApproval: true);

    expect(app(OnlineBooking::class)->availability($diag)['mode'])->toBe('request');

    $request = submitOnline(['booking' => $diag, 'preferredTime' => '09:00']);
    expect(Appointment::query()->count())->toBe(0)->and($request->status)->toBe(AppointmentRequestStatus::Pending);
});

test('on-site work must be inside the service area and carries its address to the appointment', function (): void {
    bookingPolicy(['service_area' => ['postal_codes' => ['80903', '80904'], 'message' => 'We only drive to central Springs.']]);
    $at = fn (string $zip) => new BookingContext(serviceName: 'Mobile battery swap', perVehicleMinutes: 60, location: ServiceLocationType::CustomerSite,
        address: ['line1' => '12 Pine St', 'city' => 'Colorado Springs', 'postal_code' => $zip, 'notes' => 'Blue gate']);

    expect(refusal(['booking' => $at('81001')])->getMessage())->toBe('We only drive to central Springs.');

    $request = submitOnline(['booking' => $at('80903')]);
    expect($request->locationType())->toBe(ServiceLocationType::CustomerSite)
        ->and($request->addressLabel())->toBe('12 Pine St, Colorado Springs, 80903')
        ->and($request->staffNotes())->toContain('Access notes: Blue gate');

    $this->actingAs($this->admin)->post(route('operations.appointments.store'), [
        'lead_id' => $request->lead_id,
        'appointment_request_id' => $request->id,
        'contact_name' => 'Pat Example',
        'contact_phone' => '719-555-0142',
        'starts_date' => '2026-11-10',
        'starts_time' => '09:00',
        'duration_minutes' => 60,
        'concern' => 'Battery',
    ])->assertRedirect();

    $appointment = Appointment::query()->sole();
    expect($appointment->service_location_type)->toBe(ServiceLocationType::CustomerSite)
        ->and($appointment->service_address['line1'])->toBe('12 Pine St');
});

test('a fleet visit scales its length and workload with the vehicle count', function (): void {
    $pm = new BookingContext(serviceName: 'Fleet PM', perVehicleMinutes: 60, perVehicleHours: 1.5, vehicleCount: 3, location: ServiceLocationType::FleetSite,
        address: ['line1' => '1 Yard Rd', 'city' => 'Pueblo', 'postal_code' => '81001']);

    $request = submitOnline(['booking' => $pm, 'vehicles' => [
        ['year' => 2019, 'make' => 'Ford', 'model' => 'Transit'],
        ['year' => 2020, 'make' => 'Ford', 'model' => 'Transit'],
        ['year' => 2021, 'make' => 'Ram', 'model' => 'ProMaster'],
    ]]);

    expect($request->vehicle_count)->toBe(3)
        ->and($request->duration_minutes)->toBe(180)
        ->and((float) $request->workload_hours)->toBe(4.5)
        ->and($request->vehicleLabels())->toHaveCount(3)
        ->and($request->staffNotes())->toContain('Vehicles (3)');
});

test('a resubmitted booking or a second request for the same day saves one request', function (): void {
    $first = submitOnline(['submissionKey' => 'abc123']);
    $again = submitOnline(['submissionKey' => 'abc123']);
    $sameDay = submitOnline(['concern' => 'Also the wipers']);

    expect($again->id)->toBe($first->id)->and($sameDay->id)->toBe($first->id)
        ->and(AppointmentRequest::query()->count())->toBe(1);
});

test('spam is recorded as a quiet lead without a request', function (): void {
    $outcome = app(OnlineBooking::class)->submit(onlineRequest(['spam' => true, 'spamSignals' => ['too_fast']]));

    expect($outcome->request)->toBeNull()
        ->and(AppointmentRequest::query()->count())->toBe(0)
        ->and(Lead::query()->sole()->state)->toBe(LeadState::Spam);
});

test('settings hold only the scheduling policy, and staff without settings access cannot change it', function (): void {
    $this->actingAs($this->admin)->get(route('operations.settings.booking'))->assertOk()
        ->assertSee('Online requests')
        ->assertSee('Requests or confirmed appointments')
        ->assertDontSee('Website popup');

    $this->actingAs($this->admin)->patch(route('operations.settings.booking.update'), [
        'mode' => 'instant', 'daily_requests' => 8, 'respect_capacity' => '1', 'service_area_postal_codes' => '80903 80904',
    ])->assertRedirect();

    expect(BookingSettings::forShop())->toBe([
        'mode' => 'instant',
        'service_area' => ['postal_codes' => ['80903', '80904'], 'message' => ''],
        'limits' => ['daily_requests' => 8, 'respect_capacity' => true, 'concurrent_appointments' => null],
    ]);

    $this->actingAs($this->admin)->post(route('operations.settings.booking.closed-dates.store'), ['date' => '2026-11-26', 'reason' => 'Thanksgiving'])->assertRedirect();
    expect(AppointmentRequestException::query()->sole()->disablesRequests())->toBeTrue();

    $tech = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $this->actingAs($tech)->patch(route('operations.settings.booking.update'), ['mode' => 'request'])->assertRedirect();
    expect(BookingSettings::mode())->toBe(BookingMode::Instant);
});

test('a host can add an availability rule such as travel time', function (): void {
    app()->bind('test.travel', fn () => new class implements BookingAvailabilityConstraint
    {
        public function refuse(BookingCandidate $candidate): ?string
        {
            return $candidate->date === '2026-11-12' ? 'No technician near you that day' : null;
        }

        public function label(): string
        {
            return 'Travel time';
        }
    });
    app()->tag(['test.travel'], BookingConstraints::TAG);

    $day = collect(app(OnlineBooking::class)->availability(brakesContext())['dates'])->firstWhere('date', '2026-11-12');
    expect($day['available'])->toBeFalse()->and($day['reason'])->toBe('No technician near you that day')
        ->and(refusal(['preferredDate' => '2026-11-12'])->getMessage())->toBe('No technician near you that day');
});

function failingTravelCheck(bool $advisory = false): object
{
    $check = $advisory
        ? new class implements AdvisoryBookingConstraint
        {
            public int $calls = 0;

            public function refuse(BookingCandidate $candidate): ?string
            {
                $this->calls++;

                throw new RuntimeException('Maps provider timed out');
            }

            public function label(): string
            {
                return 'Nearby technician';
            }
        }
    : new class implements BookingAvailabilityConstraint
    {
        public int $calls = 0;

        public function refuse(BookingCandidate $candidate): ?string
        {
            $this->calls++;

            throw new RuntimeException('Maps provider timed out');
        }

        public function label(): string
        {
            return 'Travel time';
        }
    };

    app()->instance('test.travel', $check);
    app()->tag(['test.travel'], BookingConstraints::TAG);

    return $check;
}

function mobileContext(): BookingContext
{
    return new BookingContext(serviceName: 'Mobile battery swap', perVehicleMinutes: 60, location: ServiceLocationType::CustomerSite,
        address: ['line1' => '12 Pine St', 'city' => 'Colorado Springs', 'state' => 'CO', 'postal_code' => '80903']);
}

test('when a required travel check cannot run, nothing is confirmed and the booking becomes a request', function (): void {
    Exceptions::fake();
    Log::spy();
    bookingPolicy(['mode' => 'instant']);
    $check = failingTravelCheck();

    $availability = app(OnlineBooking::class)->availability(mobileContext(), '2026-11-10');
    expect($availability['mode'])->toBe('request')
        ->and($availability['slots'])->toBe([])
        ->and($availability['periods'])->not->toBe([])
        ->and($availability['notice'])->toBe(BookingAvailability::UNCHECKED_NOTICE)
        ->and($check->calls)->toBe(1);

    $request = submitOnline(['booking' => mobileContext(), 'preferredTime' => '09:00', 'preferredPeriod' => null, 'concern' => 'Battery dead in the driveway']);

    expect(Appointment::query()->count())->toBe(0)
        ->and($request->status)->toBe(AppointmentRequestStatus::Pending)
        ->and($request->mode)->toBe(BookingMode::Request)
        ->and($request->preferred_period)->toBe('morning')
        ->and($request->requested_starts_at)->toBeNull()
        ->and($request->uncheckedRules())->toBe(['Travel time'])
        ->and($request->preferenceLabel())->toBe('Tue, Nov 10 · Morning, around 9:00 AM')
        ->and($request->staffNotes())->toContain('Not checked online: Travel time. Confirm before scheduling.');

    $this->actingAs($this->admin)->get(route('operations.appointments.index', ['day' => '2026-11-10']))
        ->assertOk()
        ->assertSee('Not checked online: Travel time');

    Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'Maps provider timed out');
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context = []): bool => $message === 'Online booking check could not run.' && $context['required'] === true);
});

test('a refusal from a working check still wins when another required check cannot run', function (): void {
    Exceptions::fake();
    bookingPolicy(['mode' => 'instant', 'service_area' => ['postal_codes' => ['80903'], 'message' => 'We only drive to central Springs.']]);
    failingTravelCheck();

    $far = new BookingContext(serviceName: 'Mobile battery swap', location: ServiceLocationType::CustomerSite, address: ['line1' => '1 Far Rd', 'city' => 'Pueblo', 'postal_code' => '81001']);
    expect(refusal(['booking' => $far, 'preferredTime' => '09:00'])->getMessage())->toBe('We only drive to central Springs.')
        ->and(AppointmentRequest::query()->count())->toBe(0);
});

test('an advisory check that cannot run does not stop a confirmed booking', function (): void {
    Exceptions::fake();
    bookingPolicy(['mode' => 'instant', 'limits' => ['concurrent_appointments' => 1]]);
    $check = failingTravelCheck(advisory: true);
    $oil = new BookingContext(serviceName: 'Oil change', perVehicleMinutes: 60);

    $availability = app(OnlineBooking::class)->availability($oil, '2026-11-10');
    expect($availability['mode'])->toBe('instant')->and($availability['notice'])->toBeNull()
        ->and(array_column($availability['slots'], 'value'))->toContain('09:00')
        ->and($check->calls)->toBe(1);

    $request = submitOnline(['booking' => $oil, 'preferredTime' => '09:00', 'preferredPeriod' => null]);
    expect(Appointment::query()->sole()->status)->toBe(AppointmentStatus::Scheduled)->and($request->uncheckedRules())->toBe([]);
});
