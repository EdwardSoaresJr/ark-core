<?php

use App\Ark\Operations\Appointments\AppointmentRequestAvailability;
use App\Ark\Operations\Appointments\SchedulingHours;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    ShopSettings::forgetCurrent();
    ShopSettings::current()->update([
        'shop_timezone' => 'America/Denver',
        'appointments_enabled' => true,
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
    ]);
    ShopSettings::forgetCurrent();

    Carbon::setTestNow(Carbon::parse('2026-07-24 10:00:00', 'America/Denver'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('request availability defaults seed from scheduling hours when unset', function (): void {
    ShopSettings::current()->update(['appointment_request_availability' => null]);
    ShopSettings::forgetCurrent();

    $config = AppointmentRequestAvailability::forShop();

    expect($config['weekly']['saturday']['enabled'])->toBeFalse()
        ->and($config['weekly']['monday']['enabled'])->toBeTrue()
        ->and($config['horizon_days'])->toBe(14);
});

test('settings can save appointment request availability weekly defaults', function (): void {
    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.appointments.update'), [
            'appointments_enabled' => '1',
            'appointment_slot_minutes' => 30,
            'appointment_capacity_basis' => 'limiting_resource',
            'appointment_scheduling_target_percent' => 100,
            'appointment_capacity_enforcement' => 'warn',
            'appointment_request_availability' => [
                'weekly' => [
                    'monday' => ['enabled' => '1'],
                    'tuesday' => ['enabled' => '1'],
                    'wednesday' => ['enabled' => '0'],
                    'thursday' => ['enabled' => '1'],
                    'friday' => ['enabled' => '1'],
                    'saturday' => ['enabled' => '0'],
                    'sunday' => ['enabled' => '0'],
                ],
                'horizon_days' => '7',
                'horizon_is_custom' => '0',
                'minimum_notice_days' => '1',
            ],
        ])
        ->assertRedirect();

    ShopSettings::forgetCurrent();
    $config = AppointmentRequestAvailability::forShop();

    expect($config['weekly']['wednesday']['enabled'])->toBeFalse()
        ->and($config['weekly']['monday']['enabled'])->toBeTrue()
        ->and($config['horizon_days'])->toBe(7)
        ->and($config['minimum_notice_days'])->toBe(1);
});

test('request availability saturday can stay closed independently of shop hours config shape', function (): void {
    $config = AppointmentRequestAvailability::forShop();

    expect($config['weekly']['saturday']['enabled'])->toBeFalse();
});
