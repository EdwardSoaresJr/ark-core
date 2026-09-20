<?php

use App\Ark\Operations\Communications\CommunicationEventRecorder;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Portal\EstimateAccessToken;
use App\Ark\Operations\Portal\RepairOrderPortalActivity;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Mail\PortalAccessCodeMail;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('comms tab owns estimate sends and estimate views while portal tab shows portal engagement only', function () {
    ShopSettings::current()->update([
        'telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), [
            'comms_attention_gate_enabled' => false,
        ]),
    ]);

    $repairOrder = workspaceActivityRepairOrder();
    $plainToken = str_repeat('c', 64);
    EstimateAccessToken::createForPlainToken($repairOrder, $plainToken);

    app(CommunicationEventRecorder::class)->record(
        $repairOrder,
        OperationalCommunicationType::EstimateSent,
        OperationalCommunicationChannel::Sms,
        OperationalCommunicationDirection::Outbound,
        'Estimate portal link texted to customer.',
    );

    $this->get(route('portal.estimates.show', ['token' => $plainToken]))
        ->assertOk();

    $advisor = actingAsLearnCurrentAdvisor();

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.workspace-tabs.show', ['repairOrder' => $repairOrder, 'tab' => 'comms']))
        ->assertOk()
        ->assertDontSee('Estimate activity', false)
        ->assertSee('Customer opened the estimate portal link.', false)
        ->assertSee('ops-comms-workspace__bubble', false)
        ->assertSee('Conversation', false);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.workspace-tabs.show', ['repairOrder' => $repairOrder, 'tab' => 'portal']))
        ->assertOk()
        ->assertSee('Vehicle records, documents, and portal navigation', false)
        ->assertSee('No customer portal engagement recorded for this repair order yet.', false);

    expect(app(RepairOrderPortalActivity::class)->countForRepairOrder($repairOrder))->toBe(0);
});

test('portal tab lists vehicle portal engagement for the repair order', function () {
    Mail::fake();

    $customer = Customer::query()->create([
        'first_name' => 'Molly',
        'last_name' => 'Customer',
        'phone' => '7195551212',
        'email' => 'molly.workspace@example.test',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2014,
        'make' => 'Jeep',
        'model' => 'Wrangler',
        'plate' => 'JEEP14',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'concern_summary' => 'Cooling system follow-up',
        'opened_at' => now(),
    ]);

    $this->post(route('portal.access.challenges.store'), [
        'contact' => $customer->email,
    ]);

    $sent = Mail::sent(PortalAccessCodeMail::class)->first();

    $this->post(route('portal.access.verify.store'), [
        'code' => $sent->plainCode,
    ])->assertRedirect(route('portal.home'));

    $this->get(route('portal.vehicles.show', $vehicle))->assertOk();

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->get(route('operations.repair-orders.workspace-tabs.show', ['repairOrder' => $repairOrder, 'tab' => 'portal']))
        ->assertOk()
        ->assertSee('Vehicle records opened', false);

    expect(app(RepairOrderPortalActivity::class)->forRepairOrder($repairOrder)->pluck('event_name')->all())
        ->toContain(OperationalEventName::PortalVehicleViewed->value);
});

function workspaceActivityRepairOrder(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Morgan',
        'last_name' => 'Brown',
        'phone' => '555-0144',
        'email' => 'customer@example.test',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2013,
        'make' => 'Chevrolet',
        'model' => 'Tahoe',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'A/C not cold',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'A/C not cold',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'A/C performance diagnostic',
        'quantity' => '1.00',
        'unit_price_cents' => 15593,
        'subtotal_cents' => 15593,
        'total_cents' => 15593,
    ]);

    return $repairOrder->fresh(['customer', 'vehicle', 'concerns.lines', 'lines', 'communicationEvents']);
}
