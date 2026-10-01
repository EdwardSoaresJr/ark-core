<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\HtmlPdfBuilder;
use App\Ark\Operations\Documents\OperationalSheetPresenter;
use App\Ark\Operations\RepairOrders\RepairActionOwnerType;
use App\Ark\Operations\RepairOrders\RepairActionStatus;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroup;
use App\Ark\Operations\RepairOrders\UpdateRepairActionCommunicationAction;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('repair action status and latest update replace not append', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $landon = User::factory()->create(['name' => 'Landon'])->assignRole(ArkRole::Technician->value);

    [$repairOrder, $workGroup] = repairActionCommunicationFixture($landon);

    $update = app(UpdateRepairActionCommunicationAction::class);
    $update->update(
        $workGroup,
        $advisor,
        status: RepairActionStatus::InProgress,
        latestUpdate: 'Removed intake.',
    );

    $update->update(
        $workGroup->fresh(),
        $advisor,
        status: RepairActionStatus::WaitingParts,
        latestUpdate: "Starter removed.\nWaiting for OE replacement.",
    );

    $fresh = $workGroup->fresh();

    expect($fresh->status)->toBe(RepairActionStatus::WaitingParts)
        ->and($fresh->latest_update)->toBe("Starter removed.\nWaiting for OE replacement.")
        ->and($fresh->latest_update)->not->toContain('Removed intake.');
});

test('advisor can save repair action communication via worksheet', function () {
    $advisor = User::factory()->create()->assignRole([ArkRole::Admin->value, ArkRole::Advisor->value]);
    $landon = User::factory()->create(['name' => 'Landon'])->assignRole(ArkRole::Technician->value);

    [$repairOrder, $workGroup] = repairActionCommunicationFixture($landon);

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.work-groups.communication.update', [$repairOrder, $workGroup]), [
            'status' => RepairActionStatus::WaitingApproval->value,
            'latest_update' => 'Waiting for customer approval.',
            'opened_estimate_version' => $repairOrder->estimate_version ?? 1,
        ])
        ->assertRedirect();

    expect($workGroup->fresh()->status)->toBe(RepairActionStatus::WaitingApproval)
        ->and($workGroup->fresh()->latest_update)->toBe('Waiting for customer approval.');
});

test('tech sheet includes status and latest update for owned package', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $landon = User::factory()->create(['name' => 'Landon'])->assignRole(ArkRole::Technician->value);

    $customer = Customer::query()->create([
        'first_name' => 'Jeep',
        'last_name' => 'Owner',
        'phone' => '555-0199',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Jeep',
        'model' => 'Wrangler',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'concern_summary' => 'Starter',
    ]);
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'No start',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'position' => 1,
    ]);
    $workGroup = RepairOrderWorkGroup::query()->create([
        'repair_order_concern_id' => $concern->id,
        'title' => 'Starter Replacement',
        'position' => 1,
        'owner_type' => RepairActionOwnerType::Technician,
        'owner_user_id' => $landon->id,
        'status' => RepairActionStatus::WaitingParts,
        'latest_update' => 'Starter removed. Waiting for OE replacement.',
    ]);
    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'repair_order_work_group_id' => $workGroup->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Starter R&R',
        'quantity' => '3.20',
        'unit_price_cents' => 15000,
    ]);

    $sheet = app(OperationalSheetPresenter::class)->tech($repairOrder->fresh(), $landon);

    expect($sheet['packages'])->toHaveCount(1)
        ->and($sheet['packages'][0]['status_label'])->toBe('Waiting Parts')
        ->and($sheet['packages'][0]['latest_update'])->toContain('Waiting for OE')
        ->and($sheet['technician_name'])->toBe('Landon');

    $pdf = Mockery::mock(HtmlPdfBuilder::class);
    $pdf->shouldReceive('toPdfBytes')->once()->andReturn('%PDF-fake');
    app()->instance(HtmlPdfBuilder::class, $pdf);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.sheets.tech.pdf', [$repairOrder, 'owner' => $landon->id]))
        ->assertOk();
});

test('repair order owner summary prefers repair action owners over primary technician column', function () {
    $edward = User::factory()->create(['name' => 'Edward'])->assignRole(ArkRole::Technician->value);
    $landon = User::factory()->create(['name' => 'Landon'])->assignRole(ArkRole::Technician->value);

    [$repairOrder, $workGroup] = repairActionCommunicationFixture($landon);
    $repairOrder->forceFill(['assigned_technician_id' => $edward->id])->save();

    expect($repairOrder->fresh()->repairActionOwnerSummary())->toBe('Landon')
        ->and($repairOrder->fresh()->technicianOwnershipLabel())->toBe('Landon')
        ->and($repairOrder->fresh()->hasRepairActionOwner())->toBeTrue();
});

/**
 * @return array{0: RepairOrder, 1: RepairOrderWorkGroup}
 */
function repairActionCommunicationFixture(?User $owner = null): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Comm',
        'last_name' => 'Slice',
        'phone' => '555-0188',
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
        'estimate_version' => 1,
        'concern_summary' => 'Starter work',
    ]);
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Starter',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'position' => 1,
    ]);
    $workGroup = RepairOrderWorkGroup::query()->create([
        'repair_order_concern_id' => $concern->id,
        'title' => 'Starter Replacement',
        'position' => 1,
        'owner_type' => RepairActionOwnerType::Technician,
        'owner_user_id' => $owner?->id,
        'status' => RepairActionStatus::Pending,
    ]);

    return [$repairOrder->fresh(), $workGroup->fresh()];
}
