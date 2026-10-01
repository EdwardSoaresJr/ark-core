<?php

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Appointments\UpcomingScheduleProjection;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Settings\ShopSettings;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    ShopSettings::current()->update(['appointments_enabled' => true]);
    $this->seed(ArkAuthorizationSeeder::class);
});

test('upcoming schedule groups appointments by day bucket', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();

    $customer = Customer::query()->create([
        'first_name' => 'Schedule',
        'last_name' => 'Customer',
        'phone' => '555-0100',
    ]);

    Appointment::query()->create([
        'customer_id' => $customer->id,
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => Carbon::parse('2026-06-10 09:00:00'),
        'ends_at' => Carbon::parse('2026-06-10 10:00:00'),
        'concern' => 'Today visit',
        'status' => AppointmentStatus::Scheduled,
    ]);

    Appointment::query()->create([
        'customer_id' => $customer->id,
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => Carbon::parse('2026-06-11 09:00:00'),
        'ends_at' => Carbon::parse('2026-06-11 10:00:00'),
        'concern' => 'Tomorrow visit',
        'status' => AppointmentStatus::Scheduled,
    ]);

    Appointment::query()->create([
        'customer_id' => $customer->id,
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => Carbon::parse('2026-06-13 09:00:00'),
        'ends_at' => Carbon::parse('2026-06-13 10:00:00'),
        'concern' => 'Later visit',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $projection = app(UpcomingScheduleProjection::class)->resolve(viewer: $advisor);

    expect($projection['total_count'])->toBe(3)
        ->and($projection['today'])->toHaveCount(1)
        ->and($projection['tomorrow'])->toHaveCount(1)
        ->and($projection['upcoming'])->toHaveCount(1)
        ->and($projection['today'][0]['concern'])->toBe('Today visit')
        ->and($projection['tomorrow'][0]['concern'])->toBe('Tomorrow visit')
        ->and($projection['upcoming'][0]['concern'])->toBe('Later visit');

    Carbon::setTestNow();
});

test('upcoming schedule puts viewer appointments first within each bucket', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    $viewer = actingAsLearnCurrentAdvisor();
    $otherAdvisor = \App\Models\User::factory()->create();

    $customer = Customer::query()->create([
        'first_name' => 'Shared',
        'last_name' => 'Schedule',
        'phone' => '555-0101',
    ]);

    Appointment::query()->create([
        'customer_id' => $customer->id,
        'created_by_user_id' => $otherAdvisor->id,
        'advisor_user_id' => $otherAdvisor->id,
        'starts_at' => Carbon::parse('2026-06-10 08:00:00'),
        'ends_at' => Carbon::parse('2026-06-10 09:00:00'),
        'concern' => 'Other advisor first thing',
        'status' => AppointmentStatus::Scheduled,
    ]);

    Appointment::query()->create([
        'customer_id' => $customer->id,
        'created_by_user_id' => $viewer->id,
        'advisor_user_id' => $viewer->id,
        'starts_at' => Carbon::parse('2026-06-10 11:00:00'),
        'ends_at' => Carbon::parse('2026-06-10 12:00:00'),
        'concern' => 'Viewer later slot',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $projection = app(UpcomingScheduleProjection::class)->resolve(viewer: $viewer);

    expect($projection['today'][0]['concern'])->toBe('Viewer later slot')
        ->and($projection['today'][0]['is_mine'])->toBeTrue()
        ->and($projection['today'][1]['concern'])->toBe('Other advisor first thing');

    Carbon::setTestNow();
});
