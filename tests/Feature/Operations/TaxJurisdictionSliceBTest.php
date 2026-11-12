<?php

use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\EstimateDocumentPdfSnapshot;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Documents\PdfRenderer;
use App\Ark\Operations\EstimatePricing\TaxJurisdictionMatcher;
use App\Ark\Operations\EstimatePricing\TaxProfile;
use App\Ark\Operations\EstimatePricing\TaxSourcingLocationKind;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Financial\GenerateInvoiceSnapshotAction;
use App\Ark\Operations\Portal\EstimateAccessToken;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderAuthorizationStoreController;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\SelectTaxProfileOnRepairOrderAction;
use App\Ark\Operations\RepairOrders\WorkCompletionAuthorization;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Brick\Money\Money;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    ShopSettings::current()->update([
        'tax_enabled' => true,
        'tax_document_presentation' => 'combined',
        'tax_profiles' => [
            [
                'key' => 'springs',
                'name' => 'Colorado Springs',
                'is_default' => true,
                'is_active' => true,
                'state' => 'CO',
                'counties' => ['El Paso'],
                'cities' => ['Colorado Springs'],
                'zip_codes' => ['80903', '80909'],
                'taxable_labor' => false,
                'taxable_parts' => true,
                'taxable_shop_fees' => false,
                'components' => [
                    ['id' => 'st', 'name' => 'State', 'rate' => '2.900', 'jurisdiction_type' => 'state', 'sort_order' => 1, 'is_active' => true],
                    ['id' => 'city', 'name' => 'City', 'rate' => '3.120', 'jurisdiction_type' => 'city', 'sort_order' => 2, 'is_active' => true],
                ],
            ],
            [
                'key' => 'denver',
                'name' => 'Denver',
                'is_default' => false,
                'is_active' => true,
                'state' => 'CO',
                'counties' => ['Denver'],
                'cities' => ['Denver'],
                'zip_codes' => ['80202', '80903'],
                'taxable_labor' => false,
                'taxable_parts' => true,
                'taxable_shop_fees' => false,
                'components' => [
                    ['id' => 'st2', 'name' => 'State', 'rate' => '2.900', 'jurisdiction_type' => 'state', 'sort_order' => 1, 'is_active' => true],
                    ['id' => 'city2', 'name' => 'City', 'rate' => '4.310', 'jurisdiction_type' => 'city', 'sort_order' => 2, 'is_active' => true],
                ],
            ],
        ],
        'tax_label' => 'Colorado Springs',
        'default_tax_rate' => '6.020',
        'taxable_labor' => false,
        'taxable_parts' => true,
        'taxable_shop_fees' => false,
        'shop_fee_enabled' => false,
    ]);
    $this->admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $this->actingAs($this->admin);
});

function taxSliceBRo(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Synthetic',
        'last_name' => 'TaxB',
        'phone' => '555-0174',
        'customer_type' => 'Retail',
        'city' => 'Denver',
        'state' => 'CO',
        'postal_code' => '80202',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Tax jurisdiction slice B',
    ]);
}

function taxSliceBPart(RepairOrder $ro, int $cents = 10000): void
{
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $ro->id,
        'summary' => 'Parts',
        'disposition' => RepairOrderConcernDisposition::Recommended,
    ]);
    RepairOrderLine::query()->create([
        'repair_order_id' => $ro->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Brake pads',
        'quantity' => '1.00',
        'unit_price_cents' => $cents,
        'subtotal_cents' => $cents,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'total_cents' => $cents,
    ]);
    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent(
        $ro->fresh(['lines', 'concerns', 'customer']),
    );
}

function taxSliceBMoney(int $cents): string
{
    return '$'.Money::ofMinor($cents, 'USD')->getAmount()->toScale(2)->__toString();
}

function taxSliceBApprove(RepairOrder $ro, string $approvedBy = 'Synthetic TaxB'): void
{
    $ro = $ro->fresh(['lines.concern', 'concerns']);
    if ($ro->status !== RepairOrderStatus::WaitingApproval) {
        $ro->update(['status' => RepairOrderStatus::WaitingApproval]);
    }

    test()->post(route('operations.repair-orders.authorization.store', $ro), [
        'source' => ApprovalSource::InPerson->value,
        'approved_by' => $approvedBy,
        RepairOrderConcurrency::FIELD => (int) $ro->estimate_version,
        RepairOrderAuthorizationStoreController::PRESENTED_CHANGES_FIELD => app(WorkCompletionAuthorization::class)
            ->pendingChangesFingerprint($ro),
    ])->assertRedirect();
}

function taxSliceBBindPdfRenderer(): void
{
    Storage::fake('local');

    app()->bind(PdfRenderer::class, function (): PdfRenderer {
        return new class implements PdfRenderer
        {
            public function renderEstimate(EstimateDocument $document): string
            {
                $path = 'estimate-documents/ro-'.$document->repair_order_id.'/doc-'.$document->id.'.pdf';
                Storage::disk('local')->put($path, "%PDF-1.4\nsynthetic");
                $document->forceFill([
                    'status' => $document->isInvoice() ? ($document->status ?: 'issued') : 'generated',
                    'pdf_path' => $path,
                    'generated_at' => now(),
                    'needs_pdf_refresh' => false,
                    'pdf_refreshed_at' => now(),
                ])->save();

                return $path;
            }
        };
    });
}

test('new ROs stamp TaxProfile schema 2 with frozen components', function () {
    $ro = taxSliceBRo();
    $stamp = $ro->fresh()->tax_profile;

    expect($stamp['schema'])->toBe(2)
        ->and($stamp['profile_key'])->toBe('springs')
        ->and($stamp['components'])->toHaveCount(2)
        ->and($stamp['rate'])->toStartWith('6.020')
        ->and($stamp['sourcing']['location_kind'])->toBe(TaxSourcingLocationKind::Shop->value);
});

test('schema 1 historical stamps remain readable and calculate', function () {
    $ro = taxSliceBRo();
    $ro->update([
        'tax_profile' => [
            'enabled' => true,
            'label' => 'Legacy',
            'rate' => '10.000',
            'taxable_labor' => false,
            'taxable_parts' => true,
            'taxable_shop_fees' => false,
            'schema' => 1,
        ],
    ]);
    taxSliceBPart($ro, 10000);

    $profile = TaxProfile::tryFromStored($ro->fresh()->tax_profile);
    expect($profile?->schema)->toBe(1)
        ->and((int) $ro->lines()->sum('tax_cents'))->toBe(1000);
});

test('matcher ranks city and zip without claiming exact jurisdiction', function () {
    $result = app(TaxJurisdictionMatcher::class)->suggest(ShopSettings::current(), [
        'state' => 'CO',
        'city' => 'Colorado Springs',
        'postal_code' => '80903',
    ]);

    expect($result['exact_claim'])->toBeFalse()
        ->and($result['candidates'][0]['key'])->toBe('springs')
        ->and($result['candidates'])->toHaveCount(2); // 80903 also on denver
});

test('shared ZIP with two cities stays ambiguous', function () {
    $result = app(TaxJurisdictionMatcher::class)->suggest(ShopSettings::current(), [
        'state' => 'CO',
        'postal_code' => '80903',
    ]);

    expect($result['ambiguous'])->toBeTrue()
        ->and(collect($result['candidates'])->pluck('key')->all())->toContain('springs', 'denver');
});

test('manual profile selection freezes schema 2 and recalculates living estimate', function () {
    $ro = taxSliceBRo();
    taxSliceBPart($ro, 10000);
    expect((int) $ro->lines()->sum('tax_cents'))->toBe(602);

    app(SelectTaxProfileOnRepairOrderAction::class)->execute(
        $ro,
        'denver',
        TaxSourcingLocationKind::ServiceAddress,
        $this->admin,
        ['city' => 'Denver', 'state' => 'CO', 'postal_code' => '80202', 'county' => 'Denver'],
    );

    $ro = $ro->fresh(['lines']);
    expect($ro->tax_profile['schema'])->toBe(2)
        ->and($ro->tax_profile['profile_key'])->toBe('denver')
        ->and($ro->tax_profile['sourcing']['selection_status'])->toBe('confirmed')
        ->and((int) $ro->lines()->sum('tax_cents'))->toBe(721);
});

test('catalog rate change does not reprice an already stamped RO', function () {
    $ro = taxSliceBRo();
    taxSliceBPart($ro, 10000);
    expect((int) $ro->lines()->sum('tax_cents'))->toBe(602);

    ShopSettings::current()->update([
        'default_tax_rate' => '9.999',
        'tax_profiles' => [
            [
                'key' => 'springs',
                'name' => 'Colorado Springs',
                'is_default' => true,
                'is_active' => true,
                'state' => 'CO',
                'cities' => ['Colorado Springs'],
                'zip_codes' => ['80903'],
                'taxable_labor' => false,
                'taxable_parts' => true,
                'taxable_shop_fees' => false,
                'components' => [
                    ['id' => 'st', 'name' => 'State', 'rate' => '9.999', 'jurisdiction_type' => 'state', 'sort_order' => 1, 'is_active' => true],
                ],
            ],
        ],
    ]);

    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent(
        $ro->fresh(['lines', 'concerns', 'customer']),
    );

    expect($ro->fresh()->tax_profile['rate'])->toStartWith('6.020')
        ->and((int) $ro->lines()->sum('tax_cents'))->toBe(602);
});

test('post-authorization tax increase does not raise approved collectible dollars until supplemental auth', function () {
    $ro = taxSliceBRo();
    $ro->update(['status' => RepairOrderStatus::WaitingApproval]);
    taxSliceBPart($ro, 10000);

    $payload = [
        'source' => ApprovalSource::InPerson->value,
        'approved_by' => 'Synthetic TaxB',
        RepairOrderConcurrency::FIELD => (int) $ro->fresh()->estimate_version,
        RepairOrderAuthorizationStoreController::PRESENTED_CHANGES_FIELD => app(WorkCompletionAuthorization::class)
            ->pendingChangesFingerprint($ro->fresh(['lines.concern', 'concerns'])),
    ];
    $this->post(route('operations.repair-orders.authorization.store', $ro), $payload)->assertRedirect();

    $calculator = app(EstimateTotalsCalculator::class);
    $approvedBefore = $calculator->approvedTotalsForRead($ro->fresh(['lines.concern', 'concerns']))->totalCents();

    app(SelectTaxProfileOnRepairOrderAction::class)->execute(
        $ro->fresh(),
        'denver',
        TaxSourcingLocationKind::Manual,
        $this->admin,
        ['city' => 'Denver', 'state' => 'CO', 'postal_code' => '80202'],
    );

    $ro = $ro->fresh(['lines.concern', 'concerns']);
    $approvedAfter = $calculator->approvedTotalsForRead($ro)->totalCents();
    $livingTax = (int) $ro->lines()->sum('tax_cents');

    expect($livingTax)->toBe(721)
        ->and($approvedAfter)->toBe($approvedBefore);
});

test('settings catalog persists counties associations', function () {
    $this->patch(route('operations.settings.shop.tax.update'), [
        'tax_enabled' => '1',
        'tax_document_presentation' => 'combined',
        'tax_profiles' => [[
            'key' => 'springs',
            'name' => 'Colorado Springs',
            'is_default' => '1',
            'is_active' => '1',
            'state' => 'CO',
            'counties' => 'El Paso, Teller',
            'cities' => 'Colorado Springs',
            'zip_codes' => '80903',
            'taxable_labor' => '0',
            'taxable_parts' => '1',
            'taxable_shop_fees' => '0',
            'components' => [[
                'id' => 'st',
                'name' => 'State',
                'rate' => '2.900',
                'jurisdiction_type' => 'state',
                'is_active' => '1',
            ]],
        ]],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $profile = ShopSettings::current()->fresh()->taxProfiles()[0];
    expect($profile['counties'])->toBe(['El Paso', 'Teller']);
});

test('estimate and invoice snapshots freeze tax labels from the RO stamp not live settings', function () {
    $ro = taxSliceBRo();
    taxSliceBPart($ro, 10000);

    ShopSettings::current()->update([
        'tax_label' => 'Live Catalog Label',
        'default_tax_rate' => '9.999',
        'taxable_parts' => false,
        'taxable_labor' => true,
    ]);

    $estimate = app(\App\Ark\Operations\Documents\EstimateSnapshotBuilder::class)->build($ro->fresh(['lines', 'concerns', 'customer', 'vehicle']), $this->admin);
    expect(data_get($estimate, 'settings.tax_label'))->toBe('Colorado Springs')
        ->and((string) data_get($estimate, 'settings.default_tax_rate'))->toStartWith('6.020')
        ->and(data_get($estimate, 'settings.taxable_parts'))->toBeTrue()
        ->and(data_get($estimate, 'totals.tax_label'))->toBe('Colorado Springs')
        ->and(data_get($estimate, 'tax_profile.profile_key'))->toBe('springs');

    $ro->update(['status' => RepairOrderStatus::WaitingApproval]);
    $payload = [
        'source' => ApprovalSource::InPerson->value,
        'approved_by' => 'Synthetic TaxB',
        RepairOrderConcurrency::FIELD => (int) $ro->fresh()->estimate_version,
        RepairOrderAuthorizationStoreController::PRESENTED_CHANGES_FIELD => app(WorkCompletionAuthorization::class)
            ->pendingChangesFingerprint($ro->fresh(['lines.concern', 'concerns'])),
    ];
    $this->post(route('operations.repair-orders.authorization.store', $ro), $payload)->assertRedirect();
    $ro->fresh()->update(['status' => RepairOrderStatus::ReadyPickup]);

    $invoice = app(\App\Ark\Operations\Financial\InvoiceSnapshotBuilder::class)->build(
        $ro->fresh(['lines.concern', 'concerns', 'customer', 'vehicle']),
        $this->admin,
    );
    expect(data_get($invoice, 'settings.tax_label'))->toBe('Colorado Springs')
        ->and((string) data_get($invoice, 'settings.default_tax_rate'))->toStartWith('6.020')
        ->and(data_get($invoice, 'tax_profile.profile_key'))->toBe('springs')
        ->and(data_get($invoice, 'totals.customer_tax_label'))->toContain('parts');
});

test('tax jurisdiction store requires affirmative confirmation', function () {
    $ro = taxSliceBRo();
    taxSliceBPart($ro, 10000);

    $this->from(route('operations.repair-orders.show', $ro))
        ->post(route('operations.repair-orders.tax-profile.store', $ro), [
            'tax_profile_key' => 'denver',
            'tax_sourcing_location_kind' => TaxSourcingLocationKind::Manual->value,
            'service_address_city' => 'Denver',
            'service_address_state' => 'CO',
            'service_address_postal_code' => '80202',
            RepairOrderConcurrency::FIELD => (int) $ro->fresh()->estimate_version,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('confirm_jurisdiction');

    expect($ro->fresh()->tax_profile['profile_key'])->toBe('springs');

    $this->post(route('operations.repair-orders.tax-profile.store', $ro), [
        'tax_profile_key' => 'denver',
        'tax_sourcing_location_kind' => TaxSourcingLocationKind::Manual->value,
        'service_address_city' => 'Denver',
        'service_address_state' => 'CO',
        'service_address_postal_code' => '80202',
        'confirm_jurisdiction' => '1',
        RepairOrderConcurrency::FIELD => (int) $ro->fresh()->estimate_version,
    ])->assertRedirect(route('operations.repair-orders.show', $ro));

    expect($ro->fresh()->tax_profile['profile_key'])->toBe('denver')
        ->and($ro->fresh()->tax_profile['sourcing']['selection_status'])->toBe('confirmed');
});

test('post-authorization tax increase requires supplemental auth before invoice can collect it', function () {
    $ro = taxSliceBRo();
    taxSliceBPart($ro, 10000);
    taxSliceBApprove($ro);

    $calculator = app(EstimateTotalsCalculator::class);
    $authorization = app(WorkCompletionAuthorization::class);
    $approvedBefore = $calculator->approvedTotalsForRead($ro->fresh(['lines.concern', 'concerns']))->totalCents();
    $taxBefore = (int) $ro->fresh()->lines()->sum('tax_cents');
    expect($taxBefore)->toBe(602);

    $this->post(route('operations.repair-orders.tax-profile.store', $ro), [
        'tax_profile_key' => 'denver',
        'tax_sourcing_location_kind' => TaxSourcingLocationKind::Manual->value,
        'service_address_city' => 'Denver',
        'service_address_state' => 'CO',
        'service_address_postal_code' => '80202',
        'confirm_jurisdiction' => '1',
        RepairOrderConcurrency::FIELD => (int) $ro->fresh()->estimate_version,
    ])->assertRedirect();

    $ro = $ro->fresh(['lines.concern', 'concerns', 'customer', 'vehicle']);
    $livingTax = (int) $ro->lines()->sum('tax_cents');
    $approvedHeld = $calculator->approvedTotalsForRead($ro)->totalCents();
    $pendingIncrease = $authorization->workOutsideApprovedSalesCents($ro);
    $part = $ro->lines->firstWhere('type', RepairOrderLineType::Part);

    expect($livingTax)->toBe(721)
        ->and($approvedHeld)->toBe($approvedBefore)
        ->and($pendingIncrease)->toBe(119)
        ->and($authorization->linesRequiringAuthorization($ro)->pluck('id')->all())->toContain($part->id);

    $this->get(route('operations.repair-orders.show', $ro))
        ->assertOk()
        ->assertSee('Needs authorization', false);

    $ro->update(['status' => RepairOrderStatus::ReadyPickup]);
    $invoiceBefore = app(GenerateInvoiceSnapshotAction::class)->execute(
        $ro->fresh(['lines.concern', 'concerns', 'customer', 'vehicle']),
    );
    expect((int) data_get($invoiceBefore->snapshot_json, 'totals.total_cents'))->toBe($approvedBefore)
        ->and((int) data_get($invoiceBefore->snapshot_json, 'totals.tax_cents'))->toBe($taxBefore);

    $invoiceBefore->delete();
    $ro->update(['status' => RepairOrderStatus::WaitingApproval]);

    taxSliceBApprove($ro, 'Synthetic TaxB Supplemental');
    $ro = $ro->fresh(['lines.concern', 'concerns', 'customer', 'vehicle']);
    $approvedAfter = $calculator->approvedTotalsForRead($ro)->totalCents();

    expect($authorization->linesRequiringAuthorization($ro))->toHaveCount(0)
        ->and($approvedAfter)->toBe($approvedBefore + 119)
        ->and($approvedAfter)->toBe((int) $ro->lines()->sum('total_cents'));

    $ro->update(['status' => RepairOrderStatus::ReadyPickup]);
    $invoiceAfter = app(GenerateInvoiceSnapshotAction::class)->execute(
        $ro->fresh(['lines.concern', 'concerns', 'customer', 'vehicle']),
    );
    expect((int) data_get($invoiceAfter->snapshot_json, 'totals.total_cents'))->toBe($approvedAfter)
        ->and((int) data_get($invoiceAfter->snapshot_json, 'totals.tax_cents'))->toBe(721);
});

test('pricing release e2e reconciles authoritative cents across builder estimate pdf email portal and invoice', function () {
    taxSliceBBindPdfRenderer();
    enableHostedPlatformMail();
    fakeHostedPlatformMail();

    ShopSettings::current()->update([
        'shop_fee_enabled' => true,
        'shop_fee_mode' => 'percent',
        'shop_fee_rate' => '10.000',
        'shop_fee_applies_to' => 'parts',
        'shop_fee_flat_cents' => null,
        'shop_fee_cap_cents' => null,
        'taxable_shop_fees' => false,
    ]);

    $ro = taxSliceBRo();
    $ro->vehicle->update(['vin' => '4S4BSAAC5K1234567']);
    $ro->customer->update(['email' => 'synthetic-taxb@example.test']);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $ro->id,
        'summary' => 'Brakes',
        'disposition' => RepairOrderConcernDisposition::Recommended,
    ]);
    RepairOrderLine::query()->create([
        'repair_order_id' => $ro->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Pad R&R',
        'quantity' => '1.00',
        'unit_price_cents' => 10000,
        'subtotal_cents' => 10000,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'total_cents' => 10000,
    ]);
    RepairOrderLine::query()->create([
        'repair_order_id' => $ro->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Pads',
        'quantity' => '1.00',
        'unit_price_cents' => 10000,
        'subtotal_cents' => 10000,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'total_cents' => 10000,
    ]);

    $calculator = app(EstimateTotalsCalculator::class);
    $calculator->ensureFinancialTotalsAreCurrent($ro->fresh(['lines', 'concerns', 'customer']));
    $ro = $ro->fresh(['lines', 'concerns', 'customer', 'vehicle']);

    $builder = $calculator->totalsFor($ro);
    $taxCents = $builder->taxCents();
    $feeCents = $builder->feesCents();
    $totalCents = $builder->totalCents();

    // Parts 10000 + 10% fee 1000; tax 6.020% on parts only = 602. Labor untaxed.
    expect($feeCents)->toBe(1000)
        ->and($taxCents)->toBe(602)
        ->and($totalCents)->toBe(10000 + 10000 + 1000 + 602);

    $estimateDoc = app(EstimateDocumentService::class)->createOrRefresh($ro, $this->admin);
    expect((int) data_get($estimateDoc->snapshot_json, 'totals.tax_cents'))->toBe($taxCents)
        ->and((int) data_get($estimateDoc->snapshot_json, 'totals.fees_cents'))->toBe($feeCents)
        ->and((int) data_get($estimateDoc->snapshot_json, 'totals.total_cents'))->toBe($totalCents)
        ->and(data_get($estimateDoc->snapshot_json, 'totals.tax'))->toBe(taxSliceBMoney($taxCents))
        ->and(data_get($estimateDoc->snapshot_json, 'totals.total'))->toBe(taxSliceBMoney($totalCents));

    $pdfSnapshot = app(EstimateDocumentPdfSnapshot::class)->resolve($estimateDoc->fresh(), $this->admin);
    expect((int) data_get($pdfSnapshot, 'totals.tax_cents'))->toBe($taxCents)
        ->and((int) data_get($pdfSnapshot, 'totals.total_cents'))->toBe($totalCents);

    app(EstimateDocumentService::class)->resolveForRepairOrder($ro->fresh(), $this->admin);

    $this->post(route('operations.repair-orders.estimate.email', $ro), [
        'message' => 'Synthetic pricing release review',
    ])->assertRedirect();

    Http::assertSent(function (Request $request) use ($totalCents): bool {
        if (! str_contains($request->url(), '/api/v1/services/mail/messages/transactional')) {
            return false;
        }
        $body = platformMailBody($request);

        return ($body['operation'] ?? null) === 'estimate.send'
            && ($body['variables']['estimate_total'] ?? null) === taxSliceBMoney($totalCents);
    });

    $plainToken = str_repeat('b', 64);
    EstimateAccessToken::createForPlainToken($ro, $plainToken, ['created_by_user_id' => $this->admin->id]);
    $this->get(route('portal.estimates.show', ['token' => $plainToken]))
        ->assertOk()
        ->assertSee(taxSliceBMoney($totalCents), false);

    ShopSettings::current()->update([
        'tax_label' => 'Live Catalog Drift',
        'default_tax_rate' => '9.999',
        'taxable_parts' => false,
        'taxable_labor' => true,
        'shop_fee_rate' => '25.000',
    ]);

    $estimateAfterDrift = app(EstimateDocumentService::class)->createOrRefresh($ro->fresh(['lines', 'concerns', 'customer', 'vehicle']), $this->admin);
    expect((int) data_get($estimateAfterDrift->snapshot_json, 'totals.tax_cents'))->toBe($taxCents)
        ->and((int) data_get($estimateAfterDrift->snapshot_json, 'totals.total_cents'))->toBe($totalCents)
        ->and(data_get($estimateAfterDrift->snapshot_json, 'settings.tax_label'))->toBe('Colorado Springs');

    taxSliceBApprove($ro);
    $ro = $ro->fresh(['lines.concern', 'concerns', 'customer', 'vehicle']);
    $ro->update(['status' => RepairOrderStatus::ReadyPickup]);

    $invoice = app(GenerateInvoiceSnapshotAction::class)->execute($ro);
    expect($invoice->document_type)->toBe(FinancialDocumentType::Invoice)
        ->and((int) data_get($invoice->snapshot_json, 'totals.tax_cents'))->toBe($taxCents)
        ->and((int) data_get($invoice->snapshot_json, 'totals.total_cents'))->toBe($totalCents)
        ->and(data_get($invoice->snapshot_json, 'totals.tax'))->toBe(taxSliceBMoney($taxCents))
        ->and(data_get($invoice->snapshot_json, 'settings.tax_label'))->toBe('Colorado Springs');

    $invoicePdf = app(EstimateDocumentPdfSnapshot::class)->resolve($invoice->fresh(), $this->admin);
    expect((int) data_get($invoicePdf, 'totals.tax_cents'))->toBe($taxCents)
        ->and((int) data_get($invoicePdf, 'totals.total_cents'))->toBe($totalCents);
});

test('sales tax penny reconciliation keeps stored cents equal to displayed snapshot cents', function () {
    $ro = taxSliceBRo();
    // Three lines that can produce fractional-cent half-up boundaries.
    foreach ([3333, 3333, 3334] as $i => $cents) {
        $concern = RepairOrderConcern::query()->create([
            'repair_order_id' => $ro->id,
            'summary' => 'Line '.$i,
            'disposition' => RepairOrderConcernDisposition::Recommended,
        ]);
        RepairOrderLine::query()->create([
            'repair_order_id' => $ro->id,
            'repair_order_concern_id' => $concern->id,
            'type' => RepairOrderLineType::Part,
            'description' => 'Part '.$i,
            'quantity' => '1.00',
            'unit_price_cents' => $cents,
            'subtotal_cents' => $cents,
            'tax_cents' => 0,
            'shop_fee_cents' => 0,
            'total_cents' => $cents,
        ]);
    }

    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent(
        $ro->fresh(['lines', 'concerns', 'customer']),
    );
    $ro = $ro->fresh(['lines', 'concerns', 'customer']);
    $taxCents = (int) $ro->lines()->sum('tax_cents');
    $totals = app(EstimateTotalsCalculator::class)->totalsFor($ro);

    expect($totals->taxCents())->toBe($taxCents);

    $snapshot = app(\App\Ark\Operations\Documents\EstimateSnapshotBuilder::class)
        ->build($ro, $this->admin);
    expect((int) data_get($snapshot, 'totals.tax_cents'))->toBe($taxCents);

    // Display path must format the stored cents — not recompute tax from dollars.
    $displayed = data_get($snapshot, 'totals.tax');
    $fromCents = '$'.number_format($taxCents / 100, 2);
    expect($displayed)->toBe($fromCents);
});
