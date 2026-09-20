<?php

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update(['appointments_enabled' => true]);
    $this->advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
});

it('schedules without asking for a customer id', function (): void {
    $customer = Customer::query()->create([
        'first_name' => 'Ada',
        'last_name' => 'Wright',
        'phone' => '7195550111',
        'customer_type' => 'Retail',
    ]);

    $this->actingAs($this->advisor)
        ->get(route('operations.schedule'))
        ->assertOk()
        ->assertSee('Find the customer first', false)
        ->assertDontSee('Customer ID', false);

    $this->actingAs($this->advisor)
        ->get(route('operations.schedule', ['q' => 'Ada']))
        ->assertOk()
        ->assertSee('Ada Wright', false)
        ->assertSee(route('operations.schedule', ['customer' => $customer->id]), false);
});

it('puts schedule on the rail and customer hub', function (): void {
    $customer = Customer::query()->create([
        'first_name' => 'Ben',
        'last_name' => 'Carter',
        'phone' => '7195550222',
        'customer_type' => 'Retail',
    ]);

    $this->actingAs($this->advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Job Board', false)
        ->assertSee('>Schedule</span>', false)
        ->assertDontSee('>Workspace</span>', false);

    $this->actingAs($this->advisor)
        ->get(route('operations.customers.show', $customer))
        ->assertOk()
        ->assertSee('Schedule', false)
        ->assertSee('Create RO', false);
});

it('labels check-in and offers intake when no ro', function (): void {
    $customer = Customer::query()->create([
        'first_name' => 'Cara',
        'last_name' => 'Diaz',
        'phone' => '7195550333',
        'customer_type' => 'Retail',
    ]);

    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id,
        'starts_at' => Carbon::parse('2026-07-14 09:00:00'),
        'ends_at' => Carbon::parse('2026-07-14 10:00:00'),
        'concern' => 'Brakes',
        'status' => AppointmentStatus::Arrived,
        'advisor_user_id' => $this->advisor->id,
        'created_by_user_id' => $this->advisor->id,
    ]);

    $this->actingAs($this->advisor)
        ->get(route('operations.appointments.show', $appointment))
        ->assertOk()
        ->assertSee('Checked in', false)
        ->assertSee('Check In / Create RO', false)
        ->assertSee('Use Check In to open the repair order', false);
});

it('points advisors to send estimate on the ro comms rail', function (): void {
    $repairOrder = repairOrderForEstimateWorkspace();

    $this->actingAs($this->advisor)
        ->get(route('operations.repair-orders.workspace-tabs.show', ['repairOrder' => $repairOrder, 'tab' => 'comms']))
        ->assertOk()
        ->assertSee('Send Estimate', false)
        ->assertSee('Text the customer from here', false);
});

it('hides schedule routes when appointments are disabled', function (): void {
    ShopSettings::current()->update(['appointments_enabled' => false]);
    ShopSettings::forgetCurrent();

    $this->actingAs($this->advisor)
        ->get(route('operations.appointments.create'))
        ->assertNotFound();

    $this->actingAs($this->advisor)
        ->get(route('operations.schedule'))
        ->assertNotFound();

    $this->actingAs($this->advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertDontSee('>Schedule</span>', false);
});
