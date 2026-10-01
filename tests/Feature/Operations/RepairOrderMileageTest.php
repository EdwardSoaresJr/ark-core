<?php

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('advisor can edit mileage through direct inline input', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = identityHeaderRepairOrderFixture();

    foreach ([route('operations.repair-orders.show', $repairOrder), route('operations.repair-orders.show', $repairOrder)] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertSee('arkRepairOrderMileage', false)
            ->assertSee('ops-mileage-input', false)
            ->assertSee('>In</span>', false)
            ->assertSee('>Out</span>', false);
    }

    $this->patchJson(route('operations.repair-orders.mileage.update', $repairOrder), [
        'mileage_in' => 165604,
        'mileage_out' => 165892,
    ])
        ->assertOk()
        ->assertJson([
            'mileage_in' => 165604,
            'mileage_out' => 165892,
        ]);

    $repairOrder->refresh();

    expect($repairOrder->mileage_in)->toBe(165604)
        ->and($repairOrder->mileage_out)->toBe(165892);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('165,604')
        ->assertSee('165,892');
});

test('mileage out cannot be less than mileage in', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = identityHeaderRepairOrderFixture();

    $this->from(route('operations.repair-orders.show', $repairOrder))
        ->patch(route('operations.repair-orders.mileage.update', $repairOrder), [
            'mileage_in' => 120000,
            'mileage_out' => 119000,
        ])
        ->assertSessionHasErrors('mileage_out');

    expect(RepairOrder::query()->findOrFail($repairOrder->id)->mileage_out)->toBeNull();
});

test('advisor can set mileage out on a closed repair order', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = identityHeaderRepairOrderFixture();
    $repairOrder->update([
        'status' => RepairOrderStatus::Closed,
        'mileage_in' => 78401,
        'mileage_out' => null,
        'closed_at' => now(),
    ]);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('arkRepairOrderMileage', false)
        ->assertSee('ops-mileage-input', false);

    $this->patchJson(route('operations.repair-orders.mileage.update', $repairOrder), [
        'mileage_in' => 78401,
        'mileage_out' => 78405,
    ])
        ->assertOk()
        ->assertJson([
            'mileage_in' => 78401,
            'mileage_out' => 78405,
        ]);

    expect($repairOrder->fresh()->mileage_out)->toBe(78405);
});
