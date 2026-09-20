<?php

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Approvals\ApprovalType;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\EstimateDocumentPdfSnapshot;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Documents\PdfRenderer;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderPaymentStatus;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Storage;

test('advisor can create a living estimate document snapshot from current repair order state', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');

    $this->app->bind(PdfRenderer::class, FakeEstimatePdfRenderer::class);
    $advisor = User::factory()->create(['name' => 'Molly Advisor'])->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    [$repairOrder, $concern] = repairOrderForEstimateDocumentSnapshot();
    ApprovalEvent::query()->create([
        'visit_id' => $repairOrder->id,
        'estimate_snapshot_reference' => null,
        'approval_type' => ApprovalType::Repair,
        'approved_amount_cents' => 26550,
        'source' => ApprovalSource::Phone,
        'approved_by' => 'Rosa Garcia',
        'approved_at' => now(),
        'notes' => 'Approved timing repair by phone.',
    ]);
    $totals = app(EstimateTotalsCalculator::class)->totalsFor($repairOrder->fresh()->load('lines'));

    $response = $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder));

    $document = EstimateDocument::query()->firstOrFail();
    $snapshot = $document->snapshot_json;

    $response->assertRedirect(route('operations.repair-orders.show', $repairOrder));

    expect($document->repair_order_id)->toBe($repairOrder->id)
        ->and($document->document_number)->toBe(1)
        ->and($document->status)->toBe('generated')
        ->and($document->pdf_path)->not->toBeNull()
        ->and($document->generated_at)->not->toBeNull()
        ->and($document->created_by)->toBe($advisor->id)
        ->and($snapshot['schema_version'])->toBe(2)
        ->and($snapshot['document_type'])->toBe('estimate')
        ->and($snapshot['generated_by']['name'])->toBe('Molly Advisor')
        ->and($snapshot['shop']['name'])->toBe('ARK Test Shop')
        ->and($snapshot['shop']['logo_path'])->toBe('shop-logos/test-logo.png')
        ->and($snapshot['shop']['logo_url'])->toContain('/storage/shop-logos/test-logo.png')
        ->and($snapshot['shop']['logo_data_uri'])->toStartWith('data:image/png;base64,')
        ->and($snapshot['settings']['estimate_disclaimer'])->toBe('Snapshot estimate disclaimer.')
        ->and($snapshot['settings']['recommendation_disclaimer'])->toBe('Snapshot recommendation disclaimer.')
        ->and($snapshot['settings']['tax_label'])->toBe('C/S Tax')
        ->and($snapshot['settings']['default_labor_rate_cents'])->toBe(ShopSettings::current()->default_labor_rate_cents)
        ->and($snapshot['settings']['parts_matrices'])->not->toBeEmpty()
        ->and($snapshot['repair_order']['repair_order_id'])->toBe($repairOrder->repair_order_id)
        ->and($snapshot['customer']['name'])->toBe('Rosa Garcia')
        ->and($snapshot['vehicle']['display_name'])->toBe('2018 Honda Accord EX')
        ->and($snapshot['intake']['concern_summary'])->toBe('Customer states engine rattles on cold start.')
        ->and($snapshot['concerns'][0]['id'])->toBe($concern->id)
        ->and($snapshot['concerns'][0]['summary'])->toBe('Cold start rattle')
        ->and($snapshot['concerns'][0]['customer_states'])->toBe('Engine rattles after sitting overnight.')
        ->and($snapshot['concerns'][0]['verified_findings'])->toBe('Rattle verified during first startup.')
        ->and($snapshot['concerns'][0]['dtcs_summary'])->toBe('No DTCs present.')
        ->and($snapshot['concerns'][0]['recommendation'])->toBe('Replace timing chain tensioner and retest cold.')
        ->and($snapshot['concerns'][0]['disposition'])->toBe(RepairOrderConcernDisposition::Approved->value)
        ->and($snapshot['concerns'][0]['lines'][0]['description'])->toBe('Diagnose cold start rattle')
        ->and($snapshot['staff']['approval_events'][0]['approval_type'])->toBe(ApprovalType::Repair->value)
        ->and($snapshot['staff']['approval_events'][0]['approved_amount_cents'])->toBe(26550)
        ->and($snapshot['staff']['approval_events'][0]['source'])->toBe(ApprovalSource::Phone->value)
        ->and($snapshot['staff']['approval_events'][0]['approved_by'])->toBe('Rosa Garcia')
        ->and($snapshot['staff']['approval_events'][0]['notes'])->toBe('Approved timing repair by phone.')
        ->and($snapshot['repair_order'])->not->toHaveKey('execution_posture')
        ->and($snapshot)->not->toHaveKey('general_lines')
        ->and($snapshot['totals']['labor_cents'])->toBe($totals->laborCents())
        ->and($snapshot['totals']['parts_cents'])->toBe($totals->partsCents())
        ->and($snapshot['totals']['fees_cents'])->toBe($totals->feesCents())
        ->and($snapshot['totals']['tax_cents'])->toBe($totals->taxCents())
        ->and($snapshot['totals']['total_cents'])->toBe($totals->totalCents());

    Storage::disk('local')->assertExists($document->pdf_path);
});

test('open estimate document preview refreshes from live repair order state on view', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');

    $this->app->bind(PdfRenderer::class, FakeEstimatePdfRenderer::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder, $concern] = repairOrderForEstimateDocumentSnapshot();

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder));
    $document = EstimateDocument::query()->firstOrFail();
    $originalSnapshot = $document->snapshot_json;

    $concern->update([
        'summary' => 'Edited live concern',
        'recommendation' => 'Edited live recommendation',
    ]);

    RepairOrderLine::query()->where('description', 'Diagnose cold start rattle')->firstOrFail()->update([
        'description' => 'Edited live diagnostic',
        'unit_price_cents' => 99999,
        'subtotal_cents' => 99999,
    ]);
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    $document->refresh();

    expect($document->snapshot_json)->toBe($originalSnapshot);

    $this->get(route('operations.repair-orders.estimate-documents.show', [$repairOrder, $document]))
        ->assertOk()
        ->assertSee('Edited live concern')
        ->assertSee('Edited live recommendation')
        ->assertSee('Edited live diagnostic')
        ->assertSee('$999.99')
        ->assertDontSee('Cold start rattle')
        ->assertDontSee('Diagnose cold start rattle');

    $document->refresh();

    expect($document->snapshot_json['concerns'][0]['summary'])->toBe('Edited live concern')
        ->and($document->snapshot_json['concerns'][0]['lines'][0]['description'])->toBe('Edited live diagnostic');
});

test('legacy open estimate route redirects to pdf', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');

    $this->app->bind(PdfRenderer::class, FakeEstimatePdfRenderer::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = repairOrderForEstimateDocumentSnapshot();

    $this->get(route('operations.repair-orders.estimate.show', $repairOrder))
        ->assertRedirect(route('operations.repair-orders.estimate.pdf', $repairOrder));
});

test('estimate pdf identity band shows customer address when on file', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = repairOrderForEstimateDocumentSnapshot();

    $repairOrder->customer->update([
        'address_line_1' => '3445 Chelton Loop N D',
        'city' => 'Colorado Springs',
        'state' => 'CO',
        'postal_code' => '80909',
    ]);

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder));

    $document = EstimateDocument::query()->firstOrFail();
    $snapshot = app(EstimateDocumentPdfSnapshot::class)->resolve($document->fresh());

    $identityHtml = view('operations.documents.estimates._operational-identity-band', [
        'snapshot' => $snapshot,
        'variant' => 'pdf',
    ])->render();

    expect($identityHtml)
        ->toContain('Address: 3445 Chelton Loop N D')
        ->toContain('Colorado Springs, CO 80909')
        ->not->toContain('Address: 3445 Chelton Loop N D · Colorado Springs, CO 80909');
});

test('estimate pdf identity band omits unassigned technician', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = repairOrderForEstimateDocumentSnapshot();

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder));

    $document = EstimateDocument::query()->firstOrFail();
    $snapshot = app(EstimateDocumentPdfSnapshot::class)->resolve($document->fresh());

    $identityHtml = view('operations.documents.estimates._operational-identity-band', [
        'snapshot' => $snapshot,
        'variant' => 'pdf',
    ])->render();

    expect($identityHtml)
        ->not->toContain('Technician:')
        ->not->toContain('Needs owner')
        ->not->toContain('Unassigned');
});

test('estimate pdf identity band shows repair order mileage in and out', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = repairOrderForEstimateDocumentSnapshot();

    $repairOrder->forceFill([
        'mileage_in' => 165604,
        'mileage_out' => 165892,
    ])->save();

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder));

    $document = EstimateDocument::query()->firstOrFail();
    $stored = $document->snapshot_json;
    unset($stored['vehicle']['mileage_in'], $stored['vehicle']['mileage_out'], $stored['vehicle']['mileage']);
    $document->forceFill(['snapshot_json' => $stored])->save();

    $snapshot = app(EstimateDocumentPdfSnapshot::class)->resolve($document->fresh());

    $identityHtml = view('operations.documents.estimates._operational-identity-band', [
        'snapshot' => $snapshot,
        'variant' => 'pdf',
    ])->render();

    expect($identityHtml)
        ->toContain('Mileage in: 165,604')
        ->toContain('Mileage out: 165,892')
        ->not->toContain('Mileage: 165,604 / 165,892');
});

test('view pdf route creates living estimate document without create pdf form', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');

    $this->app->bind(PdfRenderer::class, FakeEstimatePdfRenderer::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = repairOrderForEstimateDocumentSnapshot();

    expect(EstimateDocument::query()->count())->toBe(0);

    $pdfResponse = $this->get(route('operations.repair-orders.estimate.pdf', $repairOrder));

    $pdfResponse
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertSee('PDF for Cold start rattle', false);

    expect($pdfResponse->headers->get('Cache-Control'))->toContain('no-store')
        ->and($pdfResponse->headers->get('Cache-Control'))->toContain('no-cache');

    expect(EstimateDocument::query()->count())->toBe(1)
        ->and(EstimateDocument::query()->first()->needs_pdf_refresh)->toBeFalse();
});

test('open estimate pdf refreshes on view without manual regenerate', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');

    $this->app->bind(PdfRenderer::class, FakeEstimatePdfRenderer::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder, $concern] = repairOrderForEstimateDocumentSnapshot();

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder));
    $document = EstimateDocument::query()->firstOrFail();

    $concern->update(['summary' => 'PDF should follow live concern']);
    app(EstimateDocumentService::class)->markDirtyForRepairOrder($repairOrder->fresh());

    $this->get(route('operations.repair-orders.estimate-documents.pdf.show', [$repairOrder, $document]))
        ->assertOk()
        ->assertSee('PDF for PDF should follow live concern', false);

    expect($document->fresh()->needs_pdf_refresh)->toBeFalse();
});

test('shop settings changes refresh open estimate snapshots with current fees and labor authority', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');

    $this->app->bind(PdfRenderer::class, FakeEstimatePdfRenderer::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    [$repairOrder] = repairOrderForEstimateDocumentSnapshot();

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder));
    $document = EstimateDocument::query()->firstOrFail();

    expect($document->snapshot_json['settings']['default_tax_rate'])->toBe('8.250');

    $this->patch(route('operations.settings.shop.tax.update'), [
        'tax_enabled' => '1',
        'tax_label' => 'Sales Tax',
        'default_tax_rate' => '10.000',
        'taxable_labor' => '1',
        'taxable_parts' => '1',
    ])->assertRedirect();

    $document->refresh();

    expect($document->snapshot_json['settings']['default_tax_rate'])->toBe('10.000')
        ->and($document->snapshot_json['settings']['tax_label'])->toBe('Sales Tax')
        ->and($document->needs_pdf_refresh)->toBeTrue();
});

test('estimate pdf shows recommendation intent with customer facing label', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');

    $this->app->bind(PdfRenderer::class, FakeEstimatePdfRenderer::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = repairOrderForEstimateDocumentSnapshot();

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder));
    $document = EstimateDocument::query()->firstOrFail();

    $html = view('operations.documents.estimates.pdf', [
        'document' => $document,
        'snapshot' => $document->snapshot_json,
    ])->render();

    expect($html)
        ->toContain('concern--intent-immediate_attention')
        ->toContain('concern-priority-badge--immediate_attention')
        ->not->toContain('concern-intent-group');
});

test('estimate pdf header presents repair order as canonical identity', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');

    $this->app->bind(PdfRenderer::class, FakeEstimatePdfRenderer::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = repairOrderForEstimateDocumentSnapshot();

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder));
    $document = EstimateDocument::query()->firstOrFail();

    $html = view('operations.documents.estimates.pdf', [
        'document' => $document,
        'snapshot' => $document->snapshot_json,
    ])->render();

    expect($html)
        ->toContain('RO #'.$repairOrder->repair_order_id)
        ->toContain('Prepared:')
        ->toContain($document->snapshot_json['generated_at_display'])
        ->toContain('Customer')
        ->toContain('Vehicle')
        ->toContain('Visit')
        ->toContain('Estimate')
        ->toContain('Total')
        ->not->toContain('Presentation')
        ->not->toContain('Estimate for Repair Order')
        ->not->toContain('Estimate Document #1')
        ->not->toContain('Version 1')
        ->not->toContain('Estimate #1 / RO #'.$repairOrder->repair_order_id)
        ->not->toContain('Estimate #'.$repairOrder->id)
        ->not->toContain('Snapshot v1')
        ->not->toContain('Document Posture')
        ->not->toContain('Historical version generated from RO state at document creation.')
        ->not->toContain('Financial authority from stored estimate snapshot')
        ->not->toContain('Financial Authority');
});

test('estimate document routes are permission protected', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');

    $this->app->bind(PdfRenderer::class, FakeEstimatePdfRenderer::class);

    [$repairOrder] = repairOrderForEstimateDocumentSnapshot();
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $customer = User::factory()->create()->assignRole(ArkRole::Customer->value);

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.estimate-documents.store', $repairOrder))
        ->assertRedirect();

    $document = EstimateDocument::query()->firstOrFail();

    $this->actingAs($technician)
        ->get(route('operations.repair-orders.estimate-documents.show', [$repairOrder, $document]))
        ->assertOk()
        ->assertSee('Estimate')
        ->assertSee('Back to RO Review')
        ->assertDontSee('Edit Estimate');

    $this->actingAs($technician)
        ->get(route('operations.repair-orders.estimate.pdf', $repairOrder))
        ->assertOk();

    $this->actingAs($technician)
        ->post(route('operations.repair-orders.estimate-documents.store', $repairOrder))
        ->assertForbidden();

    $this->actingAs($customer)
        ->get(route('operations.repair-orders.estimate-documents.show', [$repairOrder, $document]))
        ->assertForbidden();
});

test('estimate document creation reuses one living row per repair order', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');

    $this->app->bind(PdfRenderer::class, FakeEstimatePdfRenderer::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$firstRepairOrder] = repairOrderForEstimateDocumentSnapshot();
    [$secondRepairOrder] = repairOrderForEstimateDocumentSnapshot('Fleet');

    $this->post(route('operations.repair-orders.estimate-documents.store', $firstRepairOrder));
    $this->post(route('operations.repair-orders.estimate-documents.store', $firstRepairOrder));
    $this->post(route('operations.repair-orders.estimate-documents.store', $secondRepairOrder));

    expect($firstRepairOrder->estimateDocuments()->pluck('document_number')->all())->toBe([1])
        ->and($secondRepairOrder->estimateDocuments()->pluck('document_number')->all())->toBe([1]);
});

test('estimate document creation automatically stores a pdf artifact from the snapshot', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');

    $this->app->bind(PdfRenderer::class, FakeEstimatePdfRenderer::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder, $concern] = repairOrderForEstimateDocumentSnapshot();

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder));
    $document = EstimateDocument::query()->firstOrFail();
    $snapshot = $document->snapshot_json;

    $concern->update(['summary' => 'Edited live concern before PDF']);

    expect($document->status)->toBe('generated')
        ->and($document->pdf_path)->not->toBeNull()
        ->and($document->needs_pdf_refresh)->toBeFalse()
        ->and($document->generated_at)->not->toBeNull()
        ->and($document->snapshot_json)->toBe($snapshot);

    Storage::disk('local')->assertExists($document->pdf_path);

    $pdfContents = Storage::disk('local')->get($document->pdf_path);

    expect($pdfContents)->toContain('PDF for Cold start rattle')
        ->and($pdfContents)->not->toContain('Edited live concern before PDF');
});

test('estimate document pdf can be viewed and downloaded by authorized staff', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');

    $this->app->bind(PdfRenderer::class, FakeEstimatePdfRenderer::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = repairOrderForEstimateDocumentSnapshot();

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder));
    $document = EstimateDocument::query()->firstOrFail();
    $document->refresh();

    $repairOrder->concerns()->firstOrFail()->update([
        'summary' => 'Fresh concern for opened PDF',
    ]);

    $this->get(route('operations.repair-orders.estimate-documents.pdf.show', [$repairOrder, $document]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="estimate-ro-'.$repairOrder->repair_order_id.'-current.pdf"')
        ->assertSee('PDF for Fresh concern for opened PDF', false)
        ->assertDontSee('PDF for Cold start rattle', false);

    $this->get(route('operations.repair-orders.estimate-documents.pdf.download', [$repairOrder, $document]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="estimate-ro-'.$repairOrder->repair_order_id.'-current.pdf"');
});

test('unauthorized users cannot generate or access estimate pdf artifacts', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');

    $this->app->bind(PdfRenderer::class, FakeEstimatePdfRenderer::class);

    [$repairOrder] = repairOrderForEstimateDocumentSnapshot();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $customer = User::factory()->create()->assignRole(ArkRole::Customer->value);

    $this->actingAs($advisor);
    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder));
    $document = EstimateDocument::query()->firstOrFail();
    $document->refresh();

    $this->actingAs($technician)
        ->get(route('operations.repair-orders.estimate-documents.pdf.show', [$repairOrder, $document]))
        ->assertOk();

    $this->actingAs($customer)
        ->get(route('operations.repair-orders.estimate-documents.pdf.show', [$repairOrder, $document]))
        ->assertForbidden();
});

test('estimate pdf generation failures mark the artifact safely without changing the snapshot', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $this->app->bind(PdfRenderer::class, FailingEstimatePdfRenderer::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = repairOrderForEstimateDocumentSnapshot();

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder))
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder))
        ->assertSessionHas('status');
    $document = EstimateDocument::query()->firstOrFail();
    $snapshot = $document->snapshot_json;

    $document->refresh();

    expect($document->status)->toBe('failed')
        ->and($document->pdf_path)->toBeNull()
        ->and($document->needs_pdf_refresh)->toBeTrue()
        ->and($document->generated_at)->toBeNull()
        ->and($document->snapshot_json)->toBe($snapshot);
});

test('estimate line changes mark document dirty then refresh when estimate is viewed', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');

    $this->app->bind(PdfRenderer::class, FakeEstimatePdfRenderer::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = repairOrderForEstimateDocumentSnapshot();

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder));
    $document = EstimateDocument::query()->firstOrFail();
    $originalSnapshot = $document->snapshot_json;
    $line = RepairOrderLine::query()->where('description', 'Diagnose cold start rattle')->firstOrFail();

    $this->patch(route('operations.repair-orders.lines.update', [$repairOrder, $line]), [
        'repair_order_concern_id' => $line->repair_order_concern_id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Updated diagnostic labor',
        'quantity' => '2.00',
        'unit_price' => '175.00',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    $document->refresh();

    expect($document->status)->toBe('draft')
        ->and($document->needs_pdf_refresh)->toBeTrue()
        ->and($document->snapshot_json)->not->toBe($originalSnapshot)
        ->and(collect($document->snapshot_json['concerns'][0]['lines'])->pluck('description')->all())->toContain('Updated diagnostic labor');

    $this->get(route('operations.repair-orders.estimate-documents.show', [$repairOrder, $document]))
        ->assertOk()
        ->assertSee('Updated diagnostic labor')
        ->assertDontSee('Diagnose cold start rattle');

    $viewedDocument = $document->fresh();

    expect($viewedDocument->needs_pdf_refresh)->toBeFalse()
        ->and($viewedDocument->snapshot_json)->not->toBe($originalSnapshot)
        ->and(collect($viewedDocument->snapshot_json['concerns'][0]['lines'])->pluck('description')->all())->toContain('Updated diagnostic labor');

    $this->get(route('operations.repair-orders.estimate-documents.pdf.show', [$repairOrder, $document]))
        ->assertOk()
        ->assertSee('PDF for Cold start rattle', false);

    expect($document->fresh()->needs_pdf_refresh)->toBeFalse();
});

test('concern narrative disposition and order changes mark existing estimate document dirty', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');

    $this->app->bind(PdfRenderer::class, FakeEstimatePdfRenderer::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder, $concern] = repairOrderForEstimateDocumentSnapshot();

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder));
    $narrativeDocument = EstimateDocument::query()->firstOrFail();

    $this->patch(route('operations.repair-orders.concerns.update', [$repairOrder, $concern]), [
        'summary' => 'Cold start rattle updated',
        'notes' => 'Updated notes.',
        'customer_states' => 'Updated customer state.',
        'verified_findings' => 'Updated finding.',
        'dtcs_summary' => null,
        'recommendation' => 'Updated recommendation.',
        'recommendation_intent' => 'immediate_attention',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    expect($narrativeDocument->fresh()->needs_pdf_refresh)->toBeTrue();

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder));
    $dispositionDocument = EstimateDocument::query()->firstOrFail();

    $this->patch(route('operations.repair-orders.concerns.disposition', [$repairOrder, $concern]), [
        'disposition' => RepairOrderConcernDisposition::Deferred->value,
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#concern-'.$concern->id);

    expect($dispositionDocument->fresh()->needs_pdf_refresh)->toBeTrue();

    $secondConcern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Oil leak',
        'recommendation_intent' => 'maintenance',
        'position' => 2,
    ]);

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder));
    $orderDocument = EstimateDocument::query()->firstOrFail();

    $this->patch(route('operations.repair-orders.concerns.move', [$repairOrder, $secondConcern]), [
        'direction' => 'up',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    expect($orderDocument->fresh()->needs_pdf_refresh)->toBeTrue();
});

test('customer and vehicle identity changes mark estimate documents dirty for related repair orders', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');

    $this->app->bind(PdfRenderer::class, FakeEstimatePdfRenderer::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = repairOrderForEstimateDocumentSnapshot();
    $repairOrder->load(['customer', 'vehicle']);

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder));
    $customerDocument = EstimateDocument::query()->firstOrFail();

    $this->patch(route('operations.customers.update', $repairOrder->customer), [
        'first_name' => 'Rosa',
        'last_name' => 'Garcia-Smith',
        'phone' => '555-0100',
        'email' => 'rosa@example.test',
        'customer_type' => 'Retail',
        'notes' => null,
    ])->assertRedirect(route('operations.customers.show', $repairOrder->customer));

    expect($customerDocument->fresh()->needs_pdf_refresh)->toBeTrue();

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder));
    $vehicleDocument = EstimateDocument::query()->firstOrFail();

    $this->patch(route('operations.customers.vehicles.update', [$repairOrder->customer, $repairOrder->vehicle]), [
        'vin' => '1HGCM82633A004352',
        'plate' => 'ARK999',
        'plate_state' => 'TX',
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Accord',
        'trim' => 'EX',
        'engine' => null,
        'transmission' => null,
        'drive' => null,
        'color' => null,
        'nickname' => null,
        'public_notes' => null,
        'private_notes' => null,
    ])->assertRedirect(route('operations.customers.show', $repairOrder->customer));

    expect($vehicleDocument->fresh()->needs_pdf_refresh)->toBeTrue();
});

test('refreshing current estimate reuses row and updates snapshot after dirty changes', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');

    $this->app->bind(PdfRenderer::class, FakeEstimatePdfRenderer::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = repairOrderForEstimateDocumentSnapshot();

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder));
    $document = EstimateDocument::query()->firstOrFail();
    $originalDocumentId = $document->id;
    $originalPdfPath = $document->pdf_path;

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $repairOrder->concerns()->firstOrFail()->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Follow-up diagnostic',
        'quantity' => '1.00',
        'unit_price' => '95.00',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    expect($document->fresh()->needs_pdf_refresh)->toBeTrue();

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder));

    $refreshedDocument = EstimateDocument::query()->firstOrFail();

    expect($refreshedDocument->id)->toBe($originalDocumentId)
        ->and($refreshedDocument->status)->toBe('generated')
        ->and($refreshedDocument->needs_pdf_refresh)->toBeFalse()
        ->and($refreshedDocument->document_number)->toBe(1)
        ->and($refreshedDocument->pdf_path)->toBe($originalPdfPath)
        ->and($refreshedDocument->snapshot_json)->not->toHaveKey('general_lines')
        ->and(collect($refreshedDocument->snapshot_json['concerns'][0]['lines'])->pluck('description')->all())->toContain('Follow-up diagnostic');

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Estimate PDF')
        ->assertSee(route('operations.repair-orders.estimate.pdf', $repairOrder))
        ->assertDontSee('Open Estimate')
        ->assertDontSee('Create PDF')
        ->assertDontSee('Estimate changed since last version');
});

test('terminal repair order statuses snapshot a final pdf that no longer auto refreshes', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');

    $this->app->bind(PdfRenderer::class, FakeEstimatePdfRenderer::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = repairOrderForEstimateDocumentSnapshot();

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder));
    $document = EstimateDocument::query()->firstOrFail();

    $repairOrder->concerns()->firstOrFail()->update([
        'summary' => 'Final approved concern',
        'disposition' => RepairOrderConcernDisposition::Approved,
    ]);
    $document->refresh();

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());
    $repairOrder->forceFill(['status' => RepairOrderStatus::ReadyPickup])->save();
    issueFinalInvoiceFor($repairOrder->fresh());
    payRepairOrderInFull($repairOrder->fresh());

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::Closed->value,
    ])->assertRedirect();

    $closedDocument = $document->fresh();
    $finalPdfContents = Storage::disk('local')->get($closedDocument->pdf_path);

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Closed))->toBeTrue()
        ->and($closedDocument->needs_pdf_refresh)->toBeFalse()
        ->and($closedDocument->snapshot_json['repair_order']['status'])->toBe(RepairOrderStatus::Closed->value)
        ->and($closedDocument->snapshot_json['repair_order']['payment_status'])->toBe(RepairOrderPaymentStatus::Paid->value)
        ->and($closedDocument->snapshot_json['repair_order']['payment_status_label'])->toBe('Paid')
        ->and($closedDocument->snapshot_json['repair_order']['pickup_handoff_label'])->toBe('Delivered / archived')
        ->and($closedDocument->snapshot_json['concerns'][0]['summary'])->toBe('Final approved concern')
        ->and($finalPdfContents)->toContain('PDF for Final approved concern');

    $repairOrder->concerns()->firstOrFail()->update([
        'summary' => 'Changed after close should not appear',
    ]);
    app(EstimateDocumentService::class)->markDirtyForRepairOrder($repairOrder->fresh());

    $this->get(route('operations.repair-orders.estimate-documents.pdf.show', [$repairOrder, $closedDocument]))
        ->assertOk()
        ->assertSee('PDF for Final approved concern', false)
        ->assertDontSee('Changed after close should not appear', false);

    expect($closedDocument->fresh()->needs_pdf_refresh)->toBeFalse()
        ->and($closedDocument->fresh()->snapshot_json['concerns'][0]['summary'])->toBe('Final approved concern')
        ->and(Storage::disk('local')->exists($closedDocument->pdf_path))->toBeTrue();
});

test('closed deferred estimate pdf refreshes when approval presentation drifted', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');

    $this->app->bind(PdfRenderer::class, FakeEstimatePdfRenderer::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder, $concern] = repairOrderForEstimateDocumentSnapshot();

    $concern->update(['disposition' => RepairOrderConcernDisposition::Deferred]);

    ApprovalEvent::query()->create([
        'visit_id' => $repairOrder->id,
        'approval_type' => ApprovalType::Repair,
        'approved_amount_cents' => 99118,
        'source' => ApprovalSource::Portal,
        'approved_by' => 'Emirhan Cadas',
        'approved_at' => now(),
    ]);

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder));
    $document = EstimateDocument::query()->firstOrFail();

    $repairOrder->forceFill(['status' => RepairOrderStatus::Closed])->save();

    $snapshot = $document->snapshot_json;
    unset($snapshot['_pdf_approval_presentation']);
    $document->forceFill([
        'snapshot_json' => $snapshot,
        'pdf_path' => 'estimate-documents/ro-'.$repairOrder->id.'/stale-approved-estimate.pdf',
        'status' => 'generated',
        'needs_pdf_refresh' => false,
    ])->save();

    Storage::disk('local')->put($document->pdf_path, 'PDF for stale approved footer');

    $this->get(route('operations.repair-orders.estimate.pdf', $repairOrder))
        ->assertOk()
        ->assertSee('PDF for Cold start rattle', false)
        ->assertDontSee('PDF for stale approved footer', false);

    expect($document->fresh()->snapshot_json['_pdf_approval_presentation'])
        ->toBe('deferred|Deferred|Deferred for follow-up');
});

test('closed declined estimate pdf refreshes when approval presentation drifted', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');

    $this->app->bind(PdfRenderer::class, FakeEstimatePdfRenderer::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder, $concern] = repairOrderForEstimateDocumentSnapshot();

    $concern->update(['disposition' => RepairOrderConcernDisposition::Declined]);

    ApprovalEvent::query()->create([
        'visit_id' => $repairOrder->id,
        'approval_type' => ApprovalType::Repair,
        'approved_amount_cents' => 99118,
        'source' => ApprovalSource::Portal,
        'approved_by' => 'Emirhan Cadas',
        'approved_at' => now(),
    ]);

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder));
    $document = EstimateDocument::query()->firstOrFail();

    $repairOrder->forceFill(['status' => RepairOrderStatus::Closed])->save();

    $snapshot = $document->snapshot_json;
    unset($snapshot['_pdf_approval_presentation']);
    $document->forceFill([
        'snapshot_json' => $snapshot,
        'pdf_path' => 'estimate-documents/ro-'.$repairOrder->id.'/stale-approved-estimate.pdf',
        'status' => 'generated',
        'needs_pdf_refresh' => false,
    ])->save();

    Storage::disk('local')->put($document->pdf_path, 'PDF for stale approved footer');

    $this->get(route('operations.repair-orders.estimate.pdf', $repairOrder))
        ->assertOk()
        ->assertSee('PDF for Cold start rattle', false)
        ->assertDontSee('PDF for stale approved footer', false);

    expect($document->fresh()->snapshot_json['_pdf_approval_presentation'])
        ->toBe('declined|Declined|Declined');
});

test('open migrated estimate with legacy invoice lineage still refreshes living pdf', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');

    $this->app->bind(PdfRenderer::class, FakeEstimatePdfRenderer::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = repairOrderForEstimateDocumentSnapshot();

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder));
    $document = EstimateDocument::query()->firstOrFail();
    $document->forceFill(['legacy_arksms_invoice_id' => 380])->save();

    $repairOrder->concerns()->firstOrFail()->update([
        'summary' => 'Overheating',
    ]);
    app(EstimateDocumentService::class)->markDirtyForRepairOrder($repairOrder->fresh());

    expect($document->fresh()->needs_pdf_refresh)->toBeTrue()
        ->and($document->fresh()->snapshot_json['concerns'][0]['summary'])->toBe('Overheating');

    $this->get(route('operations.repair-orders.estimate.pdf', $repairOrder))
        ->assertOk()
        ->assertSee('PDF for Overheating', false);

    expect($document->fresh()->needs_pdf_refresh)->toBeFalse()
        ->and($document->fresh()->snapshot_json['concerns'][0]['summary'])->toBe('Overheating');
});

test('customer estimate snapshot presents military standing discount label', function (): void {
    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'customer_types' => ShopSettings::DEFAULT_CUSTOMER_TYPES,
    ]);

    [$repairOrder] = repairOrderForEstimateDocumentSnapshot('Military');
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder(
        $repairOrder->fresh(['customer', 'concerns', 'lines']),
    );

    $built = app(\App\Ark\Operations\Documents\EstimateSnapshotBuilder::class)->build(
        $repairOrder->fresh(['customer', 'concerns', 'lines']),
    );

    expect(data_get($built, 'totals.standing_discount_cents'))->toBeGreaterThan(0)
        ->and(data_get($built, 'totals.standing_discount_label'))->toBe('Military Discount');

    $presented = app(\App\Ark\Operations\Documents\DocumentPdfPresenter::class)->prepareForCustomer($built);

    expect(data_get($presented, 'totals.standing_discount_label'))->toBe('Military Discount');

    $html = view('operations.documents.partials._document-footer', [
        'snapshot' => app(\App\Ark\Operations\Documents\DocumentPdfPresenter::class)->prepare($built),
        'variant' => 'browser',
    ])->render();

    expect($html)->toContain('Military Discount');
});

function repairOrderForEstimateDocumentSnapshot(string $customerType = 'Retail'): array
{
    Storage::fake('public');
    Storage::disk('public')->put('shop-logos/test-logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGOSHzRgAAAAABJRU5ErkJggg=='));

    ShopSettings::current()->update([
        'shop_name' => 'ARK Test Shop',
        'phone' => '555-0199',
        'logo_path' => 'shop-logos/test-logo.png',
        'estimate_disclaimer' => 'Snapshot estimate disclaimer.',
        'recommendation_disclaimer' => 'Snapshot recommendation disclaimer.',
        'tax_enabled' => true,
        'tax_label' => 'C/S Tax',
        'default_tax_rate' => '8.250',
        'taxable_labor' => false,
        'taxable_parts' => true,
        'shop_fee_enabled' => false,
    ]);

    $customer = Customer::query()->create([
        'first_name' => 'Rosa',
        'last_name' => 'Garcia',
        'phone' => '555-0100',
        'email' => 'rosa@example.test',
        'customer_type' => $customerType,
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'vin' => '1HGCM82633A004352',
        'plate' => 'ARK123',
        'plate_state' => 'TX',
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Accord',
        'trim' => 'EX',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Customer states engine rattles on cold start.',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Cold start rattle',
        'notes' => 'Most noticeable after sitting overnight.',
        'customer_states' => 'Engine rattles after sitting overnight.',
        'verified_findings' => 'Rattle verified during first startup.',
        'dtcs_summary' => 'No DTCs present.',
        'recommendation' => 'Replace timing chain tensioner and retest cold.',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'recommendation_intent' => 'immediate_attention',
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Diagnose cold start rattle',
        'quantity' => '1.50',
        'unit_price_cents' => 15000,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Timing chain tensioner',
        'quantity' => '1.00',
        'unit_price_cents' => 8550,
        'part_cost_cents' => 4200,
        'pricing_mode' => 'manual',
        'vendor_name' => 'Local Parts Counter',
        'part_number' => 'TCT-123',
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder);

    return [$repairOrder->fresh(), $concern->fresh()];
}

class FakeEstimatePdfRenderer implements PdfRenderer
{
    public function renderEstimate(EstimateDocument $document): string
    {
        $path = sprintf(
            'estimate-documents/ro-%d/%s',
            $document->repair_order_id,
            $document->isInvoice() ? 'current-invoice.pdf' : 'current-estimate.pdf',
        );

        $summary = data_get($document->snapshot_json, 'concerns.0.summary', 'document');

        Storage::disk('local')->put($path, 'PDF for '.$summary);

        $document->forceFill([
            'status' => 'generated',
            'pdf_path' => $path,
            'generated_at' => now(),
            'needs_pdf_refresh' => false,
            'pdf_refreshed_at' => now(),
        ])->save();

        return $path;
    }
}

class FailingEstimatePdfRenderer implements PdfRenderer
{
    public function renderEstimate(EstimateDocument $document): string
    {
        $document->forceFill([
            'status' => 'failed',
        ])->save();

        throw new RuntimeException('Chromium unavailable.');
    }
}
