<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\HtmlPdfBuilder;
use App\Ark\Operations\Documents\OperationalSheetPresenter;
use App\Ark\Operations\RepairOrders\AssignRepairActionOwnerAction;
use App\Ark\Operations\RepairOrders\RepairActionOwnerType;
use App\Ark\Operations\RepairOrders\RepairActionOwnershipEvent;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroup;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('repair action ownership assigns transfers and never copies', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $edward = User::factory()->create(['name' => 'Edward'])->assignRole(ArkRole::Technician->value);
    $landon = User::factory()->create(['name' => 'Landon'])->assignRole(ArkRole::Technician->value);

    [$repairOrder, $workGroup] = repairActionOwnershipFixture();

    $assign = app(AssignRepairActionOwnerAction::class);
    $assign->assign($workGroup, $edward->id, $advisor);

    expect($workGroup->fresh()->owner_user_id)->toBe($edward->id)
        ->and(RepairActionOwnershipEvent::query()->where('repair_order_work_group_id', $workGroup->id)->count())->toBe(1);

    $assign->assign($workGroup->fresh(), $landon->id, $advisor, reason: 'Shop balancing');

    $events = RepairActionOwnershipEvent::query()
        ->where('repair_order_work_group_id', $workGroup->id)
        ->orderBy('id')
        ->get();

    expect($workGroup->fresh()->owner_user_id)->toBe($landon->id)
        ->and($events)->toHaveCount(2)
        ->and($events[0]->event_kind)->toBe(RepairActionOwnershipEvent::KIND_ASSIGNED)
        ->and($events[1]->event_kind)->toBe(RepairActionOwnershipEvent::KIND_TRANSFERRED)
        ->and($events[1]->reason)->toBe('Shop balancing')
        ->and($events[1]->from_owner_user_id)->toBe($edward->id)
        ->and($events[1]->to_owner_user_id)->toBe($landon->id);
});

test('tech sheet for one owner excludes another technicians package', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $edward = User::factory()->create(['name' => 'Edward'])->assignRole(ArkRole::Technician->value);
    $caleb = User::factory()->create(['name' => 'Caleb'])->assignRole(ArkRole::Technician->value);
    $landon = User::factory()->create(['name' => 'Landon'])->assignRole(ArkRole::Technician->value);

    $customer = Customer::query()->create([
        'first_name' => 'Multi',
        'last_name' => 'Tech',
        'phone' => '555-0100',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Tacoma',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'assigned_technician_id' => $edward->id,
        'concern_summary' => 'Mixed work packages',
    ]);
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Mixed work',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'position' => 1,
    ]);

    $diag = RepairOrderWorkGroup::query()->create([
        'repair_order_concern_id' => $concern->id,
        'title' => 'Diagnosis',
        'position' => 1,
        'owner_type' => RepairActionOwnerType::Technician,
        'owner_user_id' => $edward->id,
    ]);
    $oil = RepairOrderWorkGroup::query()->create([
        'repair_order_concern_id' => $concern->id,
        'title' => 'Oil Service',
        'position' => 2,
        'owner_type' => RepairActionOwnerType::Technician,
        'owner_user_id' => $caleb->id,
    ]);
    $starter = RepairOrderWorkGroup::query()->create([
        'repair_order_concern_id' => $concern->id,
        'title' => 'Starter Replacement',
        'position' => 3,
        'owner_type' => RepairActionOwnerType::Technician,
        'owner_user_id' => $landon->id,
    ]);

    foreach ([
        [$diag, 'Diag labor', 1.0],
        [$oil, 'Oil change', 1.0],
        [$starter, 'Starter R&R', 3.2],
    ] as [$group, $description, $hours]) {
        RepairOrderLine::query()->create([
            'repair_order_id' => $repairOrder->id,
            'repair_order_concern_id' => $concern->id,
            'repair_order_work_group_id' => $group->id,
            'type' => RepairOrderLineType::Labor,
            'description' => $description,
            'quantity' => number_format($hours, 2, '.', ''),
            'unit_price_cents' => 15000,
        ]);
    }

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'repair_order_work_group_id' => $oil->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Oil filter',
        'quantity' => '1.00',
        'unit_price_cents' => 1200,
    ]);

    $calebSheet = app(OperationalSheetPresenter::class)->tech($repairOrder->fresh(), $caleb);

    expect($calebSheet['packages'])->toHaveCount(1)
        ->and($calebSheet['packages'][0]['title'])->toBe('Oil Service')
        ->and(collect($calebSheet['packages'][0]['parts'])->pluck('description')->all())->toContain('Oil filter')
        ->and(collect($calebSheet['packages'])->pluck('title')->all())->not->toContain('Diagnosis')
        ->and(collect($calebSheet['packages'])->pluck('title')->all())->not->toContain('Starter Replacement');

    $pdf = Mockery::mock(HtmlPdfBuilder::class);
    $pdf->shouldReceive('toPdfBytes')->once()->andReturn('%PDF-fake');
    app()->instance(HtmlPdfBuilder::class, $pdf);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.sheets.tech.pdf', [$repairOrder, 'owner' => $caleb->id]))
        ->assertOk();
});

test('advisor can transfer repair action owner via worksheet', function () {
    $advisor = User::factory()->create()->assignRole([ArkRole::Admin->value, ArkRole::Advisor->value]);
    $edward = User::factory()->create(['name' => 'Edward'])->assignRole(ArkRole::Technician->value);
    $landon = User::factory()->create(['name' => 'Landon'])->assignRole(ArkRole::Technician->value);

    [$repairOrder, $workGroup] = repairActionOwnershipFixture($edward);

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.work-groups.owner.update', [$repairOrder, $workGroup]), [
            'owner_user_id' => $landon->id,
            'reason' => 'Shop balancing',
            'opened_estimate_version' => $repairOrder->estimate_version ?? 1,
        ])
        ->assertRedirect();

    expect($workGroup->fresh()->owner_user_id)->toBe($landon->id);
});

/**
 * @return array{0: RepairOrder, 1: RepairOrderWorkGroup}
 */
function repairActionOwnershipFixture(?User $owner = null): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Own',
        'last_name' => 'Ship',
        'phone' => '555-0111',
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
        'assigned_technician_id' => $owner?->id,
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
    ]);

    return [$repairOrder->fresh(), $workGroup->fresh()];
}
