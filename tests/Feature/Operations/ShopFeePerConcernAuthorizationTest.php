<?php

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Financial\GenerateInvoiceSnapshotAction;
use App\Ark\Operations\RepairOrders\ApprovedWorkScope;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderAuthorizationStoreController;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\UpdateConcernDispositionAction;
use App\Ark\Operations\RepairOrders\WorkCompletionAuthorization;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    ShopSettings::current()->update([
        'labor_categories' => ShopSettings::DEFAULT_LABOR_CATEGORIES,
        'default_labor_rate_cents' => 16500,
        'tax_enabled' => false,
        'taxable_shop_fees' => false,
        'shop_fee_enabled' => true,
        'shop_fee_mode' => 'per_concern',
        'shop_fee_rate' => '5.000',
        'shop_fee_flat_cents' => 1500,
        'shop_fee_cap_cents' => 4000,
        'portal_signature_required' => false,
    ]);
    $this->advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($this->advisor);
});

function authFeeRo(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Synthetic',
        'last_name' => 'FeeAuth',
        'phone' => '555-0166',
        'customer_type' => 'Retail',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2017,
        'make' => 'Ford',
        'model' => 'Escape',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Per-concern authorization',
    ]);
}

function authFeeConcern(
    RepairOrder $repairOrder,
    string $summary,
    RepairOrderConcernDisposition $disposition = RepairOrderConcernDisposition::Recommended,
    int $lineCount = 1,
    int $unitPriceCents = 16500,
): RepairOrderConcern {
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => $summary,
        'disposition' => $disposition,
        'billing_posture' => ConcernBillingPosture::CustomerPay,
    ]);

    for ($i = 0; $i < $lineCount; $i++) {
        RepairOrderLine::query()->create([
            'repair_order_id' => $repairOrder->id,
            'repair_order_concern_id' => $concern->id,
            'type' => RepairOrderLineType::Labor,
            'description' => $summary.' labor '.$i,
            'quantity' => '1.00',
            'unit_price_cents' => $unitPriceCents,
            'subtotal_cents' => $unitPriceCents,
            'tax_cents' => 0,
            'shop_fee_cents' => 0,
            'total_cents' => $unitPriceCents,
            'labor_category_key' => 'mechanical',
            'labor_entered_hours' => '1.00',
        ]);
    }

    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent($repairOrder->fresh(['lines', 'concerns', 'customer']));

    return $concern->fresh();
}

function authFeeApprove(RepairOrder $repairOrder, ?string $amount = null): void
{
    $repairOrder = $repairOrder->fresh(['concerns', 'lines.concern']);
    $payload = array_filter([
        'source' => ApprovalSource::InPerson->value,
        'approved_by' => 'Synthetic FeeAuth',
        'approved_amount' => $amount,
        RepairOrderConcurrency::FIELD => (int) $repairOrder->estimate_version,
        RepairOrderAuthorizationStoreController::PRESENTED_CHANGES_FIELD => app(WorkCompletionAuthorization::class)
            ->pendingChangesFingerprint($repairOrder),
    ], fn ($value): bool => $value !== null);

    test()->post(route('operations.repair-orders.authorization.store', $repairOrder), $payload)
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#authorization-rail');
}

test('supplemental authorization raises per-concern fee from 30 to 40 only after approval', function () {
    $calculator = app(EstimateTotalsCalculator::class);
    $ro = authFeeRo();
    $one = authFeeConcern($ro, 'Concern one');
    $two = authFeeConcern($ro, 'Concern two');

    expect((int) $ro->lines()->sum('shop_fee_cents'))->toBe(3000)
        ->and((int) $one->lines()->sum('shop_fee_cents'))->toBe(1500)
        ->and((int) $two->lines()->sum('shop_fee_cents'))->toBe(1500);

    authFeeApprove($ro);

    $ro = $ro->fresh(['lines.concern', 'concerns']);
    expect($one->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Approved)
        ->and($two->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Approved)
        ->and($calculator->approvedTotalsForRead($ro)->allocatedShopFeesCents())->toBe(3000)
        ->and(ApprovalEvent::query()->where('visit_id', $ro->id)->count())->toBe(1)
        ->and(ApprovedWorkScope::query()->count())->toBe(2);

    $firstApprovedAmount = (int) ApprovalEvent::query()->where('visit_id', $ro->id)->value('approved_amount_cents');
    expect($firstApprovedAmount)->toBe($calculator->approvedTotalsForRead($ro)->totalCents());

    // Third concern stays recommended: approved shop fee must not rise yet (extra $10 unbilled).
    $three = authFeeConcern($ro, 'Concern three');
    $ro = $ro->fresh(['lines.concern', 'concerns']);
    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent($ro);
    $ro = $ro->fresh(['lines.concern', 'concerns']);

    expect($three->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Recommended)
        ->and($calculator->approvedTotalsForRead($ro)->allocatedShopFeesCents())->toBe(3000)
        ->and((int) $ro->lines()->sum('shop_fee_cents'))->toBe(3000)
        ->and((int) $one->lines()->sum('shop_fee_cents'))->toBe(1500)
        ->and((int) $two->lines()->sum('shop_fee_cents'))->toBe(1500)
        ->and((int) $three->lines()->sum('shop_fee_cents'))->toBe(0);

    // Product path: disposition → Approved records the approved work scope for that concern.
    app(UpdateConcernDispositionAction::class)->execute(
        $ro,
        $three->fresh(),
        RepairOrderConcernDisposition::Approved,
        test()->advisor,
    );

    $ro = $ro->fresh(['lines.concern', 'concerns']);
    expect($calculator->approvedTotalsForRead($ro)->allocatedShopFeesCents())->toBe(4000)
        ->and((int) $one->lines()->sum('shop_fee_cents'))->toBe(1500)
        ->and((int) $two->lines()->sum('shop_fee_cents'))->toBe(1500)
        ->and((int) $three->lines()->sum('shop_fee_cents'))->toBe(1000)
        ->and(ApprovedWorkScope::query()->where('repair_order_concern_id', $three->id)->exists())->toBeTrue()
        ->and($calculator->approvedTotalsForRead($ro)->totalCents())->toBeGreaterThan($firstApprovedAmount);
});

test('fourth concern at the already-reached cap does not raise approved shop fees', function () {
    $calculator = app(EstimateTotalsCalculator::class);
    $ro = authFeeRo();
    authFeeConcern($ro, 'One');
    authFeeConcern($ro, 'Two');
    authFeeConcern($ro, 'Three');
    authFeeApprove($ro);

    expect($calculator->approvedTotalsForRead($ro->fresh(['lines.concern']))->allocatedShopFeesCents())->toBe(4000);

    $four = authFeeConcern($ro, 'Four');
    app(UpdateConcernDispositionAction::class)->execute(
        $ro->fresh(),
        $four->fresh(),
        RepairOrderConcernDisposition::Approved,
        test()->advisor,
    );

    expect($calculator->approvedTotalsForRead($ro->fresh(['lines.concern']))->allocatedShopFeesCents())->toBe(4000);
});

test('declining an authorized concern and multi-line concerns keep per-concern fee rules', function () {
    $calculator = app(EstimateTotalsCalculator::class);
    $ro = authFeeRo();
    $multi = authFeeConcern($ro, 'Multi', RepairOrderConcernDisposition::Recommended, 3);
    authFeeConcern($ro, 'Second');
    authFeeApprove($ro);

    expect($multi->lines()->count())->toBe(3)
        ->and($calculator->approvedTotalsForRead($ro->fresh(['lines.concern']))->allocatedShopFeesCents())->toBe(3000);

    app(UpdateConcernDispositionAction::class)->execute(
        $ro->fresh(),
        $multi->fresh(),
        RepairOrderConcernDisposition::Declined,
        test()->advisor,
    );

    expect($calculator->approvedTotalsForRead($ro->fresh(['lines.concern']))->allocatedShopFeesCents())->toBe(1500)
        ->and($calculator->declinedTotalsForRead($ro->fresh(['lines.concern']))->allocatedShopFeesCents())->toBe(0);
});

test('issued invoice cannot exceed approved amounts after per-concern supplemental authorization', function () {
    $calculator = app(EstimateTotalsCalculator::class);
    $ro = authFeeRo();
    authFeeConcern($ro, 'One');
    authFeeConcern($ro, 'Two');
    authFeeApprove($ro);

    $third = authFeeConcern($ro, 'Three');
    expect($calculator->approvedTotalsForRead($ro->fresh(['lines.concern']))->allocatedShopFeesCents())->toBe(3000);

    app(UpdateConcernDispositionAction::class)->execute(
        $ro->fresh(),
        $third->fresh(),
        RepairOrderConcernDisposition::Approved,
        test()->advisor,
    );

    $ro = $ro->fresh(['lines.concern', 'concerns']);
    $ro->update(['status' => RepairOrderStatus::ReadyPickup]);
    $approvedTotal = $calculator->totalsForApprovedWork($ro->fresh(['lines.concern']))->totalCents();

    $invoice = app(GenerateInvoiceSnapshotAction::class)->execute($ro->fresh(['lines.concern', 'concerns', 'customer', 'vehicle']));
    expect($invoice)->toBeInstanceOf(EstimateDocument::class)
        ->and($invoice->document_type)->toBe(FinancialDocumentType::Invoice)
        ->and((int) data_get($invoice->snapshot_json, 'totals.total_cents'))->toBe($approvedTotal)
        ->and($calculator->approvedTotalsForRead($ro->fresh(['lines.concern']))->allocatedShopFeesCents())->toBe(4000);
});
