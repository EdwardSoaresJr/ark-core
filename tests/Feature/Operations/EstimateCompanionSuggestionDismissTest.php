<?php

use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('advisor can dismiss a missing companion suggestion until the estimate content changes', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::Estimate);
    $repairOrder->concerns()->update(['summary' => 'Timing belt']);
    $repairOrder->lines()->update(['description' => 'Replace timing belt']);
    $repairOrder = $repairOrder->fresh(['lines', 'concerns']);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('This job is missing oil', false)
        ->assertSee('arkDismissCompanionSuggestion', false);

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.companion-suggestion.dismiss', $repairOrder))
        ->assertOk();

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertDontSee('This job is missing oil', false)
        ->assertDontSee('arkDismissCompanionSuggestion', false);

    $concernId = $repairOrder->concerns()->first()->id;
    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concernId,
        'type' => RepairOrderLineType::Part,
        'description' => 'Cabin filter',
        'quantity' => '1.00',
        'unit_price_cents' => 2500,
        'subtotal_cents' => 2500,
        'total_cents' => 2500,
        'position' => 2,
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder->fresh(['lines', 'concerns'])))
        ->assertOk()
        ->assertSee('This job is missing oil', false);
});
