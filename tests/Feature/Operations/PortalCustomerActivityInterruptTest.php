<?php

use App\Ark\Operations\Communications\Events\CommsInterruptReceived;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Payments\PaymentCaptureSurface;
use App\Ark\Operations\Payments\PaymentGateway;
use App\Ark\Operations\Payments\PaymentGatewayAttempt;
use App\Ark\Operations\Payments\PaymentGatewayAttemptStatus;
use App\Ark\Operations\Portal\PortalCustomerActivityBroadcaster;
use App\Ark\Operations\Portal\PortalCustomerActivityInterruptDismissal;
use App\Ark\Operations\Portal\PortalCustomerActivityInterruptPresenter;
use App\Ark\Operations\Portal\RecordPortalEstimateViewAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', 'test-key');
});

test('portal estimate view broadcasts customer portal interrupt', function () {
    Event::fake([CommsInterruptReceived::class]);

    $customer = Customer::query()->create([
        'first_name' => 'Jamie',
        'last_name' => 'Rivera',
        'phone' => '555-0200',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Subaru',
        'model' => 'Outback',
        'vin' => '4S4BSACC0K3312345',
        'normalized_vin' => '4S4BSACC0K3312345',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Portal interrupt test',
    ]);

    $message = app(\App\Ark\Operations\Conversations\ConversationRecorder::class)
        ->recordPortalEstimateView($repairOrder, 'Customer opened the estimate portal link.');

    app(PortalCustomerActivityBroadcaster::class)->broadcastEstimateView($repairOrder, $message);

    Event::assertDispatched(CommsInterruptReceived::class, function (CommsInterruptReceived $event): bool {
        return ($event->payload['kind'] ?? null) === 'portal'
            && ($event->payload['interrupt']['portal_activity'] ?? null) === 'estimate_viewed'
            && ($event->payload['interrupt']['channel_label'] ?? null) === 'Estimate viewed';
    });

    $cached = Cache::get(PortalCustomerActivityBroadcaster::cacheKey());

    expect($cached)
        ->toBeArray()
        ->and($cached['kind'])->toBe('portal')
        ->and($cached['repair_order_id'])->toBe($repairOrder->repair_order_id);
});

test('portal payment interrupt presenter labels deposit and invoice payments', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Jamie',
        'last_name' => 'Rivera',
        'phone' => '555-0200',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Portal payment interrupt test',
    ]);

    $depositAttempt = PaymentGatewayAttempt::query()->create([
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $customer->id,
        'gateway' => PaymentGateway::Square,
        'capture_surface' => PaymentCaptureSurface::PortalEstimateDeposit,
        'amount_cents' => 25000,
        'currency' => 'USD',
        'idempotency_key' => (string) str()->uuid(),
        'status' => PaymentGatewayAttemptStatus::Completed,
        'initiated_at' => now(),
        'completed_at' => now(),
    ]);

    $invoiceAttempt = PaymentGatewayAttempt::query()->create([
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $customer->id,
        'gateway' => PaymentGateway::Square,
        'capture_surface' => PaymentCaptureSurface::Portal,
        'amount_cents' => 118800,
        'currency' => 'USD',
        'idempotency_key' => (string) str()->uuid(),
        'status' => PaymentGatewayAttemptStatus::Completed,
        'initiated_at' => now(),
        'completed_at' => now(),
    ]);

    $presenter = app(PortalCustomerActivityInterruptPresenter::class);

    expect($presenter->forPayment($repairOrder, $depositAttempt))
        ->toMatchArray([
            'kind' => 'portal',
            'portal_activity' => 'deposit_paid',
            'channel_label' => 'Deposit paid',
            'priority' => 'high',
        ])
        ->and($presenter->forPayment($repairOrder, $invoiceAttempt))
        ->toMatchArray([
            'kind' => 'portal',
            'portal_activity' => 'invoice_paid',
            'channel_label' => 'Invoice paid',
            'priority' => 'high',
        ]);
});

test('record portal estimate view action broadcasts interrupt once', function () {
    Event::fake([CommsInterruptReceived::class]);

    $customer = Customer::query()->create([
        'first_name' => 'Jamie',
        'last_name' => 'Rivera',
        'phone' => '555-0200',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Portal view action test',
    ]);

    $token = \App\Ark\Operations\Portal\EstimateAccessToken::query()->create([
        'repair_order_id' => $repairOrder->id,
        'token_hash' => hash('sha256', 'portal-view-token'),
        'expires_at' => now()->addDay(),
    ]);

    app(RecordPortalEstimateViewAction::class)->execute($repairOrder, $token);

    Event::assertDispatched(CommsInterruptReceived::class);

    app(RecordPortalEstimateViewAction::class)->execute($repairOrder, $token->fresh());

    Event::assertDispatchedTimes(CommsInterruptReceived::class, 1);
});

test('portal interrupt is hidden after advisor dismissal', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Jamie',
        'last_name' => 'Rivera',
        'phone' => '555-0200',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Portal dismiss test',
    ]);

    $message = app(\App\Ark\Operations\Conversations\ConversationRecorder::class)
        ->recordPortalEstimateView($repairOrder, 'Customer opened the estimate portal link.');

    $payload = app(PortalCustomerActivityInterruptPresenter::class)->forEstimateView($repairOrder, $message);
    Cache::put(PortalCustomerActivityBroadcaster::cacheKey(), $payload, now()->addHour());

    $this->actingAs($advisor)
        ->getJson(route('operations.comms.interrupts'))
        ->assertOk()
        ->assertJsonPath('messages.0.kind', 'portal');

    $this->actingAs($advisor)
        ->postJson(route('operations.portal.customer-activity-interrupt.dismiss'), [
            'portal_interrupt_key' => $payload['portal_interrupt_key'],
        ])
        ->assertNoContent();

    expect(Cache::get(PortalCustomerActivityBroadcaster::cacheKey()))->toBeNull();

    $this->actingAs($advisor)
        ->getJson(route('operations.comms.interrupts'))
        ->assertOk()
        ->assertJsonPath('messages', []);
});

test('marking conversation read clears matching portal interrupt cache', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Jamie',
        'last_name' => 'Rivera',
        'phone' => '555-0200',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Portal mark read test',
    ]);

    $message = app(\App\Ark\Operations\Conversations\ConversationRecorder::class)
        ->recordPortalEstimateView($repairOrder, 'Customer opened the estimate portal link.');

    $payload = app(PortalCustomerActivityInterruptPresenter::class)->forEstimateView($repairOrder, $message);
    Cache::put(PortalCustomerActivityBroadcaster::cacheKey(), $payload, now()->addHour());

    $this->actingAs($advisor)
        ->postJson(route('operations.conversations.read', $message->conversation_id))
        ->assertOk();

    expect(Cache::get(PortalCustomerActivityBroadcaster::cacheKey()))->toBeNull();
});

test('portal interrupt dismissal is per advisor', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $advisorA = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $advisorB = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $payload = [
        'kind' => 'portal',
        'state' => 'unread',
        'portal_interrupt_key' => 'estimate-view:99',
        'conversation_id' => 1,
    ];

    Cache::put(PortalCustomerActivityBroadcaster::cacheKey(), $payload, now()->addHour());

    app(PortalCustomerActivityInterruptDismissal::class)->dismiss($advisorA->id, 'estimate-view:99');

    $this->actingAs($advisorA)
        ->getJson(route('operations.comms.interrupts'))
        ->assertOk()
        ->assertJsonPath('messages', []);

    $this->actingAs($advisorB)
        ->getJson(route('operations.comms.interrupts'))
        ->assertOk()
        ->assertJsonPath('messages.0.portal_interrupt_key', 'estimate-view:99');
});
