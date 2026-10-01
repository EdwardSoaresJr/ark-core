<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('visit reason phase 0: intake stores customer words without creating estimate concerns', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update(['learn_training_gate_enabled' => false]);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $customer = Customer::query()->create([
        'first_name' => 'Kyle',
        'last_name' => 'Kight',
        'phone' => '555-1644',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2021,
        'make' => 'Kia',
        'model' => 'Forte',
    ]);

    $reason = "My brakes make noise on hard stops.\nI think I need front and rear brakes.";

    $this->post(route('operations.intake.store'), [
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'visit_reason' => $reason,
        'visit_mode' => 'waiting_here',
        'billing_class' => 'Retail',
    ])->assertSessionHasNoErrors();

    $repairOrder = RepairOrder::query()->sole();

    expect($repairOrder->visit_reason)->toBe($reason);
    expect($repairOrder->concerns)->toHaveCount(0);
    expect($repairOrder->status->is(RepairOrderStatus::Draft))->toBeTrue();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Reason for Visit')
        ->assertSee('My brakes make noise on hard stops.', false)
        ->assertSee('I think I need front and rear brakes.', false);

    $this->patch(route('operations.repair-orders.visit-reason.update', $repairOrder), [
        'visit_reason' => $reason."\n(Advisor clarified: noise on extreme stops.)",
        'opened_estimate_version' => $repairOrder->estimate_version,
    ])->assertRedirect();

    expect($repairOrder->fresh()->visit_reason)->toContain('Advisor clarified')
        ->and($repairOrder->fresh()->concerns)->toHaveCount(0);
});

test('existing repair orders keep null visit_reason with no backfill', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $customer = Customer::query()->create([
        'first_name' => 'Legacy',
        'last_name' => 'Ro',
        'phone' => '555-9999',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Ford',
        'model' => 'Focus',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Draft,
        'concern_summary' => 'Old check-in concern text.',
        'opened_at' => now(),
    ]);

    expect($repairOrder->fresh()->visit_reason)->toBeNull()
        ->and($repairOrder->concern_summary)->toBe('Old check-in concern text.');
});
