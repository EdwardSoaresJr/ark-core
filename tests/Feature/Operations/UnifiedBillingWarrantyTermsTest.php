<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\DocumentDisclaimerComposer;
use App\Ark\Operations\Documents\DocumentFooterPresenter;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\EstimatePricing\BillingClassDocumentTermsMode;
use App\Ark\Operations\EstimatePricing\BillingClassWarrantyMode;
use App\Ark\Operations\EstimatePricing\LaborWorkTypeMatrixProjection;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\PersistCustomerBillingClasses;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    Artisan::call('migrate', ['--path' => 'database/migrations/2026_11_19_100000_add_billing_class_warranty_and_defaults.php', '--force' => true]);

    $types = ShopSettings::DEFAULT_CUSTOMER_TYPES;
    foreach ($types as $i => $row) {
        if (($row['key'] ?? '') === 'warranty' || ($row['key'] ?? '') === 'repairpal') {
            $types[$i]['warranty_mode'] = BillingClassWarrantyMode::ClassSpecific->value;
            $types[$i]['warranty'] = [
                'months' => 12,
                'miles' => 12000,
                'covers_parts' => true,
                'covers_labor' => true,
                'description' => 'Third-party warranty program coverage.',
                'exclusions' => 'Customer remains responsible for non-covered charges.',
                'third_party' => true,
            ];
            $types[$i]['document_terms_mode'] = BillingClassDocumentTermsMode::Append->value;
            $types[$i]['document_terms'] = 'Third-party warranty customer responsibility disclaimer.';
        }
        if (($row['key'] ?? '') === 'retail') {
            $types[$i]['is_default'] = true;
            $types[$i]['warranty_mode'] = BillingClassWarrantyMode::InheritShop->value;
            $types[$i]['document_terms_mode'] = BillingClassDocumentTermsMode::Append->value;
            $types[$i]['document_terms'] = 'Retail shop warranty terms.';
        }
    }

    ShopSettings::current()->update([
        'customer_types' => $types,
        'labor_categories' => ShopSettings::DEFAULT_LABOR_CATEGORIES,
        'default_labor_rate_cents' => 16500,
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'shop_warranty' => [
            'months' => 24,
            'miles' => 24000,
            'covers_parts' => true,
            'covers_labor' => true,
            'description' => 'Shop warranty on parts and labor.',
            'exclusions' => 'Abuse excluded.',
            'third_party' => false,
        ],
        'estimate_disclaimer' => 'Shop-wide estimate protection.',
        'invoice_disclaimer' => 'Shop-wide invoice protection.',
    ]);
    ShopSettings::forgetCurrent();

    $this->advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $this->actingAs($this->admin);
    $this->persist = app(PersistCustomerBillingClasses::class);
});

test('create rename and default billing class with class settings gear for every active class', function () {
    $created = $this->persist->create(ShopSettings::current(), [
        'name' => 'Sammy Cars',
        'warranty_mode' => BillingClassWarrantyMode::ClassSpecific->value,
        'warranty' => [
            'months' => 12,
            'miles' => 12000,
            'covers_parts' => true,
            'covers_labor' => true,
            'description' => 'Sammy Cars coverage',
            'exclusions' => 'Racing excluded',
            'third_party' => false,
        ],
        'document_terms_mode' => BillingClassDocumentTermsMode::Append->value,
        'document_terms' => 'Sammy Cars commercial terms apply.',
    ]);

    expect($created['key'])->not->toBeEmpty()
        ->and($created['labor_column_key'])->toStartWith('custom_')
        ->and($created['name'])->toBe('Sammy Cars');

    $key = $created['key'];
    $renamed = $this->persist->upsertOne(ShopSettings::current(), $key, [
        'name' => 'Sammy Cars Fleet',
    ]);
    expect($renamed['key'])->toBe($key)
        ->and($renamed['name'])->toBe('Sammy Cars Fleet')
        ->and($renamed['labor_column_key'])->toBe($created['labor_column_key']);

    $this->persist->setDefault(ShopSettings::current(), $key);
    $settings = ShopSettings::current();
    expect($settings->defaultBillingClassKey())->toBe($key);

    $defaults = collect($settings->customerTypeRows())->where('is_default', true)->where('is_active', true);
    expect($defaults)->toHaveCount(1);

    $matrix = app(LaborWorkTypeMatrixProjection::class)->build();
    $sammyColumn = collect($matrix['columns'])->firstWhere('customer_class_key', $key);
    expect($sammyColumn)->not->toBeNull()
        ->and($sammyColumn['has_customer_class'])->toBeTrue();

    $catalog = $this->persist->catalog($settings);
    expect(collect($catalog['manage_classes'])->pluck('key'))->toContain($key)
        ->and(collect($catalog['manage_classes'])->every(fn (array $row): bool => filled($row['key'])))->toBeTrue();
});

test('new customers receive designated default and concern override does not affect siblings', function () {
    $sammy = $this->persist->create(ShopSettings::current(), ['name' => 'Sammy Cars']);
    $this->persist->setDefault(ShopSettings::current(), $sammy['key']);

    $this->actingAs($this->advisor);
    $this->post(route('operations.customers.store'), [
        'first_name' => 'Ada',
        'last_name' => 'Default',
        'phone' => '5551001001',
        'email' => 'ada-default@example.com',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $customer = Customer::query()->where('email', 'ada-default@example.com')->sole();
    expect($customer->billingClassKey())->toBe($sammy['key']);

    $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'year' => 2020, 'make' => 'Toyota', 'model' => 'Camry']);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Mixed',
    ]);

    $this->post(route('operations.repair-orders.concerns.store', $repairOrder), ['summary' => 'Brakes'])->assertSessionHasNoErrors();
    $this->post(route('operations.repair-orders.concerns.store', $repairOrder), ['summary' => 'Transmission'])->assertSessionHasNoErrors();

    $brakes = RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->where('summary', 'like', 'Brakes%')->sole();
    $transmission = RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->where('summary', 'like', 'Transmission%')->sole();

    expect($brakes->inheritsCustomerBilling())->toBeTrue()
        ->and($transmission->inheritsCustomerBilling())->toBeTrue();

    $this->patchJson(route('operations.repair-orders.concerns.billing-posture', [$repairOrder, $transmission]), [
        'billing_posture' => 'warranty_other',
    ])->assertOk();

    expect($transmission->fresh()->inheritsCustomerBilling())->toBeFalse()
        ->and($brakes->fresh()->inheritsCustomerBilling())->toBeTrue()
        ->and($brakes->fresh()->effectiveBillingClassKey())->toBe($customer->billingClassLaborColumnKey());
});

test('inherited warranty follows class and concern override restores inheritance', function () {
    $settings = ShopSettings::current();
    $warrantyRow = collect($settings->customerTypeRows())->firstWhere('key', 'warranty');
    expect($warrantyRow['warranty_mode'])->toBe(BillingClassWarrantyMode::ClassSpecific->value);

    $customer = Customer::query()->create([
        'first_name' => 'Pat',
        'last_name' => 'Warranty',
        'phone' => '555-1002',
        'billing_class_key' => 'retail',
    ]);
    $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'year' => 2019, 'make' => 'Ford', 'model' => 'F150']);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Warranty mix',
    ]);
    $this->post(route('operations.repair-orders.concerns.store', $repairOrder), ['summary' => 'Oil change'])->assertSessionHasNoErrors();
    $this->post(route('operations.repair-orders.concerns.store', $repairOrder), ['summary' => 'Transmission'])->assertSessionHasNoErrors();
    $oil = RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->where('summary', 'like', 'Oil%')->sole();
    $transmission = RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->where('summary', 'like', 'Transmission%')->sole();

    $this->patchJson(route('operations.repair-orders.concerns.billing-posture', [$repairOrder, $transmission]), [
        'billing_posture' => 'warranty_other',
    ])->assertOk();

    $oilEffective = app(\App\Ark\Operations\EstimatePricing\EffectiveWarrantyResolver::class)->forConcern($oil->fresh());
    $txEffective = app(\App\Ark\Operations\EstimatePricing\EffectiveWarrantyResolver::class)->forConcern($transmission->fresh());
    expect($oilEffective['source'])->toBe('shop')
        ->and($oilEffective['warranty']['months'])->toBe(24)
        ->and($txEffective['source'])->toBe('class')
        ->and($txEffective['third_party_disclaimer'])->not->toBeNull();

    $this->patchJson(route('operations.repair-orders.concerns.warranty', [$repairOrder, $oil]), [
        'action' => 'override',
        'warranty' => [
            'months' => 6,
            'miles' => 6000,
            'covers_parts' => true,
            'covers_labor' => false,
            'description' => 'Advisor exception',
            'exclusions' => 'None',
            'third_party' => false,
        ],
    ])->assertOk();

    $oil = $oil->fresh();
    expect($oil->inheritsClassWarranty())->toBeFalse();
    $overridden = app(\App\Ark\Operations\EstimatePricing\EffectiveWarrantyResolver::class)->forConcern($oil);
    expect($overridden['source'])->toBe('override')
        ->and($overridden['warranty']['months'])->toBe(6);

    // Changing billing class must not erase the warranty override.
    $this->patchJson(route('operations.repair-orders.concerns.billing-posture', [$repairOrder, $oil]), [
        'billing_posture' => 'fleet',
    ])->assertOk();
    expect($oil->fresh()->inheritsClassWarranty())->toBeFalse()
        ->and($oil->fresh()->warrantyOverridePayload()['months'])->toBe(6);

    $this->patchJson(route('operations.repair-orders.concerns.warranty', [$repairOrder, $oil]), [
        'action' => 'inherit',
    ])->assertOk();
    expect($oil->fresh()->inheritsClassWarranty())->toBeTrue()
        ->and($oil->fresh()->warrantyOverridePayload())->toBeNull();
});

test('mixed RO documents scope warranties and freeze issued snapshots', function () {
    $this->persist->upsertOne(ShopSettings::current(), 'retail', [
        'document_terms_mode' => BillingClassDocumentTermsMode::Append->value,
        'document_terms' => 'Retail shop warranty terms.',
        'warranty_mode' => BillingClassWarrantyMode::InheritShop->value,
    ]);
    $this->persist->upsertOne(ShopSettings::current(), 'warranty', [
        'document_terms_mode' => BillingClassDocumentTermsMode::Replace->value,
        'document_terms' => 'Third-party warranty customer responsibility disclaimer.',
        'warranty_mode' => BillingClassWarrantyMode::ClassSpecific->value,
        'warranty' => [
            'months' => 12,
            'miles' => 12000,
            'covers_parts' => true,
            'covers_labor' => true,
            'description' => 'TPA coverage',
            'exclusions' => 'Non-covered charges',
            'third_party' => true,
        ],
    ]);

    $customer = Customer::query()->create([
        'first_name' => 'Mix',
        'last_name' => 'Docs',
        'phone' => '555-1003',
        'billing_class_key' => 'retail',
    ]);
    $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'year' => 2018, 'make' => 'Honda', 'model' => 'Civic']);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Mixed docs',
    ]);
    $this->post(route('operations.repair-orders.concerns.store', $repairOrder), ['summary' => 'Brake repair'])->assertSessionHasNoErrors();
    $this->post(route('operations.repair-orders.concerns.store', $repairOrder), ['summary' => 'Transmission warranty'])->assertSessionHasNoErrors();
    $this->post(route('operations.repair-orders.concerns.store', $repairOrder), ['summary' => 'Oil change'])->assertSessionHasNoErrors();

    RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->update([
        'disposition' => RepairOrderConcernDisposition::Recommended->value,
    ]);

    $transmission = RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->where('summary', 'like', 'Transmission%')->sole();
    $this->patchJson(route('operations.repair-orders.concerns.billing-posture', [$repairOrder, $transmission]), [
        'billing_posture' => 'warranty_other',
    ])->assertOk();

    $repairOrder->load('concerns', 'customer');
    $documents = app(DocumentDisclaimerComposer::class)->snapshotFor(
        FinancialDocumentType::Estimate,
        ShopSettings::current(),
        $customer->customer_type,
        $repairOrder,
    );
    $footer = app(DocumentFooterPresenter::class)->present([
        'documents' => $documents,
        'settings' => [
            'recommendation_disclaimer' => '',
        ],
        'document_type' => 'estimate',
        'totals' => [],
    ]);
    expect($footer['important_information'])->toContain('Shop-wide estimate protection.')
        ->and($footer['concern_terms'])->not->toBeEmpty();

    $headings = collect($footer['concern_terms'])->pluck('heading');
    expect($headings->implode(' '))->toContain('Transmission')
        ->and($headings->filter(fn (string $heading): bool => str_contains($heading, 'Brake') || str_contains($heading, 'Oil'))->count())->toBeGreaterThanOrEqual(1)
        ->and(collect($footer['concern_terms'])->flatten()->implode(' '))->toContain('third-party');

    // Identical retail terms must still keep separate concern headings.
    $retailHeadings = $headings->filter(fn (string $heading): bool => str_contains($heading, 'Retail'))->values();
    expect($retailHeadings->count())->toBeGreaterThanOrEqual(2);

    $document = EstimateDocument::query()->create([
        'repair_order_id' => $repairOrder->id,
        'document_type' => FinancialDocumentType::Estimate,
        'document_number' => 1,
        'snapshot_json' => [
            'documents' => $documents,
            'settings' => ['recommendation_disclaimer' => ''],
            'document_type' => 'estimate',
            'totals' => [],
            'concerns' => [],
            'sentinel' => 'presented-contract',
        ],
        'status' => 'draft',
        'customer_presented_at' => now(),
        'created_by' => $this->advisor->id,
    ]);

    ShopSettings::current()->update([
        'estimate_disclaimer' => 'CHANGED AFTER ISSUE — must not appear',
        'shop_warranty' => [
            'months' => 1,
            'miles' => 1,
            'covers_parts' => false,
            'covers_labor' => false,
            'description' => 'changed',
            'exclusions' => 'changed',
            'third_party' => false,
        ],
    ]);
    ShopSettings::forgetCurrent();

    $refreshed = app(\App\Ark\Operations\Documents\EstimateDocumentService::class)
        ->refreshSnapshot($document->fresh(['repairOrder']));
    expect(data_get($refreshed->snapshot_json, 'documents.global_disclaimer'))
        ->toBe('Shop-wide estimate protection.')
        ->and(data_get($refreshed->snapshot_json, 'sentinel'))->toBe('presented-contract');

    $invoiceDocuments = app(DocumentDisclaimerComposer::class)->snapshotFor(
        FinancialDocumentType::Invoice,
        ShopSettings::current(),
        $customer->customer_type,
        $repairOrder->fresh(['concerns', 'customer']),
    );
    $invoiceFooter = app(DocumentFooterPresenter::class)->present([
        'documents' => $invoiceDocuments,
        'settings' => ['recommendation_disclaimer' => ''],
        'document_type' => 'invoice',
        'totals' => [],
    ]);
    expect($invoiceFooter['concern_terms'])->not->toBeEmpty();
});

test('open RO warranty and document terms follow the commercial stamp, not later shop settings', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Frozen',
        'last_name' => 'Terms',
        'phone' => '555-1005',
        'billing_class_key' => 'retail',
    ]);
    $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'year' => 2019, 'make' => 'Ford', 'model' => 'Focus']);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Stamp freeze',
    ]);
    $this->post(route('operations.repair-orders.concerns.store', $repairOrder), ['summary' => 'Pads'])->assertSessionHasNoErrors();
    $concern = RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->sole();
    $concern->update(['disposition' => RepairOrderConcernDisposition::Recommended]);

    $before = app(\App\Ark\Operations\EstimatePricing\EffectiveWarrantyResolver::class)->forConcern($concern->fresh(['repairOrder']));
    expect($before['source'])->toBe('shop')
        ->and($before['warranty']['months'])->toBe(24);

    $documentsBefore = app(DocumentDisclaimerComposer::class)->snapshotFor(
        FinancialDocumentType::Estimate,
        ShopSettings::current(),
        $customer->customer_type,
        $repairOrder->fresh(['concerns', 'customer']),
    );
    expect($documentsBefore['global_disclaimer'])->toBe('Shop-wide estimate protection.')
        ->and(collect($documentsBefore['concern_terms'])->pluck('terms')->implode(' '))->toContain('Retail shop warranty terms');

    ShopSettings::current()->update([
        'estimate_disclaimer' => 'LIVE SHOP CHANGE — must not rewrite open RO',
        'shop_warranty' => [
            'months' => 3,
            'miles' => 3000,
            'covers_parts' => false,
            'covers_labor' => false,
            'description' => 'live change',
            'exclusions' => 'live',
            'third_party' => false,
        ],
        'customer_types' => collect(ShopSettings::current()->storedCustomerTypeRows())
            ->map(function (array $row): array {
                if (($row['key'] ?? '') === 'retail') {
                    $row['document_terms'] = 'LIVE retail terms rewrite';
                    $row['warranty'] = [
                        'months' => 1,
                        'miles' => 1,
                        'covers_parts' => false,
                        'covers_labor' => false,
                        'description' => 'live class',
                        'exclusions' => '',
                        'third_party' => false,
                    ];
                    $row['warranty_mode'] = BillingClassWarrantyMode::ClassSpecific->value;
                }

                return $row;
            })
            ->all(),
    ]);
    ShopSettings::forgetCurrent();

    $after = app(\App\Ark\Operations\EstimatePricing\EffectiveWarrantyResolver::class)
        ->forConcern($concern->fresh(['repairOrder']));
    expect($after['source'])->toBe('shop')
        ->and($after['warranty']['months'])->toBe(24)
        ->and($after['warranty']['description'])->toBe('Shop warranty on parts and labor.');

    $documentsAfter = app(DocumentDisclaimerComposer::class)->snapshotFor(
        FinancialDocumentType::Estimate,
        ShopSettings::current(),
        $customer->customer_type,
        $repairOrder->fresh(['concerns', 'customer']),
    );
    expect($documentsAfter['global_disclaimer'])->toBe('Shop-wide estimate protection.')
        ->and(collect($documentsAfter['concern_terms'])->pluck('terms')->implode(' '))
        ->toContain('Retail shop warranty terms.')
        ->and(collect($documentsAfter['concern_terms'])->pluck('terms')->implode(' '))
        ->not->toContain('LIVE retail terms rewrite');

    // Explicit concern override remains allowed and auditable after shop changes.
    $this->patchJson(route('operations.repair-orders.concerns.warranty', [$repairOrder, $concern]), [
        'action' => 'override',
        'warranty' => [
            'months' => 6,
            'miles' => 6000,
            'covers_parts' => true,
            'covers_labor' => true,
            'description' => 'Advisor override after shop change',
            'exclusions' => '',
            'third_party' => false,
        ],
    ])->assertOk();

    $overridden = app(\App\Ark\Operations\EstimatePricing\EffectiveWarrantyResolver::class)
        ->forConcern($concern->fresh(['repairOrder']));
    expect($overridden['source'])->toBe('override')
        ->and($overridden['warranty']['months'])->toBe(6);
});

test('concern warranty control is on the repair order worksheet', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Ui',
        'last_name' => 'Warranty',
        'phone' => '555-1006',
        'billing_class_key' => 'retail',
    ]);
    $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'year' => 2021, 'make' => 'Toyota', 'model' => 'Corolla']);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'UI warranty',
    ]);
    $this->post(route('operations.repair-orders.concerns.store', $repairOrder), ['summary' => 'Oil'])->assertSessionHasNoErrors();

    $this->actingAs($this->advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('data-concern-warranty-choices', false)
        ->assertSee('Effective warranty', false)
        ->assertSee('Override for this concern', false)
        ->assertSee('Save concern warranty', false);
});

test('closed RO financial fingerprint stays stable after class settings change', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Closed',
        'last_name' => 'Ro',
        'phone' => '555-1004',
        'billing_class_key' => 'retail',
    ]);
    $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'year' => 2017, 'make' => 'Chevy', 'model' => 'Malibu']);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Closed fingerprint',
    ]);
    $this->post(route('operations.repair-orders.concerns.store', $repairOrder), ['summary' => 'Labor'])->assertSessionHasNoErrors();
    $concern = RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->sole();
    $concern->update(['disposition' => RepairOrderConcernDisposition::Approved]);

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => 'labor',
        'description' => 'Stable labor',
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => '1.00',
        'labor_adjustment' => 'normal',
        'labor_rate_overridden' => '0',
    ])->assertSessionHasNoErrors();

    $line = RepairOrderLine::query()->where('description', 'Stable labor')->sole();
    $priorCents = (int) $line->amount_cents;
    $repairOrder->update(['status' => RepairOrderStatus::Closed]);

    $this->persist->upsertOne(ShopSettings::current(), 'retail', [
        'shop_fees_enabled' => false,
        'discount_type' => 'labor',
        'discount_amount' => '50.00',
    ]);

    expect((int) $line->fresh()->amount_cents)->toBe($priorCents);
});
