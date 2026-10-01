<?php

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentExpectationFormatter;
use App\Ark\Operations\Appointments\AppointmentRequestAvailability;
use App\Ark\Operations\Appointments\AppointmentSmsCopy;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Appointments\ScheduleRequestWindows;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadRecorder;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\RepairOrders\RepairOrderVisitMode;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);

    ShopSettings::current()->update([
        'appointments_enabled' => true,
        'shop_timezone' => 'America/Denver',
        'shop_name' => 'Demo Auto Repair',
        'appointment_slot_minutes' => 30,
        'scheduling_hours' => [
            'monday' => ['enabled' => true, 'open' => '08:00', 'close' => '18:00'],
            'tuesday' => ['enabled' => true, 'open' => '08:00', 'close' => '18:00'],
            'wednesday' => ['enabled' => true, 'open' => '08:00', 'close' => '18:00'],
            'thursday' => ['enabled' => true, 'open' => '08:00', 'close' => '18:00'],
            'friday' => ['enabled' => true, 'open' => '08:00', 'close' => '18:00'],
            'saturday' => ['enabled' => false, 'open' => '08:00', 'close' => '12:00'],
            'sunday' => ['enabled' => false, 'open' => '08:00', 'close' => '12:00'],
        ],
        'appointment_request_availability' => null,
    ]);
    ShopSettings::forgetCurrent();
});

test('appointments table has no arrival_type column', function () {
    expect(Schema::hasColumn('appointments', 'arrival_type'))->toBeFalse();
});

test('exact appointment remains clock-confirmed in SMS', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Ada',
        'last_name' => 'Patron',
        'phone' => '7195550199',
    ]);

    $starts = ShopDisplayTimezone::parseLocal('2026-09-11 13:00')->utc();
    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id,
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => $starts,
        'ends_at' => $starts->copy()->addHour(),
        'concern' => 'Brakes',
        'status' => AppointmentStatus::Scheduled,
    ]);

    expect(AppointmentExpectationFormatter::confirmedWhenLabel($appointment))
        ->toContain('at 1:00 PM')
        ->not->toContain('afternoon');

    expect(AppointmentSmsCopy::confirmation($appointment))
        ->toContain('at 1:00 PM')
        ->not->toContain('afternoon');
});

test('visit-mode arrival_type on schedule rows remains RepairOrderVisitMode', function () {
    expect(RepairOrderVisitMode::WaitingHere->value)->toBe('waiting_here')
        ->and(RepairOrderVisitMode::DropOff->label())->not->toBeEmpty();
});

test('default request windows are morning 9-12 and afternoon 12-4', function () {
    $windows = ScheduleRequestWindows::defaults();

    expect($windows['morning']['open'])->toBe('09:00')
        ->and($windows['morning']['close'])->toBe('12:00')
        ->and($windows['afternoon']['open'])->toBe('12:00')
        ->and($windows['afternoon']['close'])->toBe('16:00')
        ->and($windows['flexible_enabled'])->toBeTrue()
        ->and($windows['latest_appointment_arrival'])->toBeNull();
});

test('latest appointment arrival is optional and off by default', function () {
    expect(ScheduleRequestWindows::forShop()['latest_appointment_arrival'])->toBeNull();

    $afternoonOptions = ScheduleRequestWindows::timeOptionsForPeriod('afternoon');
    expect($afternoonOptions)->toHaveKey('12:00')
        ->and($afternoonOptions)->toHaveKey('15:30')
        ->and($afternoonOptions)->not->toHaveKey('16:00');

    // Without a cutoff, override path still offers later times via full slot list.
    $all = \App\Ark\Operations\Appointments\AppointmentSlotMinutes::timeOptions(30);
    expect($all)->toHaveKey('17:00');
});

test('explicit latest arrival cutoff filters preference time options when enabled', function () {
    ShopSettings::current()->update([
        'appointment_request_availability' => AppointmentRequestAvailability::normalize([
            'request_windows' => [
                'morning' => ['enabled' => true, 'open' => '09:00', 'close' => '12:00'],
                'afternoon' => ['enabled' => true, 'open' => '12:00', 'close' => '16:00'],
                'flexible_enabled' => true,
                'latest_appointment_arrival' => '15:00',
            ],
        ], ShopSettings::current()->schedulingHours()),
    ]);
    ShopSettings::forgetCurrent();

    $options = ScheduleRequestWindows::timeOptionsForPeriod('afternoon');
    expect($options)->toHaveKey('14:30')
        ->and($options)->not->toHaveKey('15:30')
        ->and(ScheduleRequestWindows::forShop()['latest_appointment_arrival'])->toBe('15:00');
});

test('shop can change and disable request periods', function () {
    ShopSettings::current()->update([
        'appointment_request_availability' => AppointmentRequestAvailability::normalize([
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
            'request_windows' => [
                'morning' => ['enabled' => false, 'open' => '08:00', 'close' => '11:00'],
                'afternoon' => ['enabled' => true, 'open' => '13:00', 'close' => '17:00'],
                'flexible_enabled' => false,
                'latest_appointment_arrival' => '16:00',
            ],
        ], ShopSettings::current()->schedulingHours()),
    ]);
    ShopSettings::forgetCurrent();

    $windows = ScheduleRequestWindows::forShop();
    $periods = AppointmentRequestAvailability::enabledPeriodOptions();

    expect($windows['morning']['enabled'])->toBeFalse()
        ->and($windows['morning']['open'])->toBe('08:00')
        ->and($windows['afternoon']['open'])->toBe('13:00')
        ->and($windows['afternoon']['close'])->toBe('17:00')
        ->and($windows['flexible_enabled'])->toBeFalse()
        ->and(collect($periods)->pluck('value')->all())->toBe(['afternoon']);
});

test('lead preferred periods stay on the request side', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    foreach (['morning', 'afternoon', 'any'] as $period) {
        $lead = app(LeadRecorder::class)->recordWebsiteSubmission([
            'concern' => 'Noise',
            'contact_phone' => '7195550'.substr(crc32($period), 0, 3),
            'contact_name' => 'Sam Patron',
            'source' => LeadSource::Website,
            'preferred_period' => $period,
        ], $advisor, forcedState: LeadState::Received);

        expect($lead->preferredPeriod())->toBe($period)
            ->and(Schema::hasColumn('appointments', 'arrival_type'))->toBeFalse();
    }
});

test('afternoon preference filters time options to the request window without inventing starts_at', function () {
    $options = ScheduleRequestWindows::timeOptionsForPeriod('afternoon', '2026-09-11');

    expect($options)->toHaveKey('12:00')
        ->and($options)->toHaveKey('15:30')
        ->and($options)->not->toHaveKey('11:30')
        ->and($options)->not->toHaveKey('16:00'); // half-open end + latest arrival default
});

test('creating an appointment from a preference still requires an exact starts_time', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Pat',
        'last_name' => 'Customer',
        'phone' => '7195550300',
    ]);
    $bay = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::current()->id,
        'name' => 'Bay 1',
        'is_active' => true,
        'accepts_scheduled_work' => true,
    ]);

    $this->actingAs($advisor)
        ->from(route('operations.appointments.create', ['customer' => $customer->id, 'preferred_period' => 'afternoon']))
        ->post(route('operations.appointments.store'), [
            'customer_id' => $customer->id,
            'advisor_user_id' => $advisor->id,
            'workstation_id' => $bay->id,
            'preferred_period' => 'afternoon',
            'starts_date' => '2026-09-11',
            'starts_time' => '',
            'duration_minutes' => 60,
            'concern' => 'Oil change',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors();

    expect(Appointment::query()->count())->toBe(0);

    $this->actingAs($advisor)
        ->post(route('operations.appointments.store'), [
            'customer_id' => $customer->id,
            'advisor_user_id' => $advisor->id,
            'workstation_id' => $bay->id,
            'preferred_period' => 'afternoon',
            'starts_date' => '2026-09-11',
            'starts_time' => '13:00',
            'duration_minutes' => 60,
            'concern' => 'Oil change',
        ])
        ->assertRedirect();

    $appointment = Appointment::query()->sole();
    expect(ShopDisplayTimezone::present($appointment->starts_at)->format('H:i'))->toBe('13:00')
        ->and(AppointmentSmsCopy::confirmation($appointment))->toContain('at 1:00 PM')
        ->and(AppointmentSmsCopy::confirmation($appointment))->not->toContain('afternoon');
});

test('staff may choose a time outside the preferred window', function () {
    expect(ScheduleRequestWindows::isTimeOutsidePreferredPeriod('10:00', 'afternoon'))->toBeTrue()
        ->and(ScheduleRequestWindows::isTimeOutsidePreferredPeriod('13:00', 'afternoon'))->toBeFalse();
});

test('request formatter uses preferred-period language', function () {
    expect(AppointmentExpectationFormatter::requestedLabel('2026-09-11', 'afternoon'))
        ->toContain('afternoon')
        ->and(AppointmentExpectationFormatter::requestedDetail('2026-09-11', 'afternoon'))
        ->toContain('12:00 PM')
        ->toContain('4:00 PM');
});

test('invalid request window is rejected on settings save', function () {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->from(route('operations.settings.shop.edit', ['section' => 'operations']))
        ->patch(route('operations.settings.shop.appointments.update'), [
            'appointments_enabled' => '1',
            'appointment_slot_minutes' => 30,
            'appointment_capacity_basis' => 'limiting_resource',
            'appointment_scheduling_target_percent' => 100,
            'appointment_capacity_enforcement' => 'warn',
            'scheduling_hours_follow_shop' => '1',
            'appointment_request_availability' => [
                'weekly' => [
                    'monday' => ['enabled' => '1'],
                    'tuesday' => ['enabled' => '1'],
                    'wednesday' => ['enabled' => '1'],
                    'thursday' => ['enabled' => '1'],
                    'friday' => ['enabled' => '1'],
                    'saturday' => ['enabled' => '0'],
                    'sunday' => ['enabled' => '0'],
                ],
                'horizon_days' => 14,
                'minimum_notice_days' => 0,
                'request_windows' => [
                    'morning' => ['enabled' => '1', 'open' => '12:00', 'close' => '09:00'],
                    'afternoon' => ['enabled' => '1', 'open' => '12:00', 'close' => '16:00'],
                    'flexible_enabled' => '1',
                    'latest_appointment_arrival' => '16:00',
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('appointment_request_availability.request_windows');
});

test('settings round-trip stores request_windows', function () {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.appointments.update'), [
            'appointments_enabled' => '1',
            'appointment_slot_minutes' => 30,
            'appointment_capacity_basis' => 'limiting_resource',
            'appointment_scheduling_target_percent' => 100,
            'appointment_capacity_enforcement' => 'warn',
            'scheduling_hours_follow_shop' => '0',
            'scheduling_hours' => [
                'monday' => ['enabled' => '1', 'open' => '08:00', 'close' => '18:00'],
                'tuesday' => ['enabled' => '1', 'open' => '08:00', 'close' => '18:00'],
                'wednesday' => ['enabled' => '1', 'open' => '08:00', 'close' => '18:00'],
                'thursday' => ['enabled' => '1', 'open' => '08:00', 'close' => '18:00'],
                'friday' => ['enabled' => '1', 'open' => '08:00', 'close' => '18:00'],
                'saturday' => ['enabled' => '0', 'open' => '08:00', 'close' => '12:00'],
                'sunday' => ['enabled' => '0', 'open' => '08:00', 'close' => '12:00'],
            ],
            'appointment_request_availability' => [
                'weekly' => [
                    'monday' => ['enabled' => '1'],
                    'tuesday' => ['enabled' => '1'],
                    'wednesday' => ['enabled' => '1'],
                    'thursday' => ['enabled' => '1'],
                    'friday' => ['enabled' => '1'],
                    'saturday' => ['enabled' => '0'],
                    'sunday' => ['enabled' => '0'],
                ],
                'horizon_days' => 14,
                'minimum_notice_days' => 0,
                'request_windows' => [
                    'morning' => ['enabled' => '1', 'open' => '08:30', 'close' => '11:30'],
                    'afternoon' => ['enabled' => '1', 'open' => '12:30', 'close' => '16:00'],
                    'flexible_enabled' => '1',
                    'latest_appointment_arrival_enabled' => '1',
                    'latest_appointment_arrival' => '16:00',
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    ShopSettings::forgetCurrent();
    $windows = ScheduleRequestWindows::forShop();

    expect($windows['morning']['open'])->toBe('08:30')
        ->and($windows['morning']['close'])->toBe('11:30')
        ->and($windows['afternoon']['open'])->toBe('12:30')
        ->and($windows['latest_appointment_arrival'])->toBe('16:00');
});
