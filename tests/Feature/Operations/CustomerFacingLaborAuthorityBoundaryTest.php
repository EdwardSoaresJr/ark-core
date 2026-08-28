<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\CustomerFacingDocumentBoundary;
use App\Ark\Operations\Documents\CustomerFacingEstimateStatus;
use App\Ark\Operations\Documents\DocumentPdfPresenter;
use App\Ark\Operations\Documents\EstimateSnapshotBuilder;
use App\Ark\Operations\Labor\LaborAdjustment;
use App\Ark\Operations\Labor\LaborAdjustmentReason;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update([
        'labor_categories' => ShopSettings::DEFAULT_LABOR_CATEGORIES,
        'default_labor_rate_cents' => 16500,
    ]);
});

test('customer facing document boundary strips labor authority metadata from snapshot lines', function () {
    [$repairOrder] = repairOrderForCustomerFacingLaborBoundary();

    $snapshot = app(EstimateSnapshotBuilder::class)->build($repairOrder->fresh(['concerns.lines']));
    $line = $snapshot['concerns'][0]['lines'][0];

    expect($line)
        ->toHaveKey('labor_entered_hours')
        ->toHaveKey('labor_adjustment')
        ->toHaveKey('labor_adjustment_reason')
        ->labor_adjustment_reason->toBe('Corrosion');

    $sanitized = app(CustomerFacingDocumentBoundary::class)->sanitize($snapshot);
    $sanitizedLine = $sanitized['concerns'][0]['lines'][0];

    expect($sanitizedLine)
        ->not->toHaveKey('labor_category_key')
        ->not->toHaveKey('labor_category_name')
        ->not->toHaveKey('labor_entered_hours')
        ->not->toHaveKey('labor_adjustment')
        ->not->toHaveKey('labor_adjustment_factor')
        ->not->toHaveKey('labor_adjustment_reason')
        ->not->toHaveKey('labor_billed_hours')
        ->not->toHaveKey('labor_hours_overridden')
        ->not->toHaveKey('labor_override_reason')
        ->not->toHaveKey('labor_minimum_applied')
        ->not->toHaveKey('labor_rate_cents')
        ->not->toHaveKey('labor_rate')
        ->description->toBe('Front brake service labor')
        ->quantity->toBe('2.00')
        ->unit_price->toBe('$165.00');

    expect($sanitized['settings'])->not->toHaveKey('labor_categories');
});

test('customer facing document boundary removes draft concerns', function () {
    [$repairOrder] = repairOrderForCustomerFacingLaborBoundary();

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Hidden draft scope',
        'disposition' => RepairOrderConcernDisposition::Draft,
        'position' => 2,
    ]);

    $snapshot = app(EstimateSnapshotBuilder::class)->build($repairOrder->fresh(['concerns.lines']));

    expect(collect($snapshot['concerns'])->pluck('summary'))->toContain('Hidden draft scope');

    $sanitized = app(CustomerFacingDocumentBoundary::class)->sanitize($snapshot);

    expect(collect($sanitized['concerns'])->pluck('summary'))
        ->toContain('Front brake service')
        ->not->toContain('Hidden draft scope');
});

test('customer facing status ignores draft scopes and internal awaiting approval lifecycle', function () {
    [$repairOrder] = repairOrderForCustomerFacingLaborBoundary();

    $repairOrder->update(['status' => RepairOrderStatus::WaitingApproval]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Hidden draft follow-up',
        'disposition' => RepairOrderConcernDisposition::Draft,
        'position' => 2,
    ]);

    expect(app(CustomerFacingEstimateStatus::class)->labelForRepairOrder($repairOrder->fresh(['concerns', 'approvalEvents'])))
        ->toBe('Awaiting your approval');

    $repairOrder->concerns()->first()?->update(['disposition' => RepairOrderConcernDisposition::Approved]);

    expect(app(CustomerFacingEstimateStatus::class)->labelForRepairOrder($repairOrder->fresh(['concerns', 'approvalEvents'])))
        ->toBe('Approved');

    $snapshot = app(DocumentPdfPresenter::class)->prepareForCustomer(
        app(EstimateSnapshotBuilder::class)->build($repairOrder->fresh(['concerns.lines', 'approvalEvents'])),
    );

    expect($snapshot['repair_order']['status_label'])->toBe('Approved')
        ->and($snapshot['document_footer']['approval']['status_label'])->toBe('Approved');
});

test('estimate pdf html shows final labor only and hides labor authority metadata', function () {
    [$repairOrder] = repairOrderForCustomerFacingLaborBoundary();

    $snapshot = app(DocumentPdfPresenter::class)->prepareForCustomer(
        app(EstimateSnapshotBuilder::class)->build($repairOrder->fresh(['concerns.lines'])),
    );

    $html = view('operations.documents.pdf.document', [
        'document' => (object) ['id' => 1],
        'snapshot' => $snapshot,
    ])->render();

    expect($html)
        ->toContain('Front brake service labor')
        ->toContain('2.00')
        ->toContain('$165.00')
        ->toContain('$330.00')
        ->not->toContain('Book ')
        ->not->toContain('Corrosion')
        ->not->toContain('Difficult')
        ->not->toContain('Severe')
        ->not->toContain('labor_entered_hours')
        ->not->toContain('labor_adjustment')
        ->not->toContain('labor_billed_hours')
        ->not->toContain('minimum applied')
        ->not->toContain('Mechanical')
        ->not->toContain('Override');
});

test('stored estimate snapshots retain labor authority for staff audit', function () {
    [$repairOrder] = repairOrderForCustomerFacingLaborBoundary();

    $snapshot = app(EstimateSnapshotBuilder::class)->build($repairOrder->fresh(['concerns.lines']));
    $line = $snapshot['concerns'][0]['lines'][0];

    expect($line)
        ->labor_entered_hours->toBe('1.50')
        ->labor_billed_hours->toBe('2.00')
        ->labor_adjustment->toBe('difficult')
        ->labor_adjustment_reason->toBe('Corrosion');
});

/**
 * @return array{0: RepairOrder}
 */
function repairOrderForCustomerFacingLaborBoundary(): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Rosa',
        'last_name' => 'Garcia',
        'phone' => '555-0101',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Accord',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Brake noise',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front brake service',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Front brake service labor',
        'quantity' => '2.00',
        'unit_price_cents' => 16500,
        'subtotal_cents' => 33000,
        'total_cents' => 33000,
        'labor_category_key' => 'mechanical',
        'labor_category_name' => 'Mechanical',
        'labor_entered_hours' => '1.50',
        'labor_adjustment' => LaborAdjustment::Difficult->value,
        'labor_adjustment_factor' => '1.25',
        'labor_adjustment_reason' => LaborAdjustmentReason::Corrosion->label(),
        'labor_billed_hours' => '2.00',
        'labor_hours_overridden' => false,
        'labor_minimum_applied' => false,
        'labor_rate_cents' => 16500,
    ]);

    return [$repairOrder->fresh(['customer', 'vehicle', 'concerns.lines'])];
}

test('customer facing document boundary keeps labor lines verbatim and labels them service', function () {
    [$repairOrder] = repairOrderForCustomerFacingLaborBoundary();

    $concern = $repairOrder->concerns()->firstOrFail();

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'COMBUSTION TEST COOLING SYSTEM',
        'quantity' => '0.75',
        'unit_price_cents' => 16500,
        'subtotal_cents' => 12375,
        'total_cents' => 12808,
        'labor_category_key' => 'mechanical',
        'labor_category_name' => 'Mechanical',
        'labor_entered_hours' => '0.75',
        'labor_billed_hours' => '0.75',
        'labor_rate_cents' => 16500,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'DRAIN & FILL SYSTEM COOLING SYS',
        'quantity' => '1.00',
        'unit_price_cents' => 16500,
        'subtotal_cents' => 16500,
        'total_cents' => 17078,
        'labor_category_key' => 'mechanical',
        'labor_category_name' => 'Mechanical',
        'labor_entered_hours' => '1.00',
        'labor_billed_hours' => '1.00',
        'labor_rate_cents' => 16500,
    ]);

    $repairOrder->lines()->where('description', 'Front brake service labor')->update([
        'description' => 'R & R RADIATOR',
        'quantity' => '1.50',
        'subtotal_cents' => 24750,
        'total_cents' => 25616,
        'labor_entered_hours' => '1.50',
        'labor_billed_hours' => '1.50',
    ]);

    $snapshot = app(EstimateSnapshotBuilder::class)->build($repairOrder->fresh(['concerns.lines']));

    expect(collect($snapshot['concerns'][0]['lines'])->where('type', RepairOrderLineType::Labor->value))->toHaveCount(3);

    $sanitized = app(CustomerFacingDocumentBoundary::class)->sanitize($snapshot);
    $laborLines = collect($sanitized['concerns'][0]['lines'])->where('type', RepairOrderLineType::Labor->value)->values();

    expect($laborLines)->toHaveCount(3)
        ->and($laborLines[0]['description'])->toBe('R & R RADIATOR')
        ->and($laborLines[0]['type_label'])->toBe('Labor')
        ->and($laborLines[1]['description'])->toBe('COMBUSTION TEST COOLING SYSTEM')
        ->and($laborLines[2]['description'])->toBe('DRAIN & FILL SYSTEM COOLING SYS')
        ->and($laborLines[0])->not->toHaveKey('labor_entered_hours');

    $html = view('operations.documents.pdf.document', [
        'document' => (object) ['id' => 1],
        'snapshot' => app(DocumentPdfPresenter::class)->prepareForCustomer($snapshot),
    ])->render();

    expect($html)
        ->toContain('R &amp; R RADIATOR')
        ->toContain('COMBUSTION TEST COOLING SYSTEM')
        ->toContain('DRAIN &amp; FILL SYSTEM COOLING SYS')
        ->toContain('>Labor<');
});
