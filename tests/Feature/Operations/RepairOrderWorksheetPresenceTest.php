<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderWorksheetSession;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('worksheet heartbeat registers presence for the current advisor', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create(['name' => 'Maria Advisor'])->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = presenceRepairOrderFixture();

    $this->postJson(route('operations.repair-orders.worksheet-sessions.heartbeat', $repairOrder), [
        'session_token' => 'tab-builder-1',
        'surface' => 'builder',
        'opened_estimate_version' => $repairOrder->estimate_version,
    ])->assertOk()
        ->assertJsonPath('lease_valid', true)
        ->assertJsonPath('estimate_version', 1);

    expect(RepairOrderWorksheetSession::query()->count())->toBe(1);
});

test('worksheet heartbeat reports another advisor on the same estimate', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $maria = User::factory()->create(['name' => 'Maria Advisor'])->assignRole(ArkRole::Advisor->value);
    $ed = User::factory()->create(['name' => 'Ed Advisor'])->assignRole(ArkRole::Advisor->value);

    $repairOrder = presenceRepairOrderFixture();

    $this->actingAs($maria)->postJson(route('operations.repair-orders.worksheet-sessions.heartbeat', $repairOrder), [
        'session_token' => 'tab-maria',
        'surface' => 'builder',
        'opened_estimate_version' => 1,
    ]);

    $response = $this->actingAs($ed)->postJson(route('operations.repair-orders.worksheet-sessions.heartbeat', $repairOrder), [
        'session_token' => 'tab-ed',
        'surface' => 'builder',
        'opened_estimate_version' => 1,
    ]);

    $response->assertOk()
        ->assertJsonPath('presence_message', 'Maria Advisor is also on this estimate.');
});

test('worksheet heartbeat reports duplicate tab presence for the same advisor', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create(['name' => 'Ed Advisor'])->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = presenceRepairOrderFixture();

    $this->postJson(route('operations.repair-orders.worksheet-sessions.heartbeat', $repairOrder), [
        'session_token' => 'tab-builder-1',
        'surface' => 'builder',
        'opened_estimate_version' => 1,
    ]);

    $this->postJson(route('operations.repair-orders.worksheet-sessions.heartbeat', $repairOrder), [
        'session_token' => 'tab-builder-2',
        'surface' => 'review',
        'opened_estimate_version' => 1,
    ])->assertOk()
        ->assertJsonPath('presence_message', 'You have this estimate open in another tab.');
});

test('worksheet heartbeat prunes expired leases and reports version drift', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create(['name' => 'Maria Advisor'])->assignRole(ArkRole::Advisor->value);
    $otherAdvisor = User::factory()->create(['name' => 'Ed Advisor'])->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = presenceRepairOrderFixture();

    RepairOrderWorksheetSession::query()->create([
        'repair_order_id' => $repairOrder->id,
        'user_id' => $advisor->id,
        'session_token' => 'stale-tab',
        'surface' => 'builder',
        'last_seen_at' => now()->subMinutes(10),
        'expires_at' => now()->subMinute(),
    ]);

    $repairOrder->update([
        'estimate_version' => 3,
        'estimate_version_actor_id' => $otherAdvisor->id,
        'estimate_version_at' => now(),
    ]);

    $this->postJson(route('operations.repair-orders.worksheet-sessions.heartbeat', $repairOrder), [
        'session_token' => 'fresh-tab',
        'surface' => 'builder',
        'opened_estimate_version' => 1,
    ])->assertOk()
        ->assertJsonPath('lease_valid', true)
        ->assertJsonPath('version_drifted', true)
        ->assertJsonPath('estimate_version', 3)
        ->assertJsonPath('conflict.conflict', true);

    expect(RepairOrderWorksheetSession::query()->where('session_token', 'stale-tab')->exists())->toBeFalse();
});

test('worksheet heartbeat does not report drift for the advisors own estimate edits', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create(['name' => 'Maria Advisor'])->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = presenceRepairOrderFixture();

    $repairOrder->update([
        'estimate_version' => 3,
        'estimate_version_actor_id' => $advisor->id,
        'estimate_version_at' => now(),
    ]);

    $this->postJson(route('operations.repair-orders.worksheet-sessions.heartbeat', $repairOrder), [
        'session_token' => 'fresh-tab',
        'surface' => 'builder',
        'opened_estimate_version' => 1,
    ])->assertOk()
        ->assertJsonPath('version_drifted', false)
        ->assertJsonPath('estimate_version', 3)
        ->assertJsonPath('conflict', null);
});

function presenceRepairOrderFixture(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Presence',
        'last_name' => 'Customer',
        'phone' => '555-0199',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Presence test.',
    ]);
}
