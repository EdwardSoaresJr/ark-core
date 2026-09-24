<?php

use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderEstimateVersion;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('mobile repair order read includes the current estimate version', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = mobileRepairOrder();
    $token = $advisor->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id)
        ->assertOk()
        ->assertJsonPath('repair_order.estimate_version', (int) $repairOrder->estimate_version)
        ->assertJsonPath('workspace.estimate_version', (int) $repairOrder->estimate_version);
});

test('mobile line update with the current estimate version succeeds and returns the next version', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = mobileRepairOrder();
    $concern = $repairOrder->concerns->first();
    $line = $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace brake pads',
        'quantity' => 1,
        'unit_price_cents' => 15000,
    ]);
    $openedVersion = app(RepairOrderConcurrency::class)->openedVersion($repairOrder);
    $token = $advisor->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/lines/'.$line->id, [
            RepairOrderConcurrency::FIELD => $openedVersion,
            'repair_order_concern_id' => $concern->id,
            'type' => RepairOrderLineType::Labor->value,
            'description' => 'Replace front brake pads',
            'quantity' => '1.00',
            'unit_price' => '150.00',
        ])
        ->assertOk()
        ->assertJsonPath('repair_order.estimate_version', $openedVersion + 1);

    expect($line->fresh()->description)->toBe('Replace front brake pads');
});

test('stale mobile line update is rejected', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $other = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = mobileRepairOrder();
    $concern = $repairOrder->concerns->first();
    $line = $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace brake pads',
        'quantity' => 1,
        'unit_price_cents' => 15000,
    ]);
    $openedVersion = app(RepairOrderConcurrency::class)->openedVersion($repairOrder);
    app(RepairOrderEstimateVersion::class)->bump($repairOrder->fresh(), $other);
    $token = $advisor->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/lines/'.$line->id, [
            RepairOrderConcurrency::FIELD => $openedVersion,
            'repair_order_concern_id' => $concern->id,
            'type' => RepairOrderLineType::Labor->value,
            'description' => 'Stale mobile overwrite',
            'quantity' => '1.00',
            'unit_price' => '150.00',
        ])
        ->assertStatus(409)
        ->assertJsonPath('conflict', true);

    expect($line->fresh()->description)->toBe('Replace brake pads');
});

test('mobile line update without an estimate version still saves', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = mobileRepairOrder();
    $concern = $repairOrder->concerns->first();
    $line = $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace brake pads',
        'quantity' => 1,
        'unit_price_cents' => 15000,
    ]);
    $token = $advisor->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/lines/'.$line->id, [
            'repair_order_concern_id' => $concern->id,
            'type' => RepairOrderLineType::Labor->value,
            'description' => 'Phone still omits the version',
            'quantity' => '1.00',
            'unit_price' => '150.00',
        ])
        ->assertOk();

    expect($line->fresh()->description)->toBe('Phone still omits the version');
});
