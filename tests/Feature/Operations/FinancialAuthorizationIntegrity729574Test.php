<?php

use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\GenerateInvoiceSnapshotAction;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderAuthorizationStoreController;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\ScopeProductionStatus;
use App\Ark\Operations\RepairOrders\UnresolvedAuthorizationReport;
use App\Ark\Operations\RepairOrders\WorkCompletionAuthorization;
use App\Ark\Operations\Reports\OperationalReportTotals;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    ShopSettings::current()->update(['portal_signature_required' => false]);

    $this->advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($this->advisor);
});

test('financial authorization integrity covers reports, unresolved work, and staff presentation (#72 #74 #95)', function () {
    [$repairOrder, $concern, $labor, $part] = finAuthApprovedRepairOrder();
    $authorization = app(WorkCompletionAuthorization::class);
    $calculator = app(EstimateTotalsCalculator::class);

    $approvedBefore = $calculator->approvedTotalsForRead($repairOrder->fresh(['lines.concern']))->totalCents();
    $approvedLaborBefore = OperationalReportTotals::laborCentsByRepairOrderId([$repairOrder->id])[$repairOrder->id];

    expect($approvedBefore)->toBeGreaterThan(0)
        ->and(OperationalReportTotals::soldTotalCentsByRepairOrderId([$repairOrder->id])[$repairOrder->id])->toBe($approvedBefore)
        ->and($approvedLaborBefore)->toBe((int) $labor->fresh()->subtotal_cents);

    // 1. Newly added unapproved part on an approved concern.
    $added = finAuthStorePart($repairOrder, $concern, 'Extra caliper bolt', '1.00', '50.00');
    $repairOrder = $repairOrder->fresh(['concerns', 'lines.concern']);
    $addedPending = $authorization->pendingApprovalCents($repairOrder, $added->fresh());

    expect($authorization->customerApprovalCovers($added->fresh(['concern'])))->toBeFalse()
        ->and($authorization->linesRequiringAuthorization($repairOrder)->pluck('id')->all())->toContain($added->id)
        ->and($calculator->approvedTotalsForRead($repairOrder)->totalCents())->toBe($approvedBefore)
        ->and(OperationalReportTotals::soldTotalCentsByRepairOrderId([$repairOrder->id])[$repairOrder->id])->toBe($approvedBefore)
        ->and($addedPending)->toBeGreaterThan(0);

    // 2. Increased labor price after authorization (more billed hours at the same rate).
    finAuthPatchLabor($repairOrder, $concern, $labor, quantity: '1.30');
    $repairOrder = $repairOrder->fresh(['concerns', 'lines.concern']);
    $labor = $labor->fresh();
    $laborPending = $authorization->pendingApprovalCents($repairOrder, $labor);

    expect($authorization->customerApprovalCovers($labor->fresh(['concern'])))->toBeTrue()
        ->and($authorization->linesRequiringAuthorization($repairOrder)->pluck('id')->all())->toContain($labor->id)
        ->and($laborPending)->toBeGreaterThan(0)
        ->and($calculator->approvedTotalsForRead($repairOrder)->totalCents())->toBe($approvedBefore)
        ->and(OperationalReportTotals::laborCentsByRepairOrderId([$repairOrder->id])[$repairOrder->id])->toBe($approvedLaborBefore);

    // 3. Increased part quantity after authorization.
    finAuthPatchPart($repairOrder, $concern, $part, quantity: '2.00');
    $repairOrder = $repairOrder->fresh(['concerns', 'lines.concern']);
    $part = $part->fresh();
    $partPending = $authorization->pendingApprovalCents($repairOrder, $part);

    expect($authorization->linesRequiringAuthorization($repairOrder)->pluck('id')->all())->toContain($part->id)
        ->and($partPending)->toBeGreaterThan(0)
        ->and($calculator->approvedTotalsForRead($repairOrder)->totalCents())->toBe($approvedBefore)
        ->and(OperationalReportTotals::soldTotalCentsByRepairOrderId([$repairOrder->id])[$repairOrder->id])->toBe($approvedBefore);

    // Re-read pending cents after all three changes (fee/tax reallocations can move pennies).
    $addedPending = $authorization->pendingApprovalCents($repairOrder, $added->fresh());
    $laborPending = $authorization->pendingApprovalCents($repairOrder, $labor->fresh());
    $partPending = $authorization->pendingApprovalCents($repairOrder, $part->fresh());

    // 5. Previously approved amounts remain unchanged while increases are pending.
    $outside = $authorization->workOutsideApprovedSalesCents($repairOrder);
    expect($outside)->toBe($addedPending + $laborPending + $partPending);

    // 6. Performed work with pending increases appears in unresolved authorization.
    $part->update(['procurement_state' => PartProcurementState::Installed]);
    $added->update(['procurement_state' => PartProcurementState::Installed]);
    $concern->update(['production_status' => ScopeProductionStatus::Completed->value]);

    $unresolved = collect(app(UnresolvedAuthorizationReport::class)->rows())->keyBy('line_id');

    expect($unresolved->has($part->id))->toBeTrue()
        ->and($unresolved[$part->id]['pending_approval_cents'])->toBe($partPending)
        ->and($unresolved[$part->id]['kind'])->toBe('installed_part')
        ->and($unresolved->has($labor->id))->toBeTrue()
        ->and($unresolved[$labor->id]['pending_approval_cents'])->toBe($laborPending)
        ->and($unresolved[$labor->id]['kind'])->toBe('completed_labor')
        ->and($unresolved->has($added->id))->toBeTrue()
        ->and($unresolved[$added->id]['pending_approval_cents'])->toBe($addedPending);

    // 9. RO staff display matches approved / pending financial amounts.
    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Increase needs authorization', false)
        ->assertSee('data-line-approved-increase="'.$labor->id.'"', false)
        ->assertSee('data-line-approved-increase="'.$part->id.'"', false)
        ->assertSee('Needs authorization', false)
        ->assertSee('data-concern-disposition="approved"', false)
        ->assertSee('Approved at', false);

    // 7. Report totals before supplemental approval stay at the approved ceiling.
    expect(OperationalReportTotals::soldTotalCentsByRepairOrderId([$repairOrder->id])[$repairOrder->id])->toBe($approvedBefore);

    // 4 + 7. Supplemental approval covers pending work; report totals rise with the new approval.
    finAuthRecordApproval($repairOrder->fresh());
    $repairOrder = $repairOrder->fresh(['concerns', 'lines.concern']);
    $afterSupplemental = $calculator->approvedTotalsForRead($repairOrder)->totalCents();

    expect($authorization->linesRequiringAuthorization($repairOrder))->toHaveCount(0)
        ->and($afterSupplemental)->toBe($approvedBefore + $outside)
        ->and(OperationalReportTotals::soldTotalCentsByRepairOrderId([$repairOrder->id])[$repairOrder->id])->toBe($afterSupplemental)
        ->and(collect(app(UnresolvedAuthorizationReport::class)->rows())->pluck('line_id')->all())
        ->not->toContain($part->id, $labor->id, $added->id);
});

test('issued invoice and posted sales stay on approved dollars while increases are still pending (#74 #8)', function () {
    [$repairOrder, $concern, $labor, $part] = finAuthApprovedRepairOrder(phoneSuffix: '4400');
    $authorization = app(WorkCompletionAuthorization::class);
    $calculator = app(EstimateTotalsCalculator::class);
    $approvedBefore = $calculator->approvedTotalsForRead($repairOrder->fresh(['lines.concern']))->totalCents();

    finAuthPatchLabor($repairOrder, $concern, $labor, quantity: '1.30');
    finAuthPatchPart($repairOrder->fresh(), $concern->fresh(), $part->fresh(), quantity: '2.00');
    $repairOrder = $repairOrder->fresh(['concerns', 'lines.concern']);

    expect($calculator->approvedTotalsForRead($repairOrder)->totalCents())->toBe($approvedBefore)
        ->and($authorization->workOutsideApprovedSalesCents($repairOrder))->toBeGreaterThan(0);

    $repairOrder->forceFill(['status' => RepairOrderStatus::ReadyPickup])->save();
    $invoice = app(GenerateInvoiceSnapshotAction::class)->execute(
        $repairOrder->fresh(['lines.concern', 'concerns', 'customer']),
    );
    $repairOrder->forceFill(['posted_at' => Carbon::parse('2026-06-08 15:00:00')])->save();

    expect((int) data_get($invoice->snapshot_json, 'totals.total_cents'))->toBe($approvedBefore)
        ->and(OperationalReportTotals::postedSalesCents([$repairOrder->id]))->toBe($approvedBefore)
        ->and(OperationalReportTotals::soldTotalCentsByRepairOrderId([$repairOrder->id])[$repairOrder->id])->toBe($approvedBefore);
});

test('approved sold figures stay under a practical budget for a mid-size report set (#74 performance)', function () {
    $ids = collect();
    $expectedTotal = 0;

    for ($i = 0; $i < 40; $i++) {
        [$repairOrder] = finAuthApprovedRepairOrder(phoneSuffix: (string) (2000 + $i));
        $ids->push($repairOrder->id);
        $expectedTotal += app(EstimateTotalsCalculator::class)
            ->approvedTotalsForRead($repairOrder->fresh(['lines.concern']))
            ->totalCents();
    }

    $started = hrtime(true);
    $figures = OperationalReportTotals::approvedSoldFiguresByRepairOrderId($ids);
    $elapsedMs = (hrtime(true) - $started) / 1_000_000;

    expect($figures)->toHaveCount(40)
        ->and((int) $figures->sum(fn (array $row): int => (int) $row['total_cents']))->toBe($expectedTotal)
        ->and($elapsedMs)->toBeLessThan(5000);
});

test('technician cannot record supplemental authorization (#72 permission boundary)', function () {
    [$repairOrder] = finAuthApprovedRepairOrder(phoneSuffix: '3333');
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);

    $this->actingAs($technician)
        ->post(route('operations.repair-orders.authorization.store', $repairOrder), [
            'source' => ApprovalSource::Phone->value,
            'approved_by' => 'Morgan Brown',
            RepairOrderConcurrency::FIELD => (int) $repairOrder->estimate_version,
            RepairOrderAuthorizationStoreController::PRESENTED_CHANGES_FIELD => app(WorkCompletionAuthorization::class)
                ->pendingChangesFingerprint($repairOrder->fresh(['concerns', 'lines.concern'])),
        ])
        ->assertForbidden();
});

/**
 * @return array{0: RepairOrder, 1: RepairOrderConcern, 2: RepairOrderLine, 3: RepairOrderLine}
 */
function finAuthApprovedRepairOrder(string $phoneSuffix = '0188'): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Morgan',
        'last_name' => 'Brown',
        'phone' => '555-'.$phoneSuffix,
        'email' => 'fin-auth-'.$phoneSuffix.'@example.test',
        'customer_type' => 'Retail',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2016,
        'make' => 'Toyota',
        'model' => 'Camry',
        'vin' => '4T1BF1FK0GU'.str_pad($phoneSuffix, 6, '0', STR_PAD_LEFT),
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Front brakes grinding',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front brakes',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    $labor = finAuthStoreLabor($repairOrder, $concern, 'Replace front pads', '1.00', '200.00');

    $part = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Front brake pads',
        'quantity' => '1.00',
        'unit_price_cents' => 8000,
        'part_cost_cents' => 4000,
        'subtotal_cents' => 8000,
        'total_cents' => 8000,
    ]);

    finAuthRecordApproval($repairOrder);

    expect($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Approved);

    return [$repairOrder->fresh(), $concern->fresh(), $labor->fresh(), $part->fresh()];
}

function finAuthStoreLabor(RepairOrder $repairOrder, RepairOrderConcern $concern, string $description, string $quantity, string $unitPrice): RepairOrderLine
{
    test()->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => $description,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
    ])->assertRedirect();

    return RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('description', $description)
        ->latest('id')
        ->firstOrFail();
}

function finAuthStorePart(RepairOrder $repairOrder, RepairOrderConcern $concern, string $description, string $quantity, string $unitPrice): RepairOrderLine
{
    test()->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part->value,
        'description' => $description,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'part_cost' => '10.00',
    ])->assertRedirect();

    return RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('description', $description)
        ->latest('id')
        ->firstOrFail();
}

function finAuthPatchLabor(
    RepairOrder $repairOrder,
    RepairOrderConcern $concern,
    RepairOrderLine $line,
    ?string $quantity = null,
): void {
    test()->patch(route('operations.repair-orders.lines.update', [$repairOrder, $line]), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => $line->description,
        'quantity' => $quantity ?? (string) $line->quantity,
        'unit_price' => number_format($line->unit_price_cents / 100, 2, '.', ''),
    ])->assertRedirect();
}

function finAuthPatchPart(
    RepairOrder $repairOrder,
    RepairOrderConcern $concern,
    RepairOrderLine $line,
    ?string $quantity = null,
): void {
    test()->patch(route('operations.repair-orders.lines.update', [$repairOrder, $line]), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part->value,
        'description' => $line->description,
        'quantity' => $quantity ?? (string) $line->quantity,
        'unit_price' => number_format($line->unit_price_cents / 100, 2, '.', ''),
        'part_cost' => $line->part_cost_cents !== null
            ? number_format($line->part_cost_cents / 100, 2, '.', '')
            : null,
    ])->assertRedirect();
}

function finAuthRecordApproval(RepairOrder $repairOrder, ?string $amount = null): void
{
    $repairOrder = $repairOrder->fresh(['concerns', 'lines.concern']);
    $opened = [
        'version' => (int) $repairOrder->estimate_version,
        'changes' => app(WorkCompletionAuthorization::class)->pendingChangesFingerprint($repairOrder),
    ];

    test()->post(route('operations.repair-orders.authorization.store', $repairOrder), array_filter([
        'source' => ApprovalSource::Phone->value,
        'approved_by' => 'Morgan Brown',
        'approved_amount' => $amount,
        RepairOrderConcurrency::FIELD => $opened['version'],
        RepairOrderAuthorizationStoreController::PRESENTED_CHANGES_FIELD => $opened['changes'],
    ], fn ($value): bool => $value !== null))
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#authorization-rail');
}
