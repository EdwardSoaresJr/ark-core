<?php

use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderEstimateBroadcast;
use App\Ark\Operations\RepairOrders\RepairOrderEstimateChanged;
use App\Ark\Operations\RepairOrders\RepairOrderEstimateVersion;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Event;

test('estimate version bumps broadcast a private repair order channel event', function () {
    config(['broadcasting.default' => 'log']);

    [$repairOrder] = identityHeaderRepairOrderFixture();
    $advisor = User::factory()->create(['name' => 'Lane Advisor']);

    Event::fake([RepairOrderEstimateChanged::class]);

    app(RepairOrderEstimateVersion::class)->bump($repairOrder, $advisor);

    Event::assertDispatched(RepairOrderEstimateChanged::class, function (RepairOrderEstimateChanged $event) use ($repairOrder, $advisor): bool {
        return $event->repairOrder->repair_order_id === $repairOrder->repair_order_id
            && $event->repairOrder->estimate_version === 2
            && $event->broadcastAs() === 'estimate.changed'
            && $event->broadcastWith()['actor_id'] === $advisor->id
            && str_contains($event->broadcastWith()['message'], 'Lane Advisor');
    });
});

test('estimate version bumps skip broadcasting when broadcasting is disabled', function () {
    config(['broadcasting.default' => 'null']);

    [$repairOrder] = identityHeaderRepairOrderFixture();

    Event::fake([RepairOrderEstimateChanged::class]);

    app(RepairOrderEstimateVersion::class)->bump($repairOrder);

    Event::assertNotDispatched(RepairOrderEstimateChanged::class);
});

test('staff with repair order access can authorize the estimate broadcast channel', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    [$repairOrder] = identityHeaderRepairOrderFixture();
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->post('/broadcasting/auth', [
            'channel_name' => 'private-'.RepairOrderEstimateBroadcast::channelName($repairOrder->repair_order_id),
            'socket_id' => '1.1',
        ])
        ->assertOk();
});

test('unauthenticated users cannot authorize the estimate broadcast channel', function () {
    [$repairOrder] = identityHeaderRepairOrderFixture();

    $this->post('/broadcasting/auth', [
        'channel_name' => 'private-'.RepairOrderEstimateBroadcast::channelName($repairOrder->repair_order_id),
        'socket_id' => '1.1',
    ])->assertRedirect();
});

test('repair order estimate mutations dispatch estimate changed broadcasts when enabled', function () {
    config(['broadcasting.default' => 'log']);
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = identityHeaderRepairOrderFixture();
    $concern = $repairOrder->concerns()->firstOrFail();

    Event::fake([RepairOrderEstimateChanged::class]);

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => 'part',
        'description' => 'Brake pads',
        'quantity' => '1',
        'unit_price' => '89.99',
        RepairOrderConcurrency::FIELD => $repairOrder->estimate_version,
    ])->assertRedirect();

    Event::assertDispatched(RepairOrderEstimateChanged::class);
});
