<?php

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Appointments\SchedulingHours;
use App\Ark\Operations\Appointments\SchedulingWorkspaceProjection;
use App\Ark\Operations\Appointments\WeekAllocate;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Runtime\Authorization\ArkRole;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    ShopSettings::current()->update([
        'appointments_enabled' => true,
        'shop_timezone' => 'America/Denver',
        'scheduling_hours' => SchedulingHours::defaultWeekly(),
    ]);
    ShopSettings::forgetCurrent();
    $this->seed(ArkAuthorizationSeeder::class);
});

test('week technician allocation keeps unassigned visible and groups by technician', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-09-15 08:00:00', 'America/Denver'));
    $advisor = actingAsLearnCurrentAdvisor();
    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);

    $unassignedCustomer = Customer::query()->create([
        'first_name' => 'Una',
        'last_name' => 'Signed',
        'phone' => '5550101001',
    ]);
    $assignedCustomer = Customer::query()->create([
        'first_name' => 'Assigned',
        'last_name' => 'Driver',
        'phone' => '5550101002',
    ]);

    Appointment::query()->create([
        'customer_id' => $unassignedCustomer->id,
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-09-15 09:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-09-15 10:00')->utc(),
        'concern' => 'Brakes',
        'status' => AppointmentStatus::Scheduled,
        'estimated_labor_hours' => 1.5,
    ]);
    Appointment::query()->create([
        'customer_id' => $assignedCustomer->id,
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'technician_user_id' => $technician->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-09-16 11:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-09-16 12:30')->utc(),
        'concern' => 'Alignment',
        'status' => AppointmentStatus::Scheduled,
        'estimated_labor_hours' => 2,
    ]);

    $workspace = app(SchedulingWorkspaceProjection::class)->resolve(
        Carbon::parse('2026-09-15'),
        'week',
        'agenda',
        $advisor,
        false,
        null,
        WeekAllocate::Technician,
    );

    expect($workspace['allocate'])->toBe('technician')
        ->and($workspace['week_allocation'])->not->toBeNull();

    $rows = collect($workspace['week_allocation']['rows']);
    $unassigned = $rows->firstWhere('key', 'tech-none');
    $techRow = $rows->firstWhere('resource_id', (int) $technician->id);

    expect($unassigned)->not->toBeNull()
        ->and($unassigned['week_count'])->toBe(1)
        ->and(collect($unassigned['days'])->flatMap(fn (array $day) => $day['cards'])->pluck('customer_name')->all())
        ->toContain('Una Signed')
        ->and($techRow)->not->toBeNull()
        ->and($techRow['week_count'])->toBe(1)
        ->and($techRow['labor_label'])->toBe('2h')
        ->and(collect($techRow['days'])->flatMap(fn (array $day) => $day['cards'])->pluck('customer_name')->all())
        ->toContain('Assigned Driver');

    $this->actingAs($advisor)
        ->get(route('operations.appointments.index', [
            'day' => '2026-09-15',
            'view' => 'week',
            'allocate' => 'technician',
        ]))
        ->assertOk()
        ->assertSee('ops-cal-week-alloc', false)
        ->assertSee('Unassigned', false)
        ->assertSee('Una Signed', false)
        ->assertSee($technician->name, false);
});

test('week bay allocation groups by workstation including unassigned', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-09-15 08:00:00', 'America/Denver'));
    $advisor = actingAsLearnCurrentAdvisor();
    $bay = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::current()->id,
        'name' => 'Bay 1',
        'is_active' => true,
        'accepts_scheduled_work' => true,
    ]);

    $customer = Customer::query()->create([
        'first_name' => 'Bay',
        'last_name' => 'Job',
        'phone' => '5550101003',
    ]);

    Appointment::query()->create([
        'customer_id' => $customer->id,
        'created_by_user_id' => $advisor->id,
        'workstation_id' => $bay->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-09-17 09:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-09-17 10:00')->utc(),
        'concern' => 'Tires',
        'status' => AppointmentStatus::Scheduled,
    ]);
    Appointment::query()->create([
        'customer_id' => Customer::query()->create([
            'first_name' => 'No',
            'last_name' => 'Bay',
            'phone' => '5550101004',
        ])->id,
        'created_by_user_id' => $advisor->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-09-17 13:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-09-17 14:00')->utc(),
        'concern' => 'Diagnostic',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $workspace = app(SchedulingWorkspaceProjection::class)->resolve(
        Carbon::parse('2026-09-15'),
        'week',
        'agenda',
        $advisor,
        false,
        null,
        WeekAllocate::Workstation,
    );

    $rows = collect($workspace['week_allocation']['rows']);
    expect($rows->firstWhere('key', 'ws-none')['week_count'])->toBe(1)
        ->and($rows->firstWhere('resource_id', (int) $bay->id)['week_count'])->toBe(1);
});

test('week assignment updates existing technician and bay fields', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-09-15 08:00:00', 'America/Denver'));
    $advisor = actingAsLearnCurrentAdvisor();
    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);
    $bay = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::current()->id,
        'name' => 'Bay 2',
        'is_active' => true,
        'accepts_scheduled_work' => true,
    ]);

    $appointment = Appointment::query()->create([
        'customer_id' => Customer::query()->create([
            'first_name' => 'Move',
            'last_name' => 'Me',
            'phone' => '5550101005',
        ])->id,
        'created_by_user_id' => $advisor->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-09-15 10:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-09-15 11:00')->utc(),
        'concern' => 'Noise',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $this->actingAs($advisor)
        ->patch(route('operations.appointments.assign', $appointment), [
            'technician_user_id' => $technician->id,
            'workstation_id' => $bay->id,
            'day' => '2026-09-15',
            'view' => 'week',
            'allocate' => 'technician',
        ])
        ->assertRedirect(route('operations.appointments.index', [
            'day' => '2026-09-15',
            'view' => 'week',
            'allocate' => 'technician',
        ]));

    $appointment->refresh();
    expect((int) $appointment->technician_user_id)->toBe((int) $technician->id)
        ->and((int) $appointment->workstation_id)->toBe((int) $bay->id);
});

test('month day cells expose soft-capacity load cue while keeping three-card cap', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-09-15 08:00:00', 'America/Denver'));
    $advisor = actingAsLearnCurrentAdvisor();

    for ($i = 1; $i <= 5; $i++) {
        Appointment::query()->create([
            'customer_id' => Customer::query()->create([
                'first_name' => 'Load'.$i,
                'last_name' => 'Day',
                'phone' => '5550102'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            ])->id,
            'created_by_user_id' => $advisor->id,
            'starts_at' => ShopDisplayTimezone::parseLocal('2026-09-15 '.sprintf('%02d:00', 8 + $i))->utc(),
            'ends_at' => ShopDisplayTimezone::parseLocal('2026-09-15 '.sprintf('%02d:30', 8 + $i))->utc(),
            'concern' => 'Job '.$i,
            'status' => AppointmentStatus::Scheduled,
            'estimated_labor_hours' => 3,
        ]);
    }

    $workspace = app(SchedulingWorkspaceProjection::class)->resolve(
        Carbon::parse('2026-09-15'),
        'month',
    );

    $day = collect($workspace['month_weeks'])
        ->flatMap(fn (array $week) => $week['days'])
        ->firstWhere('date', '2026-09-15');

    expect($day['count'])->toBe(5)
        ->and($day['load_label'])->not->toBeNull()
        ->and($day)->toHaveKey('load_status')
        ->and(count($day['cards']))->toBe(5);

    $this->actingAs($advisor)
        ->get(route('operations.appointments.index', ['day' => '2026-09-15', 'view' => 'month']))
        ->assertOk()
        ->assertSee('3 shown · +2 more', false)
        ->assertSee('ops-cal-month__count', false);
});
