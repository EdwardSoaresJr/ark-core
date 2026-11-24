<?php

use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderConcernText;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('primary prefers denormalized concern_summary', function () {
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Summary Prefer');
    $repairOrder->update(['concern_summary' => 'RO-level summary wins']);
    $repairOrder->load('concerns');

    expect(RepairOrderConcernText::primary($repairOrder))->toBe('RO-level summary wins')
        ->and(RepairOrderConcernText::lines($repairOrder, 2))->toBe(['RO-level summary wins']);
});

test('primary and lines fall back to positioned concern rows', function () {
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Concern Fallback');
    $repairOrder->update(['concern_summary' => '']);
    $repairOrder->concerns()->first()?->update(['summary' => 'First concern', 'position' => 1]);
    $repairOrder->concerns()->create([
        'summary' => 'Second concern',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'recommendation_intent' => 'maintenance',
        'position' => 2,
    ]);
    $repairOrder->load('concerns');

    expect(RepairOrderConcernText::primary($repairOrder))->toBe('First concern')
        ->and(RepairOrderConcernText::lines($repairOrder, 2))->toBe(['First concern', 'Second concern']);
});
