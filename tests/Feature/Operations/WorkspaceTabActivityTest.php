<?php

use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Workspace\WorkspaceTabActivityResolver;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('repair order activity signals estimate version drift', function () {
    $resolver = new WorkspaceTabActivityResolver;
    [$repairOrder] = identityHeaderRepairOrderFixture();

    $repairOrder->update([
        'estimate_version' => 4,
        'status' => RepairOrderStatus::Estimate,
    ]);

    $activity = $resolver->resolve('repair_order', (string) $repairOrder->repair_order_id, [
        'estimateVersion' => 2,
    ]);

    expect($activity)->not->toBeNull()
        ->and($activity['unread'])->toBe(2);
});

test('repair order activity signals awaiting approval attention', function () {
    $resolver = new WorkspaceTabActivityResolver;
    [$repairOrder] = identityHeaderRepairOrderFixture();

    $repairOrder->update([
        'estimate_version' => 3,
        'status' => RepairOrderStatus::WaitingApproval,
    ]);

    $activity = $resolver->resolve('repair_order', (string) $repairOrder->repair_order_id, [
        'estimateVersion' => 3,
    ]);

    expect($activity)->not->toBeNull()
        ->and($activity['unread'])->toBe(1)
        ->and($activity['urgency'])->toBe('medium');
});

test('workspace activity endpoint returns patches for background tabs', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    [$activeRepairOrder] = identityHeaderRepairOrderFixture();
    $activeRepairOrder->update([
        'estimate_version' => 5,
        'status' => RepairOrderStatus::Estimate,
    ]);

    [$backgroundRepairOrder] = identityHeaderRepairOrderFixture();
    $backgroundRepairOrder->update([
        'estimate_version' => 4,
        'status' => RepairOrderStatus::Estimate,
    ]);

    $this->actingAs($user)
        ->postJson(route('operations.workspace.activity'), [
            'activeKey' => 'repair_order:'.$activeRepairOrder->repair_order_id,
            'tabs' => [
                [
                    'key' => 'repair_order:'.$activeRepairOrder->repair_order_id,
                    'seen' => ['estimateVersion' => 5],
                ],
                [
                    'key' => 'repair_order:'.$backgroundRepairOrder->repair_order_id,
                    'seen' => ['estimateVersion' => 2],
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonCount(1, 'patches')
        ->assertJsonPath('patches.0.key', 'repair_order:'.$backgroundRepairOrder->repair_order_id)
        ->assertJsonPath('patches.0.activity.unread', 2);
});
