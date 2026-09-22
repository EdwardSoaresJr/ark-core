<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\PartLineClassification;
use App\Ark\Operations\RepairOrders\PartLineSource;
use App\Ark\Operations\RepairOrders\PartLineWarrantyImpact;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RecommendationIntent;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderEstimateVersion;
use App\Ark\Operations\RepairOrders\RepairOrderEstimate;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\ScopeEntryKind;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('the shop can add concerns and estimate lines with authoritative totals', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $customer = Customer::query()->create([
        'first_name' => 'Rosa',
        'last_name' => 'Garcia',
        'phone' => '555-0100',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'ARK123',
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Accord',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Draft,
        'concern_summary' => 'Customer states engine rattles on cold start.',
    ]);

    $this->post(route('operations.repair-orders.concerns.store', $repairOrder), [
        'scope_entry_kind' => 'customer_concern',
        'summary' => 'Cold start rattle',
        'notes' => 'Most noticeable after sitting overnight.',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#concern-'.RepairOrderConcern::query()->latest('id')->value('id'));

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concernId = RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->value('id'),
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Diagnose cold start rattle',
        'quantity' => '1.50',
        // LaborAuthority resolves rate from labor policy — posted unit_price without override is ignored.
        'unit_price' => '120.00',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concernId,
        'type' => RepairOrderLineType::Part->value,
        'description' => 'Timing chain tensioner',
        'quantity' => '1.00',
        'unit_price' => '85.50',
        'pricing_mode' => 'manual',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    expect(RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(1)
        ->and(RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(2)
        ->and(RepairOrderLine::query()->where('description', 'Diagnose cold start rattle')->value('repair_order_concern_id'))->toBe($concernId)
        ->and(RepairOrderLine::query()->where('description', 'Timing chain tensioner')->value('repair_order_concern_id'))->toBe($concernId);

    $concern = RepairOrderConcern::query()->findOrFail($concernId);
    $concern->update(['disposition' => RepairOrderConcernDisposition::Recommended]);

    $repairOrder->refresh()->load('lines');
    $totals = app(RepairOrderEstimate::class)->totalsFor($repairOrder);
    $laborLine = RepairOrderLine::query()->where('description', 'Diagnose cold start rattle')->firstOrFail();
    $expectedLaborCents = (int) round((float) $laborLine->quantity * (int) $laborLine->unit_price_cents);

    expect($repairOrder->status->is(RepairOrderStatus::Estimate))->toBeTrue()
        ->and($laborLine->unit_price_cents)->toBe(16500)
        ->and($totals->laborCents())->toBe($expectedLaborCents)
        ->and($totals->laborCents())->toBe(24750)
        ->and($totals->partsCents())->toBe(8550)
        ->and($totals->totalCents())->toBe(33300);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Cold start rattle')
        ->assertSee('Review Estimate Notes', false)
        ->assertDontSee('data-ro-mode-control', false)
        ->assertDontSee('>Editing<', false)
        ->assertDontSee('Edit Estimate')
        ->assertDontSee('General Lines')
        ->assertSee('submitWorksheetForm', false)
        ->assertSee('id="estimate-total-panel"', false)
        ->assertSee('$247.50')
        ->assertSee('$85.50')
        ->assertSee('$333.00');
});

test('adding a line collapses the compose panel after save for each line type', function (
    RepairOrderLineType $lineType,
    array $linePayload,
    string $expectedDescription,
) {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
    ]);

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Maintenance',
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    $response = $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => $lineType->value,
        ...$linePayload,
    ]);

    $response->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    expect(session()->getOldInput('type'))->toBeNull()
        ->and(session()->getOldInput('description'))->toBeNull();

    $line = RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('description', $expectedDescription)
        ->first();

    expect($line)->not->toBeNull()
        ->and($line->type)->toBe($lineType)
        ->and($line->repair_order_concern_id)->toBe($concern->id);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('id="workspace-modal-host"', false)
        ->assertSee('+ Add Work', false)
        ->assertSee($expectedDescription);
})->with([
    'labor' => [
        RepairOrderLineType::Labor,
        [
            'description' => 'Oil change labor',
            'quantity' => '1.00',
            'unit_price' => '95.00',
        ],
        'Oil change labor',
    ],
    'part' => [
        RepairOrderLineType::Part,
        [
            'description' => 'Oil filter',
            'quantity' => '1.00',
            'unit_price' => '12.00',
        ],
        'Oil filter',
    ],
    'fee' => [
        RepairOrderLineType::Fee,
        [
            'description' => 'Shop supplies fee',
            'quantity' => '1.00',
            'unit_price' => '5.00',
        ],
        'Shop supplies fee',
    ],
    'note' => [
        RepairOrderLineType::Note,
        [
            'description' => 'Call customer before exceeding authorization.',
            'unit_price' => '0',
            'quantity' => '1',
        ],
        'Call customer before exceeding authorization.',
    ],
    'sublet' => [
        RepairOrderLineType::Sublet,
        [
            'description' => 'Alignment sublet',
            'quantity' => '1.00',
            'unit_price' => '89.00',
        ],
        'Alignment sublet',
    ],
]);

test('sequential line stores succeed when estimate version tokens are refreshed', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
    ]);

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);
    $concurrency = app(RepairOrderConcurrency::class);

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        RepairOrderConcurrency::FIELD => $concurrency->openedVersion($repairOrder->fresh()),
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Oil change labor',
        'quantity' => '1.00',
        'unit_price' => '95.00',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    $repairOrder->refresh();

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        RepairOrderConcurrency::FIELD => $concurrency->openedVersion($repairOrder),
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part->value,
        'description' => 'Oil filter',
        'quantity' => '1.00',
        'unit_price' => '12.00',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    expect(RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(2)
        ->and($repairOrder->fresh()->estimate_version)->toBe(3);
});

test('stale estimate version token is rejected when another advisor changed the worksheet', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $otherAdvisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);
    $openedVersion = app(RepairOrderConcurrency::class)->openedVersion($repairOrder->fresh());

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        RepairOrderConcurrency::FIELD => $openedVersion,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'First labor line',
        'quantity' => '1.00',
        'unit_price' => '95.00',
    ])->assertRedirect();

    app(RepairOrderEstimateVersion::class)->bump($repairOrder->fresh(), $otherAdvisor);

    $this->postJson(route('operations.repair-orders.lines.store', $repairOrder), [
        RepairOrderConcurrency::FIELD => $openedVersion,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part->value,
        'description' => 'Stale token part line',
        'quantity' => '1.00',
        'unit_price' => '12.00',
    ])->assertStatus(409)
        ->assertJsonPath('conflict', true);

    expect(RepairOrderLine::query()->where('description', 'Stale token part line')->exists())->toBeFalse();
});

test('same advisor sequential edits succeed even when the version token lags', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);
    $openedVersion = app(RepairOrderConcurrency::class)->openedVersion($repairOrder->fresh());

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        RepairOrderConcurrency::FIELD => $openedVersion,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'First labor line',
        'quantity' => '1.00',
        'unit_price' => '95.00',
    ])->assertRedirect();

    $this->postJson(route('operations.repair-orders.lines.store', $repairOrder), [
        RepairOrderConcurrency::FIELD => $openedVersion,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part->value,
        'description' => 'Second line with stale token',
        'quantity' => '1.00',
        'unit_price' => '12.00',
    ])->assertRedirect();

    expect(RepairOrderLine::query()->where('description', 'Second line with stale token')->exists())->toBeTrue();
});

test('part lines ignore zero sell placeholder when matrix pricing applies', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
    ]);

    $repairOrder = repairOrderForEstimateWorkspace();

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => concernForEstimateWorkspace($repairOrder)->id,
        'type' => RepairOrderLineType::Part->value,
        'description' => 'Cabin filter',
        'part_cost' => '20.00',
        'pricing_mode' => 'matrix',
        'unit_price' => '0.00',
        'quantity' => '1.00',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    $line = RepairOrderLine::query()->where('description', 'Cabin filter')->firstOrFail();

    expect($line->matrix_suggested_price_cents)->toBe(4400)
        ->and($line->unit_price_cents)->toBe(4400)
        ->and($line->matrix_applied)->toBeTrue();
});

test('part lines ignore stale default labor sell when matrix pricing applies', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $settings = ShopSettings::current();
    $settings->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
        'default_labor_rate_cents' => 15000,
    ]);

    $repairOrder = repairOrderForEstimateWorkspace();

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => concernForEstimateWorkspace($repairOrder)->id,
        'type' => RepairOrderLineType::Part->value,
        'description' => 'Air filter',
        'part_cost' => '20.00',
        'pricing_mode' => 'matrix',
        'unit_price' => number_format($settings->default_labor_rate_cents / 100, 2, '.', ''),
        'quantity' => '1.00',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    $line = RepairOrderLine::query()->where('description', 'Air filter')->firstOrFail();

    expect($line->matrix_suggested_price_cents)->toBe(4400)
        ->and($line->unit_price_cents)->toBe(4400)
        ->and($line->matrix_applied)->toBeTrue();
});

test('part lines persist part source classification and warranty metadata', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
    ]);

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part->value,
        'description' => 'Customer supplied lift kit hardware',
        'part_cost' => '0.00',
        'pricing_mode' => 'manual',
        'unit_price' => '0.00',
        'quantity' => '1.00',
        'part_source' => 'customer_supplied',
        'customer_part_posture' => 'waiting',
        'part_classification' => 'performance_custom',
        'part_warranty_impact' => 'customer_supplied',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    $line = RepairOrderLine::query()->where('description', 'Customer supplied lift kit hardware')->firstOrFail();

    expect($line->part_source)->toBe(PartLineSource::CustomerSupplied)
        ->and($line->procurement_state)->toBe(PartProcurementState::AwaitingCustomer)
        ->and($line->part_classification)->toBe(PartLineClassification::PerformanceCustom)
        ->and($line->part_warranty_impact)->toBe(PartLineWarrantyImpact::CustomerSupplied)
        ->and($line->partMetadataLabels())->toContain('Customer supplied')
        ->and($line->partMetadataLabels())->toContain('Performance / custom');
});

test('core part lines always save the old part on store and update', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
    ]);

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part->value,
        'description' => 'Alternator with core',
        'part_cost' => '120.00',
        'pricing_mode' => 'matrix',
        'quantity' => '1.00',
        'has_core' => '1',
        'save_old_part' => '0',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    $line = RepairOrderLine::query()->where('description', 'Alternator with core')->firstOrFail();

    expect($line->has_core)->toBeTrue()
        ->and($line->save_old_part)->toBeTrue()
        ->and($line->partPullFlagLabels())->toBe(['Core', 'Save']);

    $this->patch(route('operations.repair-orders.lines.update', [$repairOrder, $line]), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part->value,
        'description' => 'Alternator with core',
        'part_cost' => '120.00',
        'pricing_mode' => 'matrix',
        'quantity' => '1.00',
        'has_core' => '1',
        'save_old_part' => '0',
        RepairOrderConcurrency::FIELD => $repairOrder->fresh()->estimate_version,
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    expect($line->fresh())
        ->has_core->toBeTrue()
        ->save_old_part->toBeTrue();

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part->value,
        'description' => 'Air filter only',
        'part_cost' => '12.00',
        'pricing_mode' => 'matrix',
        'quantity' => '1.00',
        'has_core' => '0',
        'save_old_part' => '1',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    $saveOnlyLine = RepairOrderLine::query()->where('description', 'Air filter only')->firstOrFail();

    expect($saveOnlyLine->has_core)->toBeFalse()
        ->and($saveOnlyLine->save_old_part)->toBeTrue()
        ->and($saveOnlyLine->partPullFlagLabels())->toBe(['Save']);
});

test('part lines can accept matrix suggested sell pricing from entered cost', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
    ]);

    $repairOrder = repairOrderForEstimateWorkspace();

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => concernForEstimateWorkspace($repairOrder)->id,
        'type' => RepairOrderLineType::Part->value,
        'description' => 'Cabin air filter',
        'part_cost' => '20.00',
        'pricing_mode' => 'matrix',
        'quantity' => '1.00',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    $line = RepairOrderLine::query()->where('description', 'Cabin air filter')->firstOrFail();
    $repairOrder->load('lines');
    $totals = app(RepairOrderEstimate::class)->totalsFor($repairOrder);

    expect($line->part_cost_cents)->toBe(2000)
        ->and($line->matrix_suggested_price_cents)->toBe(4400)
        ->and($line->pricing_matrix_key)->toBe('aft-parts')
        ->and($line->pricing_matrix_name)->toBe('AFT Parts')
        ->and($line->unit_price_cents)->toBe(4400)
        ->and($line->matrix_applied)->toBeTrue()
        ->and($line->is_overridden)->toBeFalse()
        ->and($totals->partsCents())->toBe(4400)
        ->and($totals->totalCents())->toBe(4400);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Cabin air filter')
        ->assertSee('$44.00');
});

test('part pricing preview updates sell when matrix changes for the same cost', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
    ]);

    $repairOrder = repairOrderForEstimateWorkspace();
    $previewUrl = route('operations.repair-orders.lines.pricing-preview', $repairOrder);

    $aft = $this->postJson($previewUrl, [
        'type' => RepairOrderLineType::Part->value,
        'part_cost' => '20.00',
        'pricing_mode' => 'matrix',
        'pricing_matrix_key' => 'aft-parts',
        'pricing_matrix_explicit' => '1',
    ])->assertOk()->json();

    $oem = $this->postJson($previewUrl, [
        'type' => RepairOrderLineType::Part->value,
        'part_cost' => '20.00',
        'pricing_mode' => 'matrix',
        'pricing_matrix_key' => 'oem-parts',
        'pricing_matrix_explicit' => '1',
    ])->assertOk()->json();

    expect($aft['sell_from_matrix'])->toBe('44.00')
        ->and($oem['sell_from_matrix'])->toBe('38.00')
        ->and($aft['sell_from_matrix'])->not->toBe($oem['sell_from_matrix']);
});

test('part pricing preview is server fed and matches persisted matrix authority', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
    ]);

    $repairOrder = repairOrderForEstimateWorkspace();

    $preview = $this->postJson(route('operations.repair-orders.lines.pricing-preview', $repairOrder), [
        'type' => RepairOrderLineType::Part->value,
        'part_cost' => '20.00',
        'pricing_mode' => 'matrix',
        'pricing_matrix_key' => 'aft-parts',
        'pricing_matrix_explicit' => '1',
    ])->assertOk()
        ->json();

    expect($preview)->toMatchArray([
        'part_cost_cents' => 2000,
        'unit_price_cents' => 4400,
        'matrix_suggested_price_cents' => 4400,
        'pricing_mode' => 'matrix',
        'pricing_matrix_key' => 'aft-parts',
        'pricing_matrix_name' => 'AFT Parts',
        'matrix_applied' => true,
        'is_overridden' => false,
        'suggested_sell' => '$44.00',
        'sell_from_matrix' => '44.00',
        'current_sell' => '$44.00',
        'margin_percentage' => '55',
        'matrix_margin_percentage' => '55',
        'markup_percentage' => '120',
        'posture' => 'matrix-derived',
        'guidance' => 'AFT Parts suggested $44.00 accepted.',
    ]);

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => concernForEstimateWorkspace($repairOrder)->id,
        'type' => RepairOrderLineType::Part->value,
        'description' => 'Cabin air filter',
        'part_cost' => '20.00',
        'pricing_mode' => 'matrix',
        'pricing_matrix_key' => 'aft-parts',
        'pricing_matrix_explicit' => '1',
        'quantity' => '1.00',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    $line = RepairOrderLine::query()->where('description', 'Cabin air filter')->firstOrFail();

    expect($line->unit_price_cents)->toBe($preview['unit_price_cents'])
        ->and($line->matrix_suggested_price_cents)->toBe($preview['matrix_suggested_price_cents'])
        ->and($line->pricing_matrix_key)->toBe($preview['pricing_matrix_key'])
        ->and($line->grossMarginPercentage())->toBe($preview['margin_percentage'])
        ->and($line->matrixMarkupPercentage())->toBe($preview['markup_percentage']);
});

test('part lines default to matrix pricing when pricing mode is omitted', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
    ]);

    $repairOrder = repairOrderForEstimateWorkspace();

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => concernForEstimateWorkspace($repairOrder)->id,
        'type' => RepairOrderLineType::Part->value,
        'description' => 'Defaulted filter',
        'part_cost' => '20.00',
        'quantity' => '1.00',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    $line = RepairOrderLine::query()->where('description', 'Defaulted filter')->firstOrFail();

    expect($line->pricing_mode)->toBe('matrix')
        ->and($line->pricing_matrix_key)->toBe('aft-parts')
        ->and($line->unit_price_cents)->toBe(4400)
        ->and($line->matrix_applied)->toBeTrue();
});

test('labor lines use the shop default labor rate when no manual sell price is entered', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    ShopSettings::current()->update([
        'default_labor_rate_cents' => 18750,
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
    ]);
    // LaborAuthority reads LaborPolicy rows, not shop_settings.default_labor_rate_cents alone.
    \App\Ark\Operations\EstimatePricing\LaborPolicy::query()
        ->where('hourly_rate_cents', '>', 0)
        ->update(['hourly_rate_cents' => 18750]);

    $repairOrder = repairOrderForEstimateWorkspace();

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => concernForEstimateWorkspace($repairOrder)->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Default-rate diagnostic',
        'quantity' => '1.00',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    $line = RepairOrderLine::query()->where('description', 'Default-rate diagnostic')->firstOrFail();

    expect($line->unit_price_cents)->toBe(18750)
        ->and($line->total_cents)->toBe(18750);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Default-rate diagnostic')
        ->assertSee('$187.50');
});

test('manual labor sell price overrides the shop default labor rate', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    ShopSettings::current()->update([
        'default_labor_rate_cents' => 18750,
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
    ]);
    \App\Ark\Operations\EstimatePricing\LaborPolicy::query()
        ->where('hourly_rate_cents', '>', 0)
        ->update(['hourly_rate_cents' => 18750]);

    $repairOrder = repairOrderForEstimateWorkspace();

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => concernForEstimateWorkspace($repairOrder)->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Manual-rate diagnostic',
        'quantity' => '1.00',
        'unit_price' => '95.00',
        'labor_rate_overridden' => '1',
        'labor_rate_override_reason' => \App\Ark\Operations\EstimatePricing\LaborRateOverrideReason::CompetitiveMatch->value,
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    $line = RepairOrderLine::query()->where('description', 'Manual-rate diagnostic')->firstOrFail();

    expect($line->unit_price_cents)->toBe(9500)
        ->and($line->total_cents)->toBe(9500)
        ->and($line->labor_rate_override_reason)->toBe('competitive_match');
});

test('scope header shows recommendation intent and can update it inline', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Test scope intent',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Maintenance')
        ->assertSee("task: 'concern-intent'", false)
        ->assertSee('data-workspace-modal-form="concern-intent"', false)
        ->assertSee('id="workspace-intent-only-'.$concern->id.'"', false);

    $this->patch(route('operations.repair-orders.concerns.recommendation-intent', [$repairOrder, $concern]), [
        'recommendation_intent' => 'immediate_attention',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#concern-'.$concern->id);

    expect($concern->fresh()->recommendationIntent())->toBe(RecommendationIntent::ImmediateAttention);
});

test('new scope intake infers entry kind and defaults recommendation status server-side', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();

    $this->post(route('operations.repair-orders.concerns.store', $repairOrder), [
        'summary' => 'Front brake service',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#concern-'.RepairOrderConcern::query()->latest('id')->value('id'));

    $concern = RepairOrderConcern::query()->where('summary', 'Front brake service')->firstOrFail();

    expect($concern->entryKind())->toBe(ScopeEntryKind::CustomerRequested)
        ->and($concern->recommendationIntent())->toBe(RecommendationIntent::Maintenance);
});

test('scope billing can set default parts matrix for new part lines', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
        'customer_types' => [
            ['name' => 'Retail', 'fee_override' => null, 'discount_type' => 'none', 'discount_amount' => null, 'default_parts_matrix_key' => null],
            ['name' => 'Fleet', 'fee_override' => null, 'discount_type' => 'none', 'discount_amount' => null, 'default_parts_matrix_key' => 'oem-parts'],
        ],
    ]);

    $repairOrder = repairOrderForEstimateWorkspace('Fleet');
    $concern = concernForEstimateWorkspace($repairOrder);

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part->value,
        'description' => 'Fleet thermostat',
        'part_cost' => '20.00',
        'quantity' => '1.00',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    $line = RepairOrderLine::query()->where('description', 'Fleet thermostat')->firstOrFail();

    expect($line->pricing_mode)->toBe('matrix')
        ->and($line->pricing_matrix_key)->toBe('oem-parts')
        ->and($line->pricing_matrix_name)->toBe('OEM Parts')
        ->and($line->matrix_suggested_price_cents)->toBe(3800)
        ->and($line->unit_price_cents)->toBe(3800);
});

test('scope billing matrix wins over auto-filled form matrix when advisor has not chosen one', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
        'customer_types' => ShopSettings::DEFAULT_CUSTOMER_TYPES,
    ]);

    $repairOrder = repairOrderForEstimateWorkspace('Warranty');

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('defaultPartsMatrixKey', false)
        ->assertSee('warranty-no-markup');

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => concernForEstimateWorkspace($repairOrder)->id,
        'type' => RepairOrderLineType::Part->value,
        'description' => 'RepairPal supplied sensor',
        'part_cost' => '20.00',
        'pricing_mode' => 'matrix',
        'pricing_matrix_key' => 'aft-parts',
        'pricing_matrix_explicit' => '0',
        'quantity' => '1.00',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    $line = RepairOrderLine::query()->where('description', 'RepairPal supplied sensor')->firstOrFail();

    expect($line->pricing_mode)->toBe('matrix')
        ->and($line->pricing_matrix_key)->toBe('warranty-no-markup')
        ->and($line->pricing_matrix_name)->toBe('Warranty (No Markup)')
        ->and($line->matrix_suggested_price_cents)->toBe(2000)
        ->and($line->unit_price_cents)->toBe(2000)
        ->and($line->matrix_applied)->toBeTrue();
});

test('stale explicit parts matrix keys fail without creating estimate lines', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
    ]);

    $repairOrder = repairOrderForEstimateWorkspace();

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => concernForEstimateWorkspace($repairOrder)->id,
        'type' => RepairOrderLineType::Part->value,
        'description' => 'Unknown matrix part',
        'part_cost' => '20.00',
        'pricing_mode' => 'matrix',
        'pricing_matrix_key' => 'stale-matrix',
        'pricing_matrix_explicit' => '1',
        'quantity' => '1.00',
    ])->assertStatus(422);

    expect(RepairOrderLine::query()->where('description', 'Unknown matrix part')->exists())->toBeFalse();

    $this->postJson(route('operations.repair-orders.lines.pricing-preview', $repairOrder), [
        'type' => RepairOrderLineType::Part->value,
        'part_cost' => '20.00',
        'pricing_mode' => 'matrix',
        'pricing_matrix_key' => 'stale-matrix',
        'pricing_matrix_explicit' => '1',
    ])->assertStatus(422);
});

test('part matrix pricing can be overridden without changing financial authority', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
    ]);

    $repairOrder = repairOrderForEstimateWorkspace();

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => concernForEstimateWorkspace($repairOrder)->id,
        'type' => RepairOrderLineType::Part->value,
        'description' => 'Thermostat',
        'part_cost' => '20.00',
        'pricing_mode' => 'matrix',
        'pricing_matrix_key' => 'oem-parts',
        'pricing_matrix_explicit' => '1',
        'unit_price' => '39.00',
        'quantity' => '1.00',
        'vendor_name' => 'Local Parts Counter',
        'part_number' => 'THM-123',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    $line = RepairOrderLine::query()->where('description', 'Thermostat')->firstOrFail();

    expect($line->part_cost_cents)->toBe(2000)
        ->and($line->matrix_suggested_price_cents)->toBe(3800)
        ->and($line->pricing_matrix_key)->toBe('oem-parts')
        ->and($line->pricing_matrix_name)->toBe('OEM Parts')
        ->and($line->unit_price_cents)->toBe(3900)
        ->and($line->matrix_applied)->toBeFalse()
        ->and($line->is_overridden)->toBeTrue()
        ->and($line->vendor_name)->toBe('Local Parts Counter')
        ->and($line->part_number)->toBe('THM-123');

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Thermostat')
        ->assertSee('Local Parts Counter')
        ->assertSee('THM-123')
        ->assertSee('$39.00');

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Local Parts Counter')
        ->assertSee('Part # THM-123');
});

test('part lines can bypass matrix pricing with manual sell price', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
    ]);

    $repairOrder = repairOrderForEstimateWorkspace();

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => concernForEstimateWorkspace($repairOrder)->id,
        'type' => RepairOrderLineType::Part->value,
        'description' => 'Used trim clip set',
        'part_cost' => '20.00',
        'pricing_mode' => 'manual',
        'unit_price' => '25.00',
        'quantity' => '2.00',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    $line = RepairOrderLine::query()->where('description', 'Used trim clip set')->firstOrFail();
    $repairOrder->load('lines');
    $totals = app(RepairOrderEstimate::class)->totalsFor($repairOrder);

    expect($line->part_cost_cents)->toBe(2000)
        ->and($line->matrix_suggested_price_cents)->toBe(4400)
        ->and($line->unit_price_cents)->toBe(2500)
        ->and($line->pricing_mode)->toBe('manual')
        ->and($line->matrix_applied)->toBeFalse()
        ->and($line->is_overridden)->toBeFalse()
        ->and($line->total_cents)->toBe(5000)
        ->and($totals->partsCents())->toBe(5000)
        ->and($totals->totalCents())->toBe(5000);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Used trim clip set')
        ->assertSee('$25.00');
});

test('concerns carry compact diagnostic narrative into the estimate workspace', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();

    $this->post(route('operations.repair-orders.concerns.store', $repairOrder), [
        'scope_entry_kind' => 'customer_concern',
        'summary' => 'Engine misfire under load',
        'notes' => 'Advisor verified after warmup.',
        'customer_states' => 'Truck shakes uphill under acceleration.',
        'verified_findings' => 'Cylinder 3 misfire confirmed during loaded road test.',
        'dtcs_summary' => "P0303 current\nP0171 pending",
        'recommendation' => 'Replace failed ignition coil and retest system.',
        'recommendation_intent' => 'immediate_attention',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#concern-'.RepairOrderConcern::query()->latest('id')->value('id'));

    $concern = RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->firstOrFail();

    expect($concern->customer_states)->toBe('Truck shakes uphill under acceleration.')
        ->and($concern->verified_findings)->toBe('Cylinder 3 misfire confirmed during loaded road test.')
        ->and($concern->dtcs_summary)->toBe("P0303 current\nP0171 pending")
        ->and($concern->recommendation)->toBe('Replace failed ignition coil and retest system.');

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Customer states')
        ->assertSee('Truck shakes uphill under acceleration.')
        ->assertSee('Verified findings')
        ->assertSee('P0303 current')
        ->assertSee('Replace failed ignition coil and retest system.');
});

test('the shop can refine concern diagnostic narrative without touching estimate lines', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake vibration',
        'customer_states' => 'Steering wheel shakes when braking downhill.',
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Road test and brake inspection',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'total_cents' => 15000,
    ]);

    $this->patch(route('operations.repair-orders.concerns.update', [$repairOrder, $concern]), [
        'summary' => 'Brake vibration under deceleration',
        'notes' => 'Do not sell rear pads yet.',
        'customer_states' => 'Steering wheel shakes when braking downhill.',
        'verified_findings' => 'Front rotor pulsation verified on road test.',
        'dtcs_summary' => null,
        'recommendation' => 'Replace front pads and rotors, then retest.',
        'recommendation_intent' => 'immediate_attention',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    $concern->refresh();

    expect($concern->summary)->toBe('Brake vibration under deceleration')
        ->and($concern->verified_findings)->toBe('Front rotor pulsation verified on road test.')
        ->and($concern->recommendation)->toBe('Replace front pads and rotors, then retest.')
        ->and($concern->recommendation_intent)->toBe(RecommendationIntent::ImmediateAttention)
        ->and(RepairOrderLine::query()->where('repair_order_concern_id', $concern->id)->count())->toBe(1);
});

test('the shop can reorder concerns with simple up and down actions', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();

    // Same intent group so worksheet sort is by position (intent groups sort first).
    $first = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake vibration',
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    $second = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Oil leak',
        'recommendation_intent' => 'maintenance',
        'position' => 2,
    ]);

    $this->patch(route('operations.repair-orders.concerns.move', [$repairOrder, $second]), [
        'direction' => 'up',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    expect($second->fresh()->position)->toBe(1)
        ->and($first->fresh()->position)->toBe(2);

    $html = $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk()->getContent();
    $oil = strpos($html, 'id="concern-'.$second->id.'"');
    $brake = strpos($html, 'id="concern-'.$first->id.'"');

    expect($oil)->toBeInt()
        ->and($brake)->toBeInt()
        ->and($oil < $brake)->toBeTrue();
});

test('the shop can delete an empty concern from an open repair order', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Duplicate concern',
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    $this->delete(route('operations.repair-orders.concerns.destroy', [$repairOrder, $concern]))
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    expect(RepairOrderConcern::query()->whereKey($concern->id)->exists())->toBeFalse();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertDontSee('Duplicate concern');
});

test('concerns with lines must be emptied before deletion', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Diagnostic',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'total_cents' => 15000,
    ]);

    $this->delete(route('operations.repair-orders.concerns.destroy', [$repairOrder, $concern]))
        ->assertStatus(422);

    expect($concern->fresh())->not->toBeNull();
});

test('concerns carry a lightweight customer disposition in the estimate workspace', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake vibration',
        'recommendation_intent' => 'immediate_attention',
        'position' => 1,
    ]);

    expect($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Draft);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('id="concern-'.$concern->id.'"', false)
        ->assertSee('background-color:#f1f5f9', false)
        ->assertSee("task: 'concern-disposition'", false)
        ->assertSee('data-workspace-modal-form="concern-disposition"', false)
        ->assertSee('data-refresh-scope="worksheet"', false)
        ->assertSee('submitWorksheetForm', false)
        ->assertSee('Draft')
        ->assertSee('Recommended')
        ->assertSee('Approved')
        ->assertSee('Deferred');

    $this->patch(route('operations.repair-orders.concerns.disposition', [$repairOrder, $concern]), [
        'disposition' => RepairOrderConcernDisposition::Approved->value,
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#concern-'.$concern->id);

    expect($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Approved);
});

test('concern disposition ajax refresh returns html worksheet and updated estimate version', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake vibration',
        'recommendation_intent' => 'immediate_attention',
        'position' => 1,
    ]);

    $openedVersion = app(RepairOrderConcurrency::class)
        ->openedVersion($repairOrder->fresh());

    $response = $this->followingRedirects()->patch(route('operations.repair-orders.concerns.disposition', [$repairOrder, $concern]), [
        RepairOrderConcurrency::FIELD => $openedVersion,
        'disposition' => RepairOrderConcernDisposition::Deferred->value,
    ], [
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'text/html',
    ]);

    $response->assertOk()
        ->assertSee('id="estimate-lines"', false)
        ->assertSee('id="estimate-total-panel"', false)
        ->assertSee('ops-worksheet-concern--deferred', false);

    expect($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Deferred);
});

test('concern disposition supports ajax continuity with server authoritative response', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake vibration',
        'recommendation_intent' => 'immediate_attention',
        'position' => 1,
    ]);

    $this->patchJson(route('operations.repair-orders.concerns.disposition', [$repairOrder, $concern]), [
        'disposition' => RepairOrderConcernDisposition::Deferred->value,
    ])
        ->assertOk()
        ->assertJson([
            'disposition' => RepairOrderConcernDisposition::Deferred->value,
            'label' => 'Deferred',
        ]);

    expect($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Deferred);
});

test('concern disposition cannot be changed through another repair order', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $otherRepairOrder = repairOrderForEstimateWorkspace();

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Oil leak',
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    $this->patch(route('operations.repair-orders.concerns.disposition', [$otherRepairOrder, $concern]), [
        'disposition' => RepairOrderConcernDisposition::Deferred->value,
    ])->assertNotFound();

    expect($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Draft);
});

test('scope disposition can return to draft after authorization posture changes', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake vibration',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'recommendation_intent' => 'immediate_attention',
        'position' => 1,
    ]);

    $this->patch(route('operations.repair-orders.concerns.disposition', [$repairOrder, $concern]), [
        'disposition' => RepairOrderConcernDisposition::Draft->value,
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#concern-'.$concern->id);

    expect($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Draft);
});

test('the shop can edit estimate lines inline and totals stay server authoritative', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);

    $line = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Initial diagnostic',
        'quantity' => '1.00',
        'unit_price_cents' => 10000,
        'total_cents' => 10000,
    ]);

    $this->patch(route('operations.repair-orders.lines.update', [$repairOrder, $line]), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part->value,
        'description' => 'Brake pads',
        'quantity' => '2.00',
        'unit_price' => '64.25',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    $line->refresh();
    $repairOrder->load('lines');
    $totals = app(RepairOrderEstimate::class)->totalsFor($repairOrder);

    expect($line->type)->toBe(RepairOrderLineType::Part)
        ->and($line->description)->toBe('Brake pads')
        ->and($line->unit_price_cents)->toBe(6425)
        ->and($line->total_cents)->toBe(12850)
        ->and($totals->partsCents())->toBe(12850)
        ->and($totals->totalCents())->toBe(12850);
});

test('shop fees are allocated on eligible concern lines instead of generated as general lines', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => true,
        'shop_fee_rate' => '10.000',
        'shop_fee_cap_cents' => null,
    ]);

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Diagnostic',
        'quantity' => '1.00',
        // Posted unit_price without override is ignored — LaborAuthority resolves policy rate ($165/hr).
        'unit_price' => '100.00',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    $line = RepairOrderLine::query()->where('description', 'Diagnostic')->firstOrFail();

    expect($line->unit_price_cents)->toBe(16500)
        ->and($line->shop_fee_cents)->toBe(1650)
        ->and($line->total_cents)->toBe(18150)
        ->and(RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->where('type', RepairOrderLineType::Fee)->count())->toBe(0);
});

test('only one estimate line renders in edit mode at a time', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);

    $firstLine = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'First diagnostic',
        'quantity' => '1.00',
        'unit_price_cents' => 10000,
        'total_cents' => 10000,
    ]);

    $secondLine = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Brake pads',
        'quantity' => '1.00',
        'unit_price_cents' => 8000,
        'total_cents' => 8000,
    ]);

    $this->get(route('operations.repair-orders.show', [
        'repairOrder' => $repairOrder,
        'editing_line' => $secondLine->id,
    ]))
        ->assertOk()
        ->assertSee('id="line-update-'.$secondLine->id.'"', false)
        ->assertDontSee('id="line-update-'.$firstLine->id.'"', false);
});

test('estimate rows are read mostly until a row is explicitly edited', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);
    $emptyConcern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Empty terminal concern',
        'recommendation_intent' => 'maintenance',
        'position' => 2,
    ]);
    $line = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Initial diagnostic',
        'quantity' => '1.00',
        'unit_price_cents' => 10000,
        'total_cents' => 10000,
    ]);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Initial diagnostic')
        ->assertSee('Edit')
        ->assertDontSee('name="unit_price" value="100.00"', false);

    $this->get(route('operations.repair-orders.show', [
        'repairOrder' => $repairOrder,
        'editing_line' => $line->id,
    ]))
        ->assertOk()
        ->assertSee('id="workspace-modal-host"', false)
        ->assertSee('data-workspace-modal-form="edit-line"', false)
        ->assertSee('name="labor_entered_hours"', false)
        ->assertSee('name="labor_category_key"', false);
});

test('estimate lines can move between concern groups without becoming general lines', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake vibration',
        'recommendation_intent' => 'immediate_attention',
        'position' => 1,
    ]);

    $line = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Road test',
        'quantity' => '1.00',
        'unit_price_cents' => 5000,
        'total_cents' => 5000,
    ]);

    $this->patch(route('operations.repair-orders.lines.update', [$repairOrder, $line]), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Road test',
        'quantity' => '1.00',
        'unit_price' => '50.00',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    expect($line->fresh()->repair_order_concern_id)->toBe($concern->id);

    $this->patch(route('operations.repair-orders.lines.update', [$repairOrder, $line]), [
        'repair_order_concern_id' => null,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Road test',
        'quantity' => '1.00',
        'unit_price' => '50.00',
    ])->assertSessionHasErrors('repair_order_concern_id');

    expect($line->fresh()->repair_order_concern_id)->toBe($concern->id);
});

test('the shop can delete an estimate line and return to the estimate section', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);

    $line = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Mistyped diagnostic',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'total_cents' => 15000,
    ]);

    $this->delete(route('operations.repair-orders.lines.destroy', [$repairOrder, $line]))
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    expect(RepairOrderLine::query()->whereKey($line->id)->exists())->toBeFalse();

    $repairOrder->refresh()->load('lines');
    $totals = app(RepairOrderEstimate::class)->totalsFor($repairOrder);

    expect($totals->totalCents())->toBe(0);
});

test('an estimate line cannot be deleted through another repair order', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $otherRepairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);

    $line = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Diagnostic',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'total_cents' => 15000,
    ]);

    $this->delete(route('operations.repair-orders.lines.destroy', [$otherRepairOrder, $line]))
        ->assertNotFound();

    expect(RepairOrderLine::query()->whereKey($line->id)->exists())->toBeTrue();
});

test('opening a repair order lands on the presentation workspace', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Coolant leak',
        'notes' => 'Pressure test found seepage at upper hose.',
        'customer_states' => 'Customer smells coolant after parking overnight.',
        'verified_findings' => 'Cooling system pressure test found upper hose seepage.',
        'dtcs_summary' => 'No cooling system DTCs present.',
        'recommendation' => 'Replace upper radiator hose and retest for pressure loss.',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'recommendation_intent' => 'immediate_attention',
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace upper radiator hose',
        'quantity' => '1.20',
        'unit_price_cents' => 15000,
        'total_cents' => 18000,
    ]);

    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => true,
        'shop_fee_rate' => '6.667',
        'shop_fee_cap_cents' => null,
    ]);
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('ops-estimate-workspace--builder', false)
        ->assertDontSee('ops-estimate-workspace--review', false)
        ->assertDontSee('data-ro-mode-control', false)
        ->assertDontSee('>Editing<', false)
        ->assertDontSee('>Viewing<', false)
        ->assertSee(route('operations.repair-orders.show', $repairOrder))
        ->assertDontSee('Edit Estimate')
        ->assertSee('ops-service-lane-band', false)
        ->assertSee('Rosa', false)
        ->assertSee('Garcia', false)
        ->assertSee('ARK123', false)
        ->assertSee('Coolant leak')
        ->assertSee('Customer smells coolant after parking overnight.')
        ->assertSee('Cooling system pressure test found upper hose seepage.')
        ->assertSee('Replace upper radiator hose and retest for pressure loss.')
        ->assertSee('Replace upper radiator hose')
        ->assertDontSee('General Lines')
        ->assertSee('Fees')
        ->assertSee('$12.00')
        ->assertSee('$192.00')
        ->assertSee('+ Add Work', false)
        ->assertSee('id="workspace-modal-host"', false)
        ->assertDontSee('Builder Mode')
        ->assertDontSee('Add Line')
        ->assertDontSee('name="concern_summary"', false)
        ->assertDontSee('ops-disposition-select--readonly', false);
});

test('presentation workspace exposes estimate authoring without a separate builder route', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace('Fleet');
    $repairOrder->update(['status' => RepairOrderStatus::Estimate]);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee(route('operations.repair-orders.show', $repairOrder))
        ->assertSee('Building Estimate')
        ->assertSee('Fleet')
        ->assertSee('ops-estimate-workspace--builder', false)
        ->assertSee('+ Add Work', false)
        ->assertSee('id="workspace-modal-host"', false)
        ->assertSee('submitWorksheetForm', false)
        ->assertDontSee('data-ro-mode-control', false)
        ->assertDontSee('>Editing<', false)
        ->assertDontSee('Edit Estimate')
        ->assertDontSee(route('operations.repair-orders.estimate.email', $repairOrder))
        ->assertDontSee('ops-estimate-email-form');
});

test('builder pricing guidance is server fed without client-side matrix math', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);
    \App\Ark\Operations\RepairOrders\RepairOrderWorkGroup::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'title' => 'Diagnostic labor',
        'position' => 1,
    ]);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee(route('operations.repair-orders.lines.pricing-preview', $repairOrder))
        ->assertSee('refreshPreview()', false)
        ->assertSee('Server pricing guidance is unavailable.', false)
        ->assertDontSee('matrixRow', false)
        ->assertDontSee('costCents()', false)
        ->assertDontSee('suggestedCents', false);
});

test('technician lands on production work order and cannot author estimate lines', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);

    expect($technician->can(ArkCapability::RepairOrdersView->value))->toBeTrue()
        ->and($technician->can(ArkCapability::RepairOrdersManage->value))->toBeFalse();

    $this->actingAs($technician)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('data-technician-production-landing', false)
        ->assertDontSee('data-ro-mode-control', false)
        ->assertDontSee('Toggle Mode (V)', false)
        ->assertDontSee('>Editing<', false)
        ->assertDontSee('+ Add Work', false)
        ->assertDontSee('Edit Estimate');

    $this->actingAs($technician)
        ->post(route('operations.repair-orders.lines.store', $repairOrder), [
            'repair_order_concern_id' => $concern->id,
            'type' => RepairOrderLineType::Labor->value,
            'description' => 'Tech should not author',
            'quantity' => '1.00',
            'unit_price' => '100.00',
        ])
        ->assertForbidden();
});

test('note lines render as communication notes instead of priced work in review mode', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Electrical diagnostic',
        'recommendation_intent' => 'immediate_attention',
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Note,
        'description' => 'Call customer before exceeding diagnostic authorization',
        'quantity' => '1.00',
        'unit_price_cents' => 0,
        'total_cents' => 0,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Call customer before exceeding diagnostic authorization')
        ->assertSee('On tech sheet and customer estimate')
        ->assertDontSee('title="Edit line"')
        ->assertDontSee('aria-label="Edit Call customer before exceeding diagnostic authorization"');
});

test('review mode never shows note line edit pencil even when show actions is requested', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front end suspension',
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Note,
        'description' => 'Diagnosing front end suspension',
        'quantity' => '1.00',
        'unit_price_cents' => 0,
        'total_cents' => 0,
        'is_private' => true,
    ]);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Diagnosing front end suspension')
        ->assertSee('On tech sheet · hidden from customer')
        ->assertDontSee('title="Edit line"')
        ->assertDontSee('aria-label="Edit Diagnosing front end suspension"');
});

test('large grouped estimates remain renderable in review and builder hot paths', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();

    foreach (range(1, 12) as $concernIndex) {
        $concern = RepairOrderConcern::query()->create([
            'repair_order_id' => $repairOrder->id,
            'summary' => 'Dense concern '.$concernIndex,
            'customer_states' => 'Customer states symptom '.$concernIndex.' happens during real driving.',
            'verified_findings' => 'Verified finding '.$concernIndex.' under shop test conditions.',
            'recommendation' => 'Recommend grouped repair path '.$concernIndex.'.',
            'recommendation_intent' => $concernIndex % 3 === 0 ? 'immediate_attention' : 'maintenance',
            'position' => $concernIndex,
            'disposition' => $concernIndex % 4 === 0
                ? RepairOrderConcernDisposition::Deferred
                : RepairOrderConcernDisposition::Recommended,
        ]);

        foreach (range(1, 6) as $lineIndex) {
            RepairOrderLine::query()->create([
                'repair_order_id' => $repairOrder->id,
                'repair_order_concern_id' => $concern->id,
                'type' => $lineIndex % 3 === 0 ? RepairOrderLineType::Part : RepairOrderLineType::Labor,
                'description' => 'Dense line '.$concernIndex.'-'.$lineIndex,
                'quantity' => '1.00',
                'unit_price_cents' => 10000 + ($lineIndex * 1000),
                'subtotal_cents' => 10000 + ($lineIndex * 1000),
                'total_cents' => 10000 + ($lineIndex * 1000),
            ]);
        }
    }

    $repairOrder->update(['status' => RepairOrderStatus::Estimate]);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Dense concern 12')
        ->assertSee('Dense line 12-6')
        ->assertSee('Estimate Total');

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Recommended Work')
        ->assertSee('12 scopes · 72 lines')
        ->assertSee('submitWorksheetForm', false)
        ->assertSee('id="estimate-total-panel"', false)
        ->assertSee('+ Add Work', false);
});

test('the shop can move an estimate to awaiting approval through lifecycle controls', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Diagnostic',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'total_cents' => 15000,
    ]);
    $repairOrder->update(['status' => RepairOrderStatus::Estimate]);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::WaitingApproval->value,
    ])->assertRedirect();

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::WaitingApproval))->toBeTrue();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Waiting Approval');
});

test('the shop can move awaiting approval back to building estimate', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Diagnostic',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'total_cents' => 15000,
    ]);

    $repairOrder->update(['status' => RepairOrderStatus::WaitingApproval]);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::Estimate->value,
    ])->assertRedirect();

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Estimate))->toBeTrue();
});

test('lifecycle cannot move forward before estimate lines exist', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();

    $repairOrder->update(['status' => RepairOrderStatus::Estimate]);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::WaitingApproval->value,
    ])->assertRedirect()
        ->assertSessionHasErrors('lifecycle');

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Estimate))->toBeTrue();
});

test('terminal repair orders cannot be edited from the estimate workspace', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);
    $emptyConcern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Empty terminal concern',
        'recommendation_intent' => 'maintenance',
        'position' => 2,
    ]);
    $line = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Diagnostic',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'total_cents' => 15000,
    ]);

    $repairOrder->update(['status' => RepairOrderStatus::Closed]);

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Late diagnostic',
        'quantity' => '1.00',
        'unit_price' => '150.00',
    ])->assertStatus(423);

    $this->patch(route('operations.repair-orders.lines.update', [$repairOrder, $line]), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Changed diagnostic',
        'quantity' => '1.00',
        'unit_price' => '150.00',
    ])->assertStatus(423);

    $this->delete(route('operations.repair-orders.concerns.destroy', [$repairOrder, $emptyConcern]))
        ->assertStatus(423);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Closed')
        ->assertSee('Estimate PDF')
        ->assertSee(route('operations.repair-orders.estimate.pdf', $repairOrder), false)
        ->assertSee('2018 Honda Accord')
        ->assertSee('ops-mileage-inline', false)
        ->assertDontSee('+ Add Work')
        ->assertDontSee('Save Narrative')
        ->assertDontSee('Late diagnostic')
        ->assertDontSee('Add Scope');
});
