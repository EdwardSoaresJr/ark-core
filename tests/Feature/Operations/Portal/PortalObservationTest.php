<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Portal\PortalObservationSession;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Mail\PortalAccessCodeMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    ShopSettings::current()->update(['shop_name' => 'Demo Auto Repair']);
});

test('portal login starts an observation session id', function () {
    Mail::fake();

    $customer = portalObservationCustomer();

    $this->post(route('portal.access.challenges.store'), [
        'contact' => $customer->email,
    ]);

    $sent = Mail::sent(PortalAccessCodeMail::class)->first();

    $this->post(route('portal.access.verify.store'), [
        'code' => $sent->plainCode,
    ])->assertRedirect(route('portal.home'));

    expect(session(PortalObservationSession::SESSION_KEY))->toBeString()->not->toBe('');
});

test('portal vehicle page records vehicle and active visit observation facts', function () {
    Mail::fake();

    $customer = portalObservationCustomer();
    $vehicle = $customer->vehicles()->firstOrFail();

    $activeRepairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'concern_summary' => 'Cooling system follow-up',
        'opened_at' => Carbon::parse('2026-03-14 09:00:00'),
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $activeRepairOrder->id,
        'summary' => 'Oil Cooler Housing Replacement',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    portalObservationSignIn($customer);

    $sessionId = session(PortalObservationSession::SESSION_KEY);

    $this->get(route('portal.vehicles.show', $vehicle))->assertOk();

    $events = OperationalEvent::query()
        ->whereIn('event_name', [
            OperationalEventName::PortalVehicleViewed->value,
            OperationalEventName::PortalActiveVisitViewed->value,
        ])
        ->orderBy('id')
        ->get();

    expect($events)->toHaveCount(2)
        ->and($events[0]->event_name)->toBe(OperationalEventName::PortalVehicleViewed->value)
        ->and($events[0]->payload_json['portal_session_id'])->toBe($sessionId)
        ->and($events[0]->payload_json['has_active_visit'])->toBeTrue()
        ->and($events[1]->event_name)->toBe(OperationalEventName::PortalActiveVisitViewed->value)
        ->and($events[1]->payload_json['repair_order_id'])->toBe($activeRepairOrder->repair_order_id);
});

function portalObservationCustomer(): Customer
{
    $customer = Customer::query()->create([
        'first_name' => 'Molly',
        'last_name' => 'Customer',
        'phone' => '7195551212',
        'email' => 'molly.observation@example.test',
    ]);

    Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2014,
        'make' => 'Jeep',
        'model' => 'Wrangler',
        'plate' => 'JEEP14',
    ]);

    return $customer;
}

function portalObservationSignIn(Customer $customer): void
{
    test()->post(route('portal.access.challenges.store'), [
        'contact' => $customer->email,
    ]);

    $sent = Mail::sent(PortalAccessCodeMail::class)->first();

    test()->post(route('portal.access.verify.store'), [
        'code' => $sent->plainCode,
    ])->assertRedirect(route('portal.home'));
}
