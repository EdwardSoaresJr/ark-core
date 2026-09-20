<?php

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use Database\Seeders\ArkAuthorizationSeeder;

test('resolve for customer returns relationship context without phone lookup', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Hub',
        'last_name' => 'Customer',
        'phone' => '7195557777',
        'customer_type' => 'Retail',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2021,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    conversationContextRepairOrder($customer, $vehicle, RepairOrderStatus::WaitingApproval, 3101);

    $context = app(CustomerCallContextResolver::class)->resolveForCustomer($customer);

    expect($context->customer?->id)->toBe($customer->id)
        ->and($context->openRepairOrders)->toHaveCount(1)
        ->and($context->vehicles)->toHaveCount(1)
        ->and($context->vehicles->first()?->model)->toBe('Camry');
});

test('customer hub renders shared relationship context panel', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = actingAsLearnCurrentAdvisor();
    $this->actingAs($advisor);

    $customer = Customer::query()->create([
        'first_name' => 'Pat',
        'last_name' => 'Rivera',
        'phone' => '5550100888',
        'customer_type' => 'Retail',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Chevrolet',
        'model' => 'Malibu',
        'plate' => 'MAL888',
    ]);

    $repairOrder = conversationContextRepairOrder($customer, $vehicle, RepairOrderStatus::InProgress, 3201);

    app(ConversationRecorder::class)->recordAdvisorLog(
        $repairOrder,
        $advisor,
        OperationalCommunicationChannel::Phone,
        OperationalCommunicationDirection::Inbound,
        'Calling about pickup time.',
    );

    $this->get(route('operations.customers.show', $customer))
        ->assertOk()
        ->assertSee('Active Work')
        ->assertSee('Communications')
        ->assertSee('Calls ·')
        ->assertSee('Text ·')
        ->assertSee('filter by type')
        ->assertSee('2018 Chevrolet Malibu')
        ->assertSee('RO #3201')
        ->assertSee('Calling about pickup time.');
});

test('caller lookup renders unmatched conversation through shared panel', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    app(ConversationRecorder::class)->recordInboundSms(
        normalizedPhone: '7195550000',
        body: 'Do you have time for an oil change tomorrow?',
        providerMessageSid: 'SM_unmatched_panel_test',
    );

    $this->get(route('operations.caller-lookup', ['phone' => '719-555-0000']))
        ->assertOk()
        ->assertSee('No customer matched')
        ->assertSee('Recent Conversation')
        ->assertSee('Unmatched number')
        ->assertSee('oil change tomorrow');
});

test('estimate review renders relationship context with fleet open ros', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    $customer = Customer::query()->create([
        'first_name' => 'Acme',
        'last_name' => 'Plumbing',
        'phone' => '3035550100',
        'customer_type' => 'Fleet',
    ]);

    $repairOrders = [];

    foreach ([
        ['F-150', RepairOrderStatus::WaitingApproval, 4101],
        ['Transit', RepairOrderStatus::InProgress, 4102],
        ['Silverado', RepairOrderStatus::WaitingParts, 4103],
    ] as [$model, $status, $roNumber]) {
        $vehicle = Vehicle::query()->create([
            'customer_id' => $customer->id,
            'year' => 2018,
            'make' => 'Ford',
            'model' => $model,
        ]);

        $repairOrders[] = conversationContextRepairOrder($customer, $vehicle, $status, $roNumber);
    }

    $this->get(route('operations.repair-orders.show', $repairOrders[1]))
        ->assertOk()
        ->assertSee('Relationship Context')
        ->assertSee('RO #4101')
        ->assertSee('RO #4102')
        ->assertSee('RO #4103')
        ->assertSee('This RO')
        ->assertSee('Customer relationship projection');
});

function conversationContextRepairOrder(
    Customer $customer,
    Vehicle $vehicle,
    RepairOrderStatus $status,
    int $repairOrderNumber,
): RepairOrder {
    $repairOrder = RepairOrder::query()->create([
        'repair_order_id' => $repairOrderNumber,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => 'Relationship context panel test',
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Test concern',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    return $repairOrder;
}
