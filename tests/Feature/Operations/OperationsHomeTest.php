<?php

use App\Ark\Operations\Communications\CommunicationsNeedsYou;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Workstations\WorkstationPresence;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
});

test('work surface renders active repair order board as advisor home', function () {
    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Search job board', false)
        ->assertDontSee('Active Cars', false)
        ->assertDontSee('ops-advisor-home-cockpit', false)
        ->assertSee('Estimates', false)
        ->assertSee('Work in Progress', false)
        ->assertSee('Completed', false)
        ->assertDontSee('Customer Decisions')
        ->assertDontSee('Overview', false)
        ->assertDontSee('What needs action right now');
});

test('work surface comms strip lives on communications attention not advisor home', function () {
    Carbon::setTestNow('2026-06-03 08:00:00');

    $advisor = actingAsLearnCurrentAdvisor();
    $advisor->forceFill(['last_seen_at' => '2026-06-03 07:00:00'])->save();

    $customer = Customer::query()->create([
        'first_name' => 'Morning',
        'last_name' => 'Caller',
        'phone' => '7195551001',
    ]);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAhome001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551001',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551001',
        'status' => CallSessionStatus::Missed,
        'customer_id' => $customer->id,
        'started_at' => '2026-06-03 07:30:00',
    ]);

    $pressureCustomer = Customer::query()->create([
        'first_name' => 'Pressure',
        'last_name' => 'Customer',
        'phone' => '555-0101',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $pressureCustomer->id,
        'plate' => 'HOME1',
        'year' => 2019,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    RepairOrder::query()->create([
        'customer_id' => $pressureCustomer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Waiting on customer authorization.',
    ]);

    $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url())
        ->assertOk()
        ->assertSee('Morning Caller');

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Search job board', false)
        ->assertDontSee('Since Last Shift (1)');

    Carbon::setTestNow();
});

test('advisor home board shows waiting approval repair orders with estimate totals', function () {
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Triage Surface');
    $repairOrder->update(['concern_summary' => 'TRIAGE-SURFACE-SEED']);
    $repairOrder->lines()->update([
        'unit_price_cents' => 98765,
        'subtotal_cents' => 98765,
        'total_cents' => 98765,
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('RO #'.$repairOrder->repair_order_id, false)
        ->assertSee('Triage Surface', false)
        ->assertSee('$987.65');
});
