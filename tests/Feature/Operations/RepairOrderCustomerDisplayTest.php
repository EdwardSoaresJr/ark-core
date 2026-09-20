<?php

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('customer display is a counter kiosk, not the customer portal estimate', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Diagnostic labor',
        'quantity' => '1.00',
        'unit_price_cents' => 12000,
        'total_cents' => 12000,
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.customer-display', $repairOrder))
        ->assertOk()
        ->assertSee('2018 Honda Accord', false)
        ->assertSee('Diagnostic labor', false)
        ->assertSee('Your advisor can walk through this with you', false)
        ->assertDontSee('Staff preview', false)
        ->assertDontSee('Authorize this work', false)
        ->assertDontSee('portal-estimate-shell', false);

    expect(CommunicationEvent::query()->count())->toBe(0);
});

test('guest cannot open the customer display', function () {
    $repairOrder = repairOrderForEstimateWorkspace();

    $this->get(route('operations.repair-orders.customer-display', $repairOrder))
        ->assertRedirect();
});
