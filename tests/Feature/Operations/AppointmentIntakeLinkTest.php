<?php

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Intake\IntakeEntryQuery;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadContactPreference;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update(['learn_training_gate_enabled' => false]);
});

test('check in from an appointment links the new repair order', function () {
    ShopSettings::current()->update(['appointments_enabled' => true]);
    ShopSettings::forgetCurrent();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $customer = Customer::query()->create([
        'first_name' => 'Sarah',
        'last_name' => 'Zelenske',
        'phone' => '5551744000',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2013,
        'make' => 'Mazda',
        'model' => 'CX-9',
    ]);
    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => Carbon::parse('2026-09-14 15:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-09-14 16:00:00', 'UTC'),
        'concern' => 'Alternator replacement',
        'status' => AppointmentStatus::Scheduled,
    ]);

    expect(IntakeEntryQuery::fromAppointment($appointment))
        ->toMatchArray([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'concern' => 'Alternator replacement',
            'appointment_id' => $appointment->id,
        ]);

    $this->get(route('operations.appointments.show', $appointment))
        ->assertOk()
        ->assertSee('appointment_id='.$appointment->id, false)
        ->assertSee('Check In / Create RO', false);

    $this->followingRedirects()
        ->get(route('operations.intake.create', IntakeEntryQuery::fromAppointment($appointment)))
        ->assertOk()
        ->assertSee('name="appointment_id"', false)
        ->assertSee('value="'.$appointment->id.'"', false);

    $this->post(route('operations.intake.store'), [
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'appointment_id' => $appointment->id,
        'visit_mode' => 'drop_off',
        'visit_reason' => 'Alternator replacement',
    ])->assertRedirect();

    $repairOrder = RepairOrder::query()->sole();

    expect($appointment->fresh()->repair_order_id)->toBe($repairOrder->id)
        ->and($repairOrder->appointment)->toBeFalse();
});

test('lead intake still converts the lead without an appointment', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Brakes grinding at low speed.',
        'contact_phone' => '7195550188',
        'contact_name' => 'Sam Rivera',
        'contact_email' => 'sam@example.com',
        'contact_preference' => LeadContactPreference::Call,
    ]);
    $customer = Customer::query()->create([
        'first_name' => 'Sam',
        'last_name' => 'Rivera',
        'phone' => '7195550188',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'CR-V',
    ]);

    $this->followingRedirects()
        ->get(route('operations.intake.create', ['lead_id' => $lead->id]))
        ->assertOk()
        ->assertSee('name="lead_id"', false)
        ->assertSee('value="'.$lead->id.'"', false)
        ->assertDontSee('name="appointment_id"', false);

    $this->post(route('operations.intake.store'), [
        'lead_id' => $lead->id,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'visit_mode' => 'drop_off',
        'billing_class' => 'Retail',
    ])->assertRedirect();

    $repairOrder = RepairOrder::query()->sole();

    expect($lead->fresh())
        ->state->toBe(LeadState::Converted)
        ->repair_order_id->toBe($repairOrder->id)
        ->and(Appointment::query()->count())->toBe(0);
});

test('intake does not reassign an appointment already linked to another repair order', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $customer = Customer::query()->create([
        'first_name' => 'Josh',
        'last_name' => 'Case',
        'phone' => '5551733000',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);
    $existingRepairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Already on this visit',
    ]);
    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'repair_order_id' => $existingRepairOrder->id,
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => Carbon::parse('2026-09-14 15:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-09-14 16:00:00', 'UTC'),
        'concern' => 'Follow-up',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $this->post(route('operations.intake.store'), [
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'appointment_id' => $appointment->id,
        'visit_mode' => 'waiting_here',
    ])->assertRedirect();

    $newRepairOrder = RepairOrder::query()->whereKeyNot($existingRepairOrder->id)->sole();

    expect($appointment->fresh()->repair_order_id)->toBe($existingRepairOrder->id)
        ->and($newRepairOrder->id)->not->toBe($existingRepairOrder->id)
        ->and($newRepairOrder->appointments)->toHaveCount(0);
});
