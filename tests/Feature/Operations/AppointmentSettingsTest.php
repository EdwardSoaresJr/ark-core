<?php

use App\Ark\Operations\Appointments\AppointmentSlotMinutes;
use App\Ark\Operations\OperationsFeatures;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    ShopSettings::forgetCurrent();
});

function appointmentSettingsPayload(array $overrides = []): array
{
    return array_merge([
        'appointments_enabled' => '1',
        'appointment_slot_minutes' => 30,
        'appointment_capacity_basis' => 'limiting_resource',
        'appointment_scheduling_target_percent' => 100,
        'appointment_capacity_enforcement' => 'warn',
    ], $overrides);
}

test('shop owner can enable appointments from settings', function () {
    expect(OperationsFeatures::appointmentsEnabled())->toBeFalse();

    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.appointments.update'), appointmentSettingsPayload([
            'appointments_enabled' => '1',
        ]))
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'operations']))
        ->assertSessionHas('status');

    expect(ShopSettings::current()->fresh()->appointments_enabled)->toBeTrue()
        ->and(OperationsFeatures::appointmentsEnabled())->toBeTrue();
});

test('shop owner can disable appointments from settings', function () {
    ShopSettings::current()->update(['appointments_enabled' => true]);

    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.appointments.update'), appointmentSettingsPayload([
            'appointments_enabled' => '0',
        ]))
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'operations']));

    expect(ShopSettings::current()->fresh()->appointments_enabled)->toBeFalse();
});

test('shop owner can set appointment slot minutes', function () {
    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.appointments.update'), appointmentSettingsPayload([
            'appointment_slot_minutes' => 15,
        ]))
        ->assertRedirect();

    expect(ShopSettings::current()->fresh()->appointment_slot_minutes)->toBe(15)
        ->and(AppointmentSlotMinutes::resolve())->toBe(15);
});

test('shop owner can configure soft capacity policy', function () {
    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.appointments.update'), appointmentSettingsPayload([
            'appointment_capacity_basis' => 'technicians',
            'appointment_scheduling_target_percent' => 125,
            'appointment_capacity_enforcement' => 'block',
        ]))
        ->assertRedirect();

    $settings = ShopSettings::current()->fresh();

    expect($settings->appointment_capacity_basis)->toBe('technicians')
        ->and($settings->appointment_scheduling_target_percent)->toBe(125)
        ->and($settings->appointment_capacity_enforcement)->toBe('block');
});

test('operations settings page shows appointments section', function () {
    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', ['section' => 'operations']))
        ->assertOk()
        ->assertSee('Enable Appointments', false)
        ->assertSee('Appointment time steps', false)
        ->assertSee('Scheduling capacity', false)
        ->assertSee('Scheduling hours', false)
        ->assertSee('Follow Business Hours', false)
        ->assertSee('Bays', false)
        ->assertSee('Station presence', false)
        ->assertSee('Save operations settings', false);
});

test('scheduling hours follow business hours by default', function () {
    $flow = ShopSettings::defaultTelephonyCallFlow();
    $flow['weekly_hours']['saturday'] = ['enabled' => true, 'open' => '09:00', 'close' => '13:00'];

    ShopSettings::current()->update([
        'scheduling_hours' => null,
        'telephony_call_flow' => $flow,
    ]);
    ShopSettings::forgetCurrent();

    $hours = ShopSettings::current()->schedulingHours();

    expect(ShopSettings::current()->usesCustomSchedulingHours())->toBeFalse()
        ->and($hours['saturday']['enabled'])->toBeTrue()
        ->and($hours['saturday']['open'])->toBe('09:00')
        ->and($hours['saturday']['close'])->toBe('13:00');
});

test('partial telephony config without weekly hours still yields open weekdays for scheduling', function () {
    ShopSettings::current()->update([
        'scheduling_hours' => null,
        'telephony_call_flow' => [
            'comms_attention_gate_enabled' => true,
        ],
    ]);
    ShopSettings::forgetCurrent();

    $hours = ShopSettings::current()->schedulingHours();

    expect($hours['monday']['enabled'])->toBeTrue()
        ->and($hours['friday']['enabled'])->toBeTrue()
        ->and($hours['saturday']['enabled'])->toBeFalse();
});

test('shop owner can customize scheduling hours to blacklist a day', function () {
    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.appointments.update'), appointmentSettingsPayload([
            'scheduling_hours_follow_shop' => '0',
            'scheduling_hours' => [
                'monday' => ['enabled' => '1', 'open' => '08:00', 'close' => '17:00'],
                'tuesday' => ['enabled' => '1', 'open' => '08:00', 'close' => '17:00'],
                'wednesday' => ['enabled' => '1', 'open' => '08:00', 'close' => '17:00'],
                'thursday' => ['enabled' => '1', 'open' => '08:00', 'close' => '17:00'],
                'friday' => ['enabled' => '1', 'open' => '08:00', 'close' => '17:00'],
                'saturday' => ['enabled' => '0', 'open' => '08:00', 'close' => '12:00'],
                'sunday' => ['enabled' => '0', 'open' => '08:00', 'close' => '12:00'],
            ],
        ]))
        ->assertRedirect();

    $settings = ShopSettings::current()->fresh();

    expect($settings->usesCustomSchedulingHours())->toBeTrue()
        ->and($settings->schedulingHours()['saturday']['enabled'])->toBeFalse()
        ->and($settings->schedulingHours()['monday']['enabled'])->toBeTrue();
});

test('shop owner can restore follow business hours', function () {
    ShopSettings::current()->update([
        'scheduling_hours' => \App\Ark\Operations\Appointments\SchedulingHours::defaultWeekly(),
    ]);
    ShopSettings::forgetCurrent();

    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.appointments.update'), appointmentSettingsPayload([
            'scheduling_hours_follow_shop' => '1',
        ]))
        ->assertRedirect();

    expect(ShopSettings::current()->fresh()->scheduling_hours)->toBeNull()
        ->and(ShopSettings::current()->usesCustomSchedulingHours())->toBeFalse();
});

test('shop owner can add rename and remove a schedule bay', function () {
    $this->actingAs($this->admin)
        ->post(route('operations.settings.shop.appointments.bays.store'), [
            'name' => 'Bay 1',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'operations']))
        ->assertSessionHas('status');

    $bay = Workstation::query()->where('name', 'Bay 1')->first();

    expect($bay)->not->toBeNull()
        ->and($bay->accepts_scheduled_work)->toBeTrue();

    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', ['section' => 'operations']))
        ->assertOk()
        ->assertSee('Bay 1', false);

    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.appointments.bays.update', $bay), [
            'name' => 'Lift 1',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'operations']));

    expect($bay->fresh()->name)->toBe('Lift 1')
        ->and($bay->fresh()->accepts_scheduled_work)->toBeTrue();

    $this->actingAs($this->admin)
        ->delete(route('operations.settings.shop.appointments.bays.destroy', $bay))
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'operations']));

    expect($bay->fresh()->accepts_scheduled_work)->toBeFalse()
        ->and(Workstation::query()->whereKey($bay->id)->exists())->toBeTrue();
});

test('removing a schedule bay clears workstation from open appointments', function () {
    ShopSettings::current()->update(['appointments_enabled' => true]);

    $bay = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::current()->id,
        'name' => 'Bay 2',
        'is_active' => true,
        'accepts_scheduled_work' => true,
    ]);

    $customer = \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => 'Bay',
        'last_name' => 'Clear',
        'phone' => '555-0202',
    ]);

    $appointment = \App\Ark\Operations\Appointments\Appointment::query()->create([
        'customer_id' => $customer->id,
        'advisor_user_id' => $this->admin->id,
        'created_by_user_id' => $this->admin->id,
        'workstation_id' => $bay->id,
        'starts_at' => now()->addDay()->setTime(9, 0),
        'ends_at' => now()->addDay()->setTime(10, 0),
        'status' => \App\Ark\Operations\Appointments\AppointmentStatus::Scheduled,
        'concern' => 'Oil change',
    ]);

    $this->actingAs($this->admin)
        ->delete(route('operations.settings.shop.appointments.bays.destroy', $bay))
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'operations']))
        ->assertSessionHas('status');

    expect($bay->fresh()->accepts_scheduled_work)->toBeFalse()
        ->and($appointment->fresh()->workstation_id)->toBeNull();
});

test('schedule bays do not appear as communications stations needing a phone', function () {
    Workstation::query()->create([
        'shop_settings_id' => ShopSettings::current()->id,
        'name' => 'Front Counter',
        'is_active' => true,
        'accepts_scheduled_work' => false,
    ]);

    Workstation::query()->create([
        'shop_settings_id' => ShopSettings::current()->id,
        'name' => 'Bay 1',
        'is_active' => true,
        'accepts_scheduled_work' => true,
    ]);

    $projection = \App\Ark\Operations\Communications\CommunicationsShopProjection::forCurrentShop()->resolve();

    $names = collect($projection['workstations'] ?? [])->pluck('name')->all();

    expect($names)->toContain('Front Counter')
        ->and($names)->not->toContain('Bay 1')
        ->and($projection['next_setup_step'] ?? null)->not->toContain('Bay 1');
});
