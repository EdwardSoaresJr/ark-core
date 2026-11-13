<?php

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Intake\VisitCheckInAction;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('known customer without an open visit shows Check In prefilling the customer', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Known',
        'last_name' => 'Patron',
        'phone' => '7195556101',
    ]);

    $action = app(VisitCheckInAction::class)->forContext([
        'customer' => ['id' => $customer->id, 'phone' => $customer->phone],
        'customer_id' => $customer->id,
    ], $advisor);

    expect($action)->not->toBeNull()
        ->and($action['key'])->toBe('check_in')
        ->and($action['label'])->toBe('Check In')
        ->and($action['style'])->toBe('primary')
        ->and($action['url'])->toContain('customer_id='.$customer->id)
        ->and($action['url'])->toContain(route('operations.intake.create', [], false));
});

test('unknown contact shows Check In prefilling the conversation phone', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $action = app(VisitCheckInAction::class)->forContext([
        'thread' => [
            'normalized_phone' => '7195556102',
            'phone' => '7195556102',
        ],
    ], $advisor);

    expect($action)->not->toBeNull()
        ->and($action['key'])->toBe('check_in')
        ->and($action['label'])->toBe('Check In')
        ->and($action['url'])->toContain('phone=7195556102');
});

test('open repair order is recognized and Check In becomes New visit', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Open',
        'last_name' => 'Visit',
        'phone' => '7195556103',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Brakes',
    ]);

    $action = app(VisitCheckInAction::class)->forContext([
        'customer' => ['id' => $customer->id],
        'customer_id' => $customer->id,
        'vehicle' => ['id' => $vehicle->id],
        'primary_ro' => ['repair_order_id' => $repairOrder->repair_order_id],
        'current_visit' => [
            'vehicle_id' => $vehicle->id,
            'repair_order_id' => $repairOrder->repair_order_id,
        ],
    ], $advisor);

    expect($action)->not->toBeNull()
        ->and($action['key'])->toBe('new_visit')
        ->and($action['label'])->toBe('New visit')
        ->and($action['style'])->toBe('secondary')
        ->and($action['hint'])->toContain('open repair order')
        ->and($action['url'])->toContain('customer_id='.$customer->id);
});

test('staff without repair-order manage cannot see Check In', function (): void {
    $viewer = User::factory()->create()->givePermissionTo([
        ArkCapability::OperationsAccess->value,
        'customers.manage',
        'repair_orders.view',
    ]);
    expect($viewer->can(ArkCapability::RepairOrdersManage->value))->toBeFalse();

    $customer = Customer::query()->create([
        'first_name' => 'No',
        'last_name' => 'Manage',
        'phone' => '7195556104',
    ]);

    $action = app(VisitCheckInAction::class)->forContext([
        'customer' => ['id' => $customer->id],
        'customer_id' => $customer->id,
    ], $viewer);

    expect($action)->toBeNull();
});

test('lead concern and arrived appointment prefill Check In without treating RO status as check-in', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Arrived',
        'last_name' => 'Guest',
        'phone' => '7195556105',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);
    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'contact_name' => 'Arrived Guest',
        'contact_phone' => '7195556105',
        'customer_id' => $customer->id,
        'concern' => 'Rattle under load',
    ]);
    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'lead_id' => $lead->id,
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => now()->subMinutes(10),
        'ends_at' => now()->addHour(),
        'concern' => 'Rattle under load',
        'status' => AppointmentStatus::Arrived,
    ]);

    $action = app(VisitCheckInAction::class)->forContext([
        'customer' => ['id' => $customer->id],
        'customer_id' => $customer->id,
        'lead_id' => $lead->id,
        'vehicle' => ['id' => $vehicle->id],
        'current_visit' => ['vehicle_id' => $vehicle->id],
    ], $advisor);

    expect($action)->not->toBeNull()
        ->and($action['key'])->toBe('check_in')
        ->and($action['hint'])->toBe('Customer arrived. Check In opens the repair order for this visit.')
        ->and($action['url'])->toContain('appointment_id='.$appointment->id)
        ->and($action['url'])->toContain('vehicle_id='.$vehicle->id);
});
