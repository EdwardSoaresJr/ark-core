<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\CustomerFacingDocumentBoundary;
use App\Ark\Operations\Documents\DocumentPdfPresenter;
use App\Ark\Operations\Documents\EstimateSnapshotBuilder;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroup;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Parts\CustomerPartPresentationProfile;
use App\Ark\Operations\Parts\CustomerPartPresentationProfileResolver;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\ShopSettingsSeeder;

test('shop settings can remap billing class customer document presentation profile', function () {
    $this->seed([ArkAuthorizationSeeder::class, ShopSettingsSeeder::class]);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $rows = collect(ShopSettings::current()->customerTypeRows())
        ->map(fn (array $row): array => [
            'name' => $row['name'],
            'document_presentation_profile' => $row['name'] === 'Fleet' ? 'warranty' : ($row['document_presentation_profile'] ?? 'retail'),
            'shop_fees_enabled' => $row['shop_fees_enabled'] ? '1' : '0',
            'shop_fee_rate_override' => $row['shop_fee_rate_override'],
            'discount_type' => $row['discount_type'],
            'discount_amount' => $row['discount_amount'],
            'default_parts_matrix_key' => $row['default_parts_matrix_key'],
        ])
        ->all();

    $this->patch(route('operations.settings.shop.customer-types.update'), [
        'customer_types' => $rows,
    ])->assertRedirect(route('operations.settings.shop.edit'));

    expect(ShopSettings::current()->documentPresentationProfileFor('Fleet'))
        ->toBe(CustomerPartPresentationProfile::Warranty);
});

test('billing class defaults map to customer part presentation profiles', function () {
    $this->seed(ShopSettingsSeeder::class);

    expect(ShopSettings::current()->documentPresentationProfileFor('Retail'))
        ->toBe(CustomerPartPresentationProfile::Retail)
        ->and(ShopSettings::current()->documentPresentationProfileFor('Warranty'))
        ->toBe(CustomerPartPresentationProfile::Warranty)
        ->and(ShopSettings::current()->documentPresentationProfileFor('RepairPal'))
        ->toBe(CustomerPartPresentationProfile::RepairPal)
        ->and(ShopSettings::current()->documentPresentationProfileFor('Fleet'))
        ->toBe(CustomerPartPresentationProfile::Fleet)
        ->and(ShopSettings::current()->documentPresentationProfileFor('Internal'))
        ->toBe(CustomerPartPresentationProfile::Retail);
});

test('concern billing posture overrides customer billing class presentation profile', function () {
    $resolver = new CustomerPartPresentationProfileResolver;

    $snapshot = [
        'customer' => ['customer_type' => 'Retail'],
        'concerns' => [],
    ];

    expect($resolver->forConcern($snapshot, ['billing_posture' => 'warranty_other']))
        ->toBe(CustomerPartPresentationProfile::Warranty)
        ->and($resolver->forConcern($snapshot, ['billing_posture' => 'repairpal']))
        ->toBe(CustomerPartPresentationProfile::RepairPal)
        ->and($resolver->forConcern($snapshot, ['billing_posture' => 'default']))
        ->toBe(CustomerPartPresentationProfile::Retail);
});

test('customer facing boundary keeps part descriptions verbatim and strips inventory metadata', function () {
    [$repairOrder, $concern] = repairOrderForCustomerPartPresentation();

    $workGroup = RepairOrderWorkGroup::query()->create([
        'repair_order_concern_id' => $concern->id,
        'title' => 'Replace Water Pump',
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'repair_order_work_group_id' => $workGroup->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace water pump',
        'quantity' => '2.75',
        'unit_price_cents' => 16500,
        'subtotal_cents' => 45375,
        'total_cents' => 45375,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'repair_order_work_group_id' => $workGroup->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Gates 43527 Water Pump',
        'customer_description' => null,
        'vendor_name' => 'WorldPac',
        'part_number' => '43527',
        'part_cost_cents' => 6142,
        'quantity' => '1.00',
        'unit_price_cents' => 18900,
        'subtotal_cents' => 18900,
        'total_cents' => 18900,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'repair_order_work_group_id' => $workGroup->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Prestone Dex-Cool 50/50 Prediluted Extended Life Antifreeze/Coolant',
        'customer_description' => 'Coolant',
        'vendor_name' => 'NAPA',
        'part_number' => 'AF850',
        'quantity' => '3.00',
        'unit_price_cents' => 2499,
        'subtotal_cents' => 7497,
        'total_cents' => 7497,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    $snapshot = app(EstimateSnapshotBuilder::class)->build($repairOrder->fresh([
        'customer',
        'vehicle',
        'concerns.lines',
        'concerns.workGroups.lines',
    ]));

    expect($snapshot['concerns'][0]['work_groups'][0]['lines'][1]['description'])
        ->toBe('Gates 43527 Water Pump')
        ->and($snapshot['concerns'][0]['work_groups'][0]['lines'][1]['part_number'])->toBe('43527');

    $sanitized = app(CustomerFacingDocumentBoundary::class)->sanitize($snapshot);
    $partLine = $sanitized['concerns'][0]['work_groups'][0]['lines'][1];
    $explicitLine = $sanitized['concerns'][0]['work_groups'][0]['lines'][2];

    expect($partLine)
        ->description->toBe('Gates 43527 Water Pump')
        ->customer_part_description->toBe('Gates 43527 Water Pump')
        ->not->toHaveKey('part_number')
        ->not->toHaveKey('vendor_name')
        ->not->toHaveKey('part_cost_cents');

    expect($explicitLine)
        ->description->toBe('Prestone Dex-Cool 50/50 Prediluted Extended Life Antifreeze/Coolant')
        ->customer_part_description->toBe('Coolant');
});

test('grouped repair action pdf shows labor and part badges and hides column type labels', function () {
    [$repairOrder, $concern] = repairOrderForCustomerPartPresentation();

    $workGroup = RepairOrderWorkGroup::query()->create([
        'repair_order_concern_id' => $concern->id,
        'title' => 'Replace Water Pump',
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'repair_order_work_group_id' => $workGroup->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace Water Pump',
        'quantity' => '2.75',
        'unit_price_cents' => 16500,
        'subtotal_cents' => 45375,
        'total_cents' => 46963,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'repair_order_work_group_id' => $workGroup->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Gates 43527 Water Pump',
        'quantity' => '1.00',
        'unit_price_cents' => 30612,
        'subtotal_cents' => 30612,
        'total_cents' => 34193,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'repair_order_work_group_id' => $workGroup->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Prestone Dex-Cool 50/50 Prediluted Extended Life Antifreeze/Coolant',
        'quantity' => '3.00',
        'unit_price_cents' => 4094,
        'subtotal_cents' => 12282,
        'total_cents' => 13719,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    $snapshot = app(DocumentPdfPresenter::class)->prepareForCustomer(
        app(EstimateSnapshotBuilder::class)->build($repairOrder->fresh([
            'customer',
            'vehicle',
            'concerns.lines',
            'concerns.workGroups.lines',
        ])),
    );

    $html = view('operations.documents.pdf.document', [
        'document' => (object) ['id' => 1],
        'snapshot' => $snapshot,
    ])->render();

    expect($html)
        ->toContain('repair-action-group')
        ->toContain('Replace Water Pump')
        ->toContain('line-type-badge--labor')
        ->toContain('line-type-badge--part')
        ->toContain('>Labor<')
        ->toContain('>Part<')
        ->toContain('Gates 43527 Water Pump')
        ->toContain('Coolant')
        ->toContain('.repair-action-group {')
        ->toContain('border: none')
        ->toContain('.repair-action-header {')
        ->toContain('background: transparent')
        ->toContain('.repair-action-title {')
        ->toContain('color: #0f172a')
        ->toContain('text-transform: none')
        ->toContain('.line-type-badge--labor {')
        ->not->toContain('background: #f1f5f9')
        ->not->toContain('border: 1px solid #dbe3ec')
        ->not->toContain('border: 1px solid #a7f3d0')
        ->not->toContain('>LABOR<')
        ->not->toContain('>PART<');
});
test('estimate pdf html shows customer part descriptions and hides inventory metadata', function () {
    [$repairOrder, $concern] = repairOrderForCustomerPartPresentation();

    $workGroup = RepairOrderWorkGroup::query()->create([
        'repair_order_concern_id' => $concern->id,
        'title' => 'Replace Water Pump',
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'repair_order_work_group_id' => $workGroup->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Gates 43527 Water Pump',
        'vendor_name' => 'WorldPac',
        'part_number' => '43527',
        'quantity' => '1.00',
        'unit_price_cents' => 18900,
        'subtotal_cents' => 18900,
        'total_cents' => 18900,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    $snapshot = app(DocumentPdfPresenter::class)->prepareForCustomer(
        app(EstimateSnapshotBuilder::class)->build($repairOrder->fresh([
            'customer',
            'vehicle',
            'concerns.lines',
            'concerns.workGroups.lines',
        ])),
    );

    $html = view('operations.documents.pdf.document', [
        'document' => (object) ['id' => 1],
        'snapshot' => $snapshot,
    ])->render();

    expect($html)
        ->toContain('Replace Water Pump')
        ->toContain('Gates 43527 Water Pump')
        ->not->toContain('WorldPac');
});

test('prepare for customer keeps stored part descriptions intact while adding presentation labels', function () {
    [$repairOrder, $concern] = repairOrderForCustomerPartPresentation();

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Gates 43527 Water Pump',
        'quantity' => '1.00',
        'unit_price_cents' => 18900,
        'subtotal_cents' => 18900,
        'total_cents' => 18900,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    $built = app(EstimateSnapshotBuilder::class)->build($repairOrder->fresh(['customer', 'vehicle', 'concerns.lines']));
    $presented = app(DocumentPdfPresenter::class)->prepareForCustomer($built);

    expect($built['concerns'][0]['lines'][0]['description'])->toBe('Gates 43527 Water Pump')
        ->and($presented['concerns'][0]['lines'][0]['description'])->toBe('Gates 43527 Water Pump')
        ->and($presented['concerns'][0]['lines'][0]['customer_part_description'])->toBe('Gates 43527 Water Pump');
});

test('warranty presentation profile shows audit part number and vendor without mutating stored description', function () {
    [$repairOrder, $concern] = repairOrderForCustomerPartPresentation(customerType: 'Warranty');

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Gates 43527 Water Pump',
        'vendor_name' => 'WorldPac',
        'part_number' => '43527',
        'quantity' => '1.00',
        'unit_price_cents' => 18900,
        'subtotal_cents' => 18900,
        'total_cents' => 18900,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    $presented = app(DocumentPdfPresenter::class)->prepareForCustomer(
        app(EstimateSnapshotBuilder::class)->build($repairOrder->fresh(['customer', 'vehicle', 'concerns.lines'])),
    );

    $line = $presented['concerns'][0]['lines'][0];

    expect($presented['concerns'][0]['customer_part_presentation_profile'])->toBe('warranty')
        ->and($line['description'])->toBe('Gates 43527 Water Pump')
        ->and($line['customer_part_description'])->toBe('Gates 43527 Water Pump')
        ->and($line['customer_part_number'])->toBe('Gates 43527')
        ->and($line['customer_part_vendor'])->toBe('WorldPac');

    $html = view('operations.documents.pdf.document', [
        'document' => (object) ['id' => 1],
        'snapshot' => $presented,
    ])->render();

    expect($html)
        ->toContain('Gates 43527 Water Pump')
        ->toContain('Gates 43527')
        ->toContain('WorldPac');
});

test('repairpal presentation profile shows part number but not vendor', function () {
    [$repairOrder, $concern] = repairOrderForCustomerPartPresentation(customerType: 'RepairPal');

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Gates 43527 Water Pump',
        'vendor_name' => 'WorldPac',
        'part_number' => '43527',
        'quantity' => '1.00',
        'unit_price_cents' => 18900,
        'subtotal_cents' => 18900,
        'total_cents' => 18900,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    $presented = app(DocumentPdfPresenter::class)->prepareForCustomer(
        app(EstimateSnapshotBuilder::class)->build($repairOrder->fresh(['customer', 'vehicle', 'concerns.lines'])),
    );

    $line = $presented['concerns'][0]['lines'][0];

    expect($presented['concerns'][0]['customer_part_presentation_profile'])->toBe('repairpal')
        ->and($line['customer_part_description'])->toBe('Gates 43527 Water Pump')
        ->and($line['customer_part_number'])->toBe('Gates 43527')
        ->not->toHaveKey('customer_part_vendor');
});

test('concern billing posture can override retail customer to warranty presentation', function () {
    [$repairOrder, $concern] = repairOrderForCustomerPartPresentation(customerType: 'Retail');
    $concern->update(['billing_posture' => ConcernBillingPosture::WarrantyOther]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Gates 43527 Water Pump',
        'vendor_name' => 'WorldPac',
        'part_number' => '43527',
        'quantity' => '1.00',
        'unit_price_cents' => 18900,
        'subtotal_cents' => 18900,
        'total_cents' => 18900,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    $presented = app(DocumentPdfPresenter::class)->prepareForCustomer(
        app(EstimateSnapshotBuilder::class)->build($repairOrder->fresh(['customer', 'vehicle', 'concerns.lines'])),
    );

    expect($presented['concerns'][0]['customer_part_presentation_profile'])->toBe('warranty')
        ->and($presented['concerns'][0]['lines'][0]['customer_part_vendor'])->toBe('WorldPac');
});

test('staff estimate document view keeps internal inventory descriptions', function () {
    [$repairOrder, $concern] = repairOrderForCustomerPartPresentation();

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Gates 43527 Water Pump',
        'vendor_name' => 'WorldPac',
        'part_number' => '43527',
        'quantity' => '1.00',
        'unit_price_cents' => 18900,
        'subtotal_cents' => 18900,
        'total_cents' => 18900,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    $snapshot = app(DocumentPdfPresenter::class)->prepare(
        app(EstimateSnapshotBuilder::class)->build($repairOrder->fresh(['customer', 'vehicle', 'concerns.lines'])),
    );

    expect($snapshot['concerns'][0]['lines'][0])
        ->description->toBe('Gates 43527 Water Pump')
        ->part_number->toBe('43527')
        ->vendor_name->toBe('WorldPac')
        ->not->toHaveKey('customer_part_description');
});

function repairOrderForCustomerPartPresentation(?string $customerType = null): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Rosa',
        'last_name' => 'Garcia',
        'phone' => '555-0100',
        'email' => 'rosa@example.test',
        'customer_type' => $customerType ?? 'Retail',
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
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Vehicle overheating.',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Overheating',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    return [$repairOrder->fresh(), $concern->fresh()];
}
