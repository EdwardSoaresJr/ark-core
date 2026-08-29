<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Portal\CustomerVehicleDetailProjection;
use App\Ark\Operations\Portal\PortalAccessChallenge;
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

test('portal vehicle detail shows active visit and documents', function () {
    Mail::fake();

    $customer = portalVehicleDetailCustomer();
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

    $closedRepairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Closed,
        'concern_summary' => 'Prior cooling work',
        'opened_at' => Carbon::parse('2026-01-08 09:00:00'),
        'closed_at' => Carbon::parse('2026-01-12 16:00:00'),
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $closedRepairOrder->id,
        'summary' => 'Cooling System Repair',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    EstimateDocument::query()->create([
        'repair_order_id' => $closedRepairOrder->id,
        'document_type' => FinancialDocumentType::Invoice,
        'document_number' => 1042,
        'snapshot_json' => ['totals' => []],
        'status' => 'issued',
        'generated_at' => Carbon::parse('2026-01-12 17:00:00'),
    ]);

    EstimateDocument::query()->create([
        'repair_order_id' => $activeRepairOrder->id,
        'document_type' => FinancialDocumentType::Estimate,
        'document_number' => 1043,
        'snapshot_json' => ['totals' => []],
        'status' => 'generated',
        'generated_at' => Carbon::parse('2026-03-14 10:00:00'),
    ]);

    $this->post(route('portal.access.challenges.store'), [
        'contact' => $customer->email,
    ]);

    $plainCode = portalVehicleDetailPlainCodeFromMailFake();

    $this->post(route('portal.access.verify.store'), [
        'code' => $plainCode,
    ])->assertRedirect(route('portal.home'));

    $this->get(route('portal.vehicles.show', $vehicle))
        ->assertOk()
        ->assertSee('2014 Jeep Wrangler')
        ->assertSee('Active visit')
        ->assertSee('Oil Cooler Housing Replacement')
        ->assertSee('Recent visits')
        ->assertSee('Cooling System Repair')
        ->assertSee('Documents')
        ->assertSee('2 available', false)
        ->assertSee('Invoice #1042');
});

test('portal vehicle detail review estimate links to authorization page', function () {
    Mail::fake();

    $customer = portalVehicleDetailCustomer();
    $vehicle = $customer->vehicles()->firstOrFail();

    $activeRepairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'concern_summary' => 'Overheating',
        'opened_at' => Carbon::parse('2026-06-16 09:00:00'),
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $activeRepairOrder->id,
        'summary' => 'Overheating',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'recommendation_intent' => 'maintenance',
        'position' => 0,
    ]);

    EstimateDocument::query()->create([
        'repair_order_id' => $activeRepairOrder->id,
        'document_type' => FinancialDocumentType::Estimate,
        'document_number' => 1578,
        'snapshot_json' => ['totals' => []],
        'status' => 'generated',
        'generated_at' => Carbon::parse('2026-06-16 10:00:00'),
    ]);

    $this->post(route('portal.access.challenges.store'), [
        'contact' => $customer->email,
    ]);

    $plainCode = portalVehicleDetailPlainCodeFromMailFake();

    $this->post(route('portal.access.verify.store'), [
        'code' => $plainCode,
    ])->assertRedirect(route('portal.home'));

    $this->get(route('portal.vehicles.show', $vehicle))
        ->assertOk()
        ->assertSee('Review estimate', false)
        ->assertSee('/portal/estimates/', false)
        ->assertSee('Documents')
        ->assertSee('1 available', false);

    $detail = app(CustomerVehicleDetailProjection::class)->forVehicle($vehicle, $customer);

    expect($detail['active_visit']['review_url'] ?? null)
        ->toStartWith(url('/portal/estimates/'))
        ->and($detail['active_visit']['review_label'])->toBe('estimate')
        ->and($detail['documents']['items'][0]['review_url'] ?? null)
        ->toStartWith(url('/portal/estimates/'))
        ->and($detail['documents']['items'][0]['review_label'])->toBe('Review estimate');
});

test('portal vehicle detail rejects another customers vehicle', function () {
    Mail::fake();

    $owner = portalVehicleDetailCustomer();
    $vehicle = $owner->vehicles()->firstOrFail();

    $other = Customer::query()->create([
        'first_name' => 'Other',
        'last_name' => 'Driver',
        'phone' => '7195558888',
        'email' => 'other.driver@example.test',
    ]);

    $this->post(route('portal.access.challenges.store'), [
        'contact' => $other->email,
    ]);

    $plainCode = portalVehicleDetailPlainCodeFromMailFake();

    $this->post(route('portal.access.verify.store'), [
        'code' => $plainCode,
    ])->assertRedirect(route('portal.home'));

    $this->get(route('portal.vehicles.show', $vehicle))->assertNotFound();
});

test('vehicle detail projection scopes facts to one vehicle', function () {
    $customer = portalVehicleDetailCustomer();
    $vehicle = $customer->vehicles()->firstOrFail();
    $otherVehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2021,
        'make' => 'Subaru',
        'model' => 'Outback',
        'plate' => 'SUB21',
    ]);

    $vehicleOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Closed,
        'concern_summary' => 'Jeep work',
        'closed_at' => Carbon::parse('2026-02-01 12:00:00'),
    ]);

    RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $otherVehicle->id,
        'status' => RepairOrderStatus::Closed,
        'concern_summary' => 'Subaru work',
        'closed_at' => Carbon::parse('2026-02-02 12:00:00'),
    ]);

    $detail = app(CustomerVehicleDetailProjection::class)->forVehicle($vehicle, $customer);

    expect($detail['recent_visits'])->toHaveCount(1)
        ->and($detail['recent_visits'][0]['summary'])->toBe('Jeep work');
});

function portalVehicleDetailCustomer(): Customer
{
    $customer = Customer::query()->create([
        'first_name' => 'Molly',
        'last_name' => 'Customer',
        'phone' => '7195551212',
        'email' => 'molly.vehicle@example.test',
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

function portalVehicleDetailPlainCodeFromMailFake(): string
{
    $sent = Mail::sent(PortalAccessCodeMail::class)->first();

    expect($sent)->not->toBeNull();

    return $sent->plainCode;
}
