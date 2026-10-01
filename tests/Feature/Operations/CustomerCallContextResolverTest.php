<?php

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('caller context resolves customer vehicles open ros conversation and workflow posture', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $customer = Customer::query()->create([
        'first_name' => 'John',
        'last_name' => 'Smith',
        'phone' => '7195551234',
        'email' => 'john@example.test',
        'customer_type' => 'Retail',
    ]);

    $outback = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2014,
        'make' => 'Subaru',
        'model' => 'Outback',
        'plate' => 'OUT123',
    ]);

    $transit = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Ford',
        'model' => 'Transit',
        'plate' => 'FLT200',
    ]);

    $awaiting = repairOrderForCallerContext($customer, $outback, RepairOrderStatus::WaitingApproval, 1432);
    $inProgress = repairOrderForCallerContext($customer, $transit, RepairOrderStatus::InProgress, 1433);

    $awaiting->communicationEvents()->create([
        'event_type' => OperationalCommunicationType::EstimateViewed,
        'channel' => OperationalCommunicationChannel::Email,
        'direction' => OperationalCommunicationDirection::Inbound,
        'summary' => 'Customer viewed estimate yesterday.',
        'occurred_at' => now()->subDay(),
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    app(ConversationRecorder::class)->recordAdvisorLog(
        $awaiting,
        $advisor,
        OperationalCommunicationChannel::Sms,
        OperationalCommunicationDirection::Inbound,
        'Can I pick it up Friday?',
    );

    $context = app(CustomerCallContextResolver::class)->resolve('719-555-1234');

    expect($context)->not->toBeNull()
        ->and($context->hasMatch())->toBeTrue()
        ->and($context->customer?->id)->toBe($customer->id)
        ->and($context->openRepairOrders)->toHaveCount(2)
        ->and($context->openRepairOrders->pluck('repairOrder.repair_order_id')->all())->toEqual([1433, 1432])
        ->and($context->vehicles)->toHaveCount(2)
        ->and($context->openRepairOrders->firstWhere(fn ($row) => $row->repairOrder->repair_order_id === 1432)?->workflowNextAction)
        ->toBe('Follow up viewed estimate')
        ->and($context->recentConversationMessages->first()?->body)->toBe('Can I pick it up Friday?');
});

test('fleet customer shows multiple open ros without choosing one', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Acme',
        'last_name' => 'Plumbing',
        'phone' => '3035550100',
        'customer_type' => 'Fleet',
    ]);

    foreach ([
        ['F-150', RepairOrderStatus::WaitingApproval, 1001],
        ['Transit', RepairOrderStatus::InProgress, 1002],
        ['Silverado', RepairOrderStatus::WaitingParts, 1003],
    ] as [$model, $status, $roNumber]) {
        $vehicle = Vehicle::query()->create([
            'customer_id' => $customer->id,
            'year' => 2018,
            'make' => 'Ford',
            'model' => $model,
        ]);

        repairOrderForCallerContext($customer, $vehicle, $status, $roNumber);
    }

    $context = app(CustomerCallContextResolver::class)->resolve('303-555-0100');

    expect($context?->openRepairOrders)->toHaveCount(3)
        ->and($context?->openRepairOrders->pluck('vehicle.model')->sort()->values()->all())
        ->toEqual(['F-150', 'Silverado', 'Transit']);
});

test('caller lookup page renders resolved context', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    $customer = Customer::query()->create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'phone' => '5550100999',
        'customer_type' => 'Retail',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    repairOrderForCallerContext($customer, $vehicle, RepairOrderStatus::WaitingApproval, 2001);

    $this->get(route('operations.caller-lookup', ['phone' => '555-010-0999']))
        ->assertOk()
        ->assertSee('Jane Doe')
        ->assertSee('2019 Honda Civic')
        ->assertSee('RO #2001')
        ->assertSee('Awaiting Approval')
        ->assertSee('Lookup Caller');
});

function repairOrderForCallerContext(
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
        'concern_summary' => 'Caller context test',
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Test concern',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    return $repairOrder;
}
