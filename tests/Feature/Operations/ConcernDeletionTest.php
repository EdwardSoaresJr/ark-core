<?php

use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\UpdateConcernDispositionAction;
use App\Ark\Operations\RepairOrders\WorkCompletionAuthorization;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Spatie\Permission\Models\Permission;

test('open repair orders surface a confirmable delete control for empty unapproved concerns', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Duplicate draft concern',
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    $html = $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk()->getContent();

    expect($html)
        ->toContain('aria-label="Delete concern"')
        ->toContain('Confirm delete')
        ->toContain('Remove this unlinked concern (requires confirmation)')
        ->toContain('name="_method" value="DELETE"')
        ->not->toContain('Cannot delete');

    $this->delete(route('operations.repair-orders.concerns.destroy', [$repairOrder, $concern]))
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    expect(RepairOrderConcern::query()->whereKey($concern->id)->exists())->toBeFalse();
});

test('concerns with linked lines explain why delete is unavailable', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Diagnostic',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'total_cents' => 15000,
    ]);

    $reason = WorkCompletionAuthorization::CONCERN_DELETE_HAS_LINES;

    $html = $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk()->getContent();

    expect($html)
        ->toContain('Cannot delete')
        ->toContain($reason)
        ->toContain('aria-label="Cannot delete concern"')
        ->not->toContain('aria-label="Delete concern"');

    $this->delete(route('operations.repair-orders.concerns.destroy', [$repairOrder, $concern]))
        ->assertStatus(422);

    expect($concern->fresh())->not->toBeNull();
});

test('approved concerns keep authorization history and explain the delete block', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Approved brakes',
        'recommendation_intent' => 'required',
        'disposition' => RepairOrderConcernDisposition::Draft,
        'position' => 1,
    ]);

    $line = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Pad replacement',
        'quantity' => '1.00',
        'unit_price_cents' => 22000,
        'total_cents' => 22000,
    ]);

    app(UpdateConcernDispositionAction::class)->execute(
        $repairOrder,
        $concern,
        RepairOrderConcernDisposition::Approved,
        $advisor,
    );

    $line->delete();

    $reason = WorkCompletionAuthorization::CONCERN_DELETE_BLOCKED;

    expect(app(WorkCompletionAuthorization::class)->concernDeletionBlockedReason($concern->fresh()))
        ->toBe($reason);

    $html = $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk()->getContent();

    expect($html)
        ->toContain('Cannot delete')
        ->toContain($reason)
        ->not->toContain('aria-label="Delete concern"');

    $this->deleteJson(route('operations.repair-orders.concerns.destroy', [$repairOrder, $concern]))
        ->assertStatus(422)
        ->assertJsonPath('message', $reason);

    expect($concern->fresh())->not->toBeNull();
});

test('closed repair orders reject concern deletion and hide the control', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Leftover empty concern',
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    $repairOrder->update([
        'status' => RepairOrderStatus::Closed,
        'closed_at' => now(),
    ]);

    $html = $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk()->getContent();

    expect($html)
        ->not->toContain('aria-label="Delete concern"')
        ->not->toContain('aria-label="Cannot delete concern"')
        ->not->toContain('aria-label="Confirm delete concern"');

    $this->delete(route('operations.repair-orders.concerns.destroy', [$repairOrder, $concern]))
        ->assertStatus(423);

    expect($concern->fresh())->not->toBeNull();
});

test('concern deletion requires destructive repair-order authority', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $permissions = collect(config('ark-permissions.roles.'.ArkRole::Advisor->value))
        ->reject(fn (string $permission): bool => $permission === ArkCapability::RepairOrdersDestructive->value)
        ->map(fn (string $permission): Permission => Permission::findByName($permission))
        ->all();

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);
    $this->actingAs($user);

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Should stay',
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    $html = $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk()->getContent();

    expect($html)
        ->not->toContain('aria-label="Delete concern"')
        ->not->toContain('aria-label="Confirm delete concern"');

    $this->delete(route('operations.repair-orders.concerns.destroy', [$repairOrder, $concern]))
        ->assertForbidden();

    expect($concern->fresh())->not->toBeNull();
});
