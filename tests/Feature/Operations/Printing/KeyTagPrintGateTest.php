<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Printing\KeyTagPrintGate;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderVisitMode;
use App\Ark\Operations\RepairOrders\RecommendationIntent;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('check in defaults choose whether a key tag needs mileage in', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->withSession([\App\Ark\Operations\Workstations\WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('operations.settings.shop.edit', [
            'section' => 'workflow',
            'workflow-tab' => 'defaults',
        ]))
        ->assertOk()
        ->assertSee('Mileage required')
        ->assertSee('print without mileage in', false)
        ->assertSee("don't print until it is entered", false);

    $this->actingAs($admin)->patch(route('operations.settings.shop.workflow.update'), [
        'default_visit_mode' => RepairOrderVisitMode::DropOff->value,
        'default_recommendation_intent' => RecommendationIntent::Maintenance->value,
        'key_tag_mileage_requirement' => KeyTagPrintGate::MILEAGE_IN,
    ])->assertRedirect();

    expect(ShopSettings::current()->fresh()->key_tag_mileage_requirement)->toBe(KeyTagPrintGate::MILEAGE_IN);
});

test('key tag print is blocked until mileage in when the shop requires it', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    ShopSettings::current()->update([
        'key_tag_mileage_requirement' => KeyTagPrintGate::MILEAGE_IN,
    ]);

    $customer = Customer::query()->create([
        'first_name' => 'Key',
        'last_name' => 'Tag',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Key tag',
    ]);

    expect(KeyTagPrintGate::blockedReason($repairOrder))->toBe('Enter mileage in before printing the key tag.');

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.print-key-tag', $repairOrder))
        ->assertStatus(422);

    $repairOrder->update(['mileage_in' => 120000]);

    expect(KeyTagPrintGate::blockedReason($repairOrder->fresh()))->toBeNull();
});
