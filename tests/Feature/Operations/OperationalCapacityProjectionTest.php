<?php

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Appointments\OperationalCapacityProjection;
use App\Ark\Operations\Appointments\SchedulingHours;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Runtime\Authorization\ArkRole;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    ShopSettings::current()->update([
        'appointments_enabled' => true,
        'scheduling_hours' => SchedulingHours::defaultWeekly(),
        'appointment_capacity_basis' => 'technicians',
        'appointment_scheduling_target_percent' => 100,
        'appointment_capacity_enforcement' => 'warn',
    ]);
    ShopSettings::forgetCurrent();
    $this->seed(ArkAuthorizationSeeder::class);
});

test('capacity projection reports soft shop capacity on the rail', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();
    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);

    $customer = Customer::query()->create([
        'first_name' => 'Load',
        'last_name' => 'Test',
        'phone' => '555-0400',
    ]);

    Appointment::query()->create([
        'customer_id' => $customer->id,
        'created_by_user_id' => $advisor->id,
        'technician_user_id' => $technician->id,
        'starts_at' => Carbon::parse('2026-06-10 08:00:00'),
        'ends_at' => Carbon::parse('2026-06-10 12:00:00'),
        'estimated_labor_hours' => 8.5,
        'concern' => 'Heavy day',
        'status' => AppointmentStatus::Confirmed,
    ]);

    $rail = app(OperationalCapacityProjection::class)->resolve(Carbon::parse('2026-06-10'), 'day');

    expect($rail['ready'])->toBeTrue()
        ->and($rail['shop']['available'])->toBeTrue()
        ->and($rail['shop']['scheduled_hours'])->toBe(8.5)
        ->and($rail['shop']['base_hours'])->toBe(9.0)
        ->and($rail['shop']['status'])->toBe('below_base')
        ->and($rail['warnings'])->toBeEmpty();

    $this->actingAs($advisor)
        ->get(route('operations.appointments.index', ['day' => '2026-06-10', 'view' => 'day']))
        ->assertOk()
        ->assertSee('Capacity', false)
        ->assertSee('8.5 hrs', false);

    Carbon::setTestNow();
});

test('capacity projection shows unavailable when no resources exist', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));

    $rail = app(OperationalCapacityProjection::class)->resolve(Carbon::parse('2026-06-10'), 'day');

    expect($rail['shop']['available'])->toBeFalse()
        ->and($rail['warnings'][0] ?? '')->toContain('Capacity unavailable');

    Carbon::setTestNow();
});

test('capacity projection marks beyond target when overpacked past percent', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();
    actingAsLearnCurrentStaff(ArkRole::Technician);
    ShopSettings::current()->update([
        'appointment_scheduling_target_percent' => 100,
    ]);
    ShopSettings::forgetCurrent();

    $customer = Customer::query()->create([
        'first_name' => 'Beyond',
        'last_name' => 'Target',
        'phone' => '555-0402',
    ]);

    Appointment::query()->create([
        'customer_id' => $customer->id,
        'created_by_user_id' => $advisor->id,
        'starts_at' => Carbon::parse('2026-06-10 08:00:00'),
        'ends_at' => Carbon::parse('2026-06-10 12:00:00'),
        'estimated_labor_hours' => 12,
        'concern' => 'Over target',
        'status' => AppointmentStatus::Confirmed,
    ]);

    $rail = app(OperationalCapacityProjection::class)->resolve(Carbon::parse('2026-06-10'), 'day');

    expect($rail['shop']['status'])->toBe('beyond_target')
        ->and($rail['warnings'])->not->toBeEmpty();

    Carbon::setTestNow();
});
