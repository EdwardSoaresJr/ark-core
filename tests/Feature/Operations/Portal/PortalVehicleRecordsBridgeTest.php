<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Portal\CreateOrReuseEstimateAccessTokenAction;
use App\Ark\Operations\Portal\PortalIntendedUrl;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Mail\PortalAccessCodeMail;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    ShopSettings::current()->update(['shop_name' => 'Demo Auto Repair']);
});

test('estimate page links guests to portal access with vehicle return url', function () {
    [$repairOrder, $plainToken] = portalVehicleRecordsEstimate();

    $vehiclePath = route('portal.vehicles.show', $repairOrder->vehicle, absolute: false);

    $this->get(route('portal.estimates.show', ['token' => $plainToken]))
        ->assertOk()
        ->assertSee('Access all records for this vehicle', false)
        ->assertSee('Sign in to view records', false)
        ->assertSee('return='.urlencode($vehiclePath), false);
});

test('estimate page links authenticated portal customers directly to vehicle records', function () {
    Mail::fake();

    [$repairOrder, $plainToken] = portalVehicleRecordsEstimate();
    $customer = $repairOrder->customer;

    portalVehicleRecordsSignIn($customer);

    $this->get(route('portal.estimates.show', ['token' => $plainToken]))
        ->assertOk()
        ->assertSee('See all records for this vehicle', false)
        ->assertSee('Open vehicle records', false)
        ->assertSee(route('portal.vehicles.show', $repairOrder->vehicle, absolute: false), false);
});

test('portal access returns customer to intended vehicle after verification', function () {
    Mail::fake();

    $customer = Customer::query()->create([
        'first_name' => 'Molly',
        'last_name' => 'Customer',
        'phone' => '7195551212',
        'email' => 'molly.bridge@example.test',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2014,
        'make' => 'Jeep',
        'model' => 'Wrangler',
        'plate' => 'JEEP14',
    ]);

    $vehiclePath = route('portal.vehicles.show', $vehicle, absolute: false);

    $this->get(route('portal.access', ['return' => $vehiclePath]))
        ->assertOk();

    expect(session(PortalIntendedUrl::SESSION_KEY))->toBe($vehiclePath);

    $this->post(route('portal.access.challenges.store'), [
        'contact' => $customer->email,
    ]);

    $sent = Mail::sent(PortalAccessCodeMail::class)->first();

    $this->post(route('portal.access.verify.store'), [
        'code' => $sent->plainCode,
    ])->assertRedirect($vehiclePath);
});

test('portal intended url rejects non portal paths', function () {
    expect(PortalIntendedUrl::validate('https://evil.test/portal/home'))->toBeNull()
        ->and(PortalIntendedUrl::validate('/operations/repair-orders/1'))->toBeNull()
        ->and(PortalIntendedUrl::validate('/portal/vehicles/1'))->toBe('/portal/vehicles/1')
        ->and(PortalIntendedUrl::validate('/book'))->toBe('/book')
        ->and(PortalIntendedUrl::validate('/book?vehicle=1'))->toBe('/book?vehicle=1');
});

/**
 * @return array{0: RepairOrder, 1: string}
 */
function portalVehicleRecordsEstimate(): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Morgan',
        'last_name' => 'Brown',
        'phone' => '7195553434',
        'email' => 'morgan.bridge@example.test',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Jeep',
        'model' => 'Grand Cherokee',
        'plate' => 'JEEP18',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Brake service',
    ]);

    $result = app(CreateOrReuseEstimateAccessTokenAction::class)->execute($repairOrder);

    return [$repairOrder, $result->plainToken];
}

function portalVehicleRecordsSignIn(Customer $customer): void
{
    test()->post(route('portal.access.challenges.store'), [
        'contact' => $customer->email,
    ]);

    $sent = Mail::sent(PortalAccessCodeMail::class)->first();

    test()->post(route('portal.access.verify.store'), [
        'code' => $sent->plainCode,
    ]);
}
