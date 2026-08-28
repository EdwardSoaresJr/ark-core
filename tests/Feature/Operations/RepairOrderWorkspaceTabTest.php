<?php

use App\Ark\Operations\Communications\CommunicationEventRecorder;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('estimate review page does not render lazy tab panels on first paint', function () {
    $repairOrder = workspaceTabRepairOrder();

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('data-workspace-tab-panel="comms"', false)
        ->assertSee('data-workspace-tab-panel="portal"', false)
        ->assertDontSee('id="communication-rail"', false)
        ->assertDontSee('Estimate activity', false);
});

test('estimate review renders declined concern scopes without error', function () {
    $repairOrder = workspaceTabRepairOrder();

    $repairOrder->concerns->first()?->update([
        'disposition' => RepairOrderConcernDisposition::Declined,
    ]);

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('ops-review-concern--declined', false);
});

test('workspace tab endpoint returns comms panel html on demand', function () {
    $repairOrder = workspaceTabRepairOrder();

    app(CommunicationEventRecorder::class)->record(
        $repairOrder,
        OperationalCommunicationType::EstimateViewed,
        OperationalCommunicationChannel::Website,
        OperationalCommunicationDirection::Inbound,
        'Customer opened the estimate portal link.',
    );

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->get(route('operations.repair-orders.workspace-tabs.show', ['repairOrder' => $repairOrder, 'tab' => 'comms']))
        ->assertOk()
        ->assertSee('id="communication-rail"', false)
        ->assertSee('Send Estimate', false)
        ->assertDontSee('Estimate activity', false)
        ->assertSee('ops-comms-workspace__bubble', false)
        ->assertSee('Customer opened the estimate portal link.', false);
});

test('workspace tab endpoint renders comms timeline rows from unified event entries', function () {
    \App\Ark\Operations\Settings\ShopSettings::current()->update([
        'telephony_call_flow' => array_merge(\App\Ark\Operations\Settings\ShopSettings::defaultTelephonyCallFlow(), [
            'comms_attention_gate_enabled' => false,
        ]),
    ]);

    $repairOrder = workspaceTabRepairOrder();

    \App\Ark\Operations\Telephony\CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAworkspacecomms01',
        'direction' => \App\Ark\Operations\Telephony\CallSessionDirection::Inbound,
        'from_number' => '+15550199',
        'to_number' => '+17195559999',
        'normalized_from' => '5550199',
        'status' => \App\Ark\Operations\Telephony\CallSessionStatus::Completed,
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $repairOrder->customer_id,
        'started_at' => now(),
        'worked_at' => now(),
    ]);

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->get(route('operations.repair-orders.workspace-tabs.show', ['repairOrder' => $repairOrder, 'tab' => 'comms']))
        ->assertOk()
        ->assertSee('Conversation', false)
        ->assertSee('ops-comms-workspace__bubble', false)
        ->assertSee('Send Estimate', false);
});

test('workspace tab endpoint returns parts panel html on demand', function () {
    $repairOrder = workspaceTabRepairOrder(withPart: true);

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->get(route('operations.repair-orders.workspace-tabs.show', ['repairOrder' => $repairOrder, 'tab' => 'parts']))
        ->assertOk()
        ->assertSee('Part Lines', false)
        ->assertSee('Front brake pads', false);
});

function workspaceTabRepairOrder(bool $withPart = false): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Tab',
        'last_name' => 'Loader',
        'phone' => '555-0199',
        'email' => 'tab.loader@example.test',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Pilot',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Brake noise',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brakes',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'position' => 1,
    ]);

    if ($withPart) {
        RepairOrderLine::query()->create([
            'repair_order_id' => $repairOrder->id,
            'repair_order_concern_id' => $concern->id,
            'type' => RepairOrderLineType::Part,
            'description' => 'Front brake pads',
            'quantity' => '1.00',
            'unit_price_cents' => 12000,
            'part_cost_cents' => 6000,
            'subtotal_cents' => 12000,
            'total_cents' => 12000,
        ]);
    }

    return $repairOrder->fresh(['customer', 'vehicle', 'concerns.lines', 'lines']);
}
