<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Portal\PortalObservationSession;
use App\Ark\Operations\Portal\RepairOrderPortalSessionController;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('staff portal session logs customer into portal and redirects to vehicle records', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Staff',
        'last_name' => 'Bridge',
        'phone' => '7195550100',
        'email' => 'staff.bridge@example.test',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Subaru',
        'model' => 'Outback',
        'plate' => 'SUB18',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Oil change due.',
    ]);

    $vehiclePath = route('portal.vehicles.show', $vehicle, absolute: false);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.portal-session', [
            'repairOrder' => $repairOrder,
            'return' => $vehiclePath,
        ]))
        ->assertRedirect($vehiclePath);

    expect(Auth::guard('portal')->id())->toBe($customer->id)
        ->and(session(PortalObservationSession::SESSION_KEY))->not->toBeNull()
        ->and(session(RepairOrderPortalSessionController::STAFF_ACTOR_SESSION_KEY))->toBe($advisor->id);

    $this->get(route('portal.vehicles.show', $vehicle))
        ->assertOk();
});

test('staff portal session does not record portal observation events', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Observe',
        'last_name' => 'Suppressed',
        'phone' => '7195550103',
        'email' => 'observe.suppressed@example.test',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Tire rotation.',
    ]);

    $vehiclePath = route('portal.vehicles.show', $vehicle, absolute: false);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.portal-session', [
            'repairOrder' => $repairOrder,
            'return' => $vehiclePath,
        ]))
        ->assertRedirect($vehiclePath);

    $this->get(route('portal.vehicles.show', $vehicle))
        ->assertOk();

    $portalEventNames = [
        OperationalEventName::PortalVehicleViewed->value,
        OperationalEventName::PortalActiveVisitViewed->value,
        OperationalEventName::PortalCommunicationSectionViewed->value,
        OperationalEventName::PortalDocumentViewed->value,
        OperationalEventName::PortalDocumentDownloaded->value,
    ];

    expect(OperationalEvent::query()
        ->whereIn('event_name', $portalEventNames)
        ->count())->toBe(0);
});

test('staff portal session rejects invalid return urls', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Invalid',
        'last_name' => 'Return',
        'phone' => '7195550101',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Brake noise.',
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.portal-session', [
            'repairOrder' => $repairOrder,
            'return' => 'https://evil.test/portal/vehicles/'.$vehicle->id,
        ]))
        ->assertNotFound();
});

test('guest cannot start staff portal session', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Guest',
        'last_name' => 'Blocked',
        'phone' => '7195550102',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2021,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Check engine light.',
    ]);

    $this->get(route('operations.repair-orders.portal-session', $repairOrder))
        ->assertRedirect(route('login'));
});
