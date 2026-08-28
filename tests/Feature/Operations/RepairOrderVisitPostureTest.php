<?php

use App\Ark\Operations\RepairOrders\RepairOrderVisitMode;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update(['learn_training_gate_enabled' => false]);
});

test('advisor can update visit posture from repair order workspace', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = identityHeaderRepairOrderFixture();
    RepairOrderVisitMode::DropOff->applyTo($repairOrder);
    $repairOrder->save();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Visit', false)
        ->assertSee('RO #'.$repairOrder->repair_order_id, false)
        ->assertSee("task: 'visit-posture'", false)
        ->assertSee('data-workspace-modal-form="visit-posture"', false)
        ->assertSee('value="drop_off"', false);

    $this->patchJson(route('operations.repair-orders.visit-posture.update', $repairOrder), [
        'visit_mode' => RepairOrderVisitMode::NeedsShuttle->value,
    ])
        ->assertOk()
        ->assertJson([
            'visit_mode' => RepairOrderVisitMode::NeedsShuttle->value,
            'visit_mode_label' => 'Shuttle',
        ]);

    $repairOrder->refresh();

    expect($repairOrder->needs_shuttle)->toBeTrue()
        ->and($repairOrder->drop_off)->toBeFalse()
        ->and($repairOrder->waiting_here)->toBeFalse()
        ->and($repairOrder->tow_incoming)->toBeFalse();
});

test('visit posture update is blocked on terminal repair orders', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = identityHeaderRepairOrderFixture();
    $repairOrder->update(['status' => RepairOrderStatus::Closed]);

    $this->patchJson(route('operations.repair-orders.visit-posture.update', $repairOrder), [
        'visit_mode' => RepairOrderVisitMode::WaitingHere->value,
    ])->assertStatus(423);
});

test('visit posture update sets posture on repair order without prior visit mode', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = identityHeaderRepairOrderFixture();
    $repairOrder->forceFill([
        'drop_off' => false,
        'waiting_here' => false,
        'needs_shuttle' => false,
        'tow_incoming' => false,
    ])->save();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Visit', false)
        ->assertSee('RO #'.$repairOrder->repair_order_id, false)
        ->assertSee("task: 'visit-posture'", false);

    $this->patchJson(route('operations.repair-orders.visit-posture.update', $repairOrder), [
        'visit_mode' => RepairOrderVisitMode::DropOff->value,
    ])
        ->assertOk()
        ->assertJson([
            'visit_mode' => RepairOrderVisitMode::DropOff->value,
            'visit_mode_label' => 'Drop Off',
        ]);

    $repairOrder->refresh();

    expect($repairOrder->drop_off)->toBeTrue();
});

test('visit posture update requires manage permission', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Technician->value));

    [$repairOrder] = identityHeaderRepairOrderFixture();

    $this->patchJson(route('operations.repair-orders.visit-posture.update', $repairOrder), [
        'visit_mode' => RepairOrderVisitMode::WaitingHere->value,
    ])->assertForbidden();
});
