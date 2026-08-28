<?php

use App\Ark\Operations\Documents\PdfRenderer;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Financial\FinancialPositionProjection;
use App\Ark\Operations\Financial\GenerateInvoiceSnapshotAction;
use App\Ark\Operations\Financial\InvoiceSnapshotBuilder;
use App\Ark\Operations\Financial\InvoiceStatus;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Operations\Financial\RefreshLivingInvoiceSnapshotAction;
use App\Ark\Operations\Financial\RepairOrderCloseoutAuthority;
use App\Ark\Operations\Financial\VoidLedgerEntryAction;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairActionOwnerType;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderPaymentStatus;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroup;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Storage;

test('final invoice snapshot includes vehicle identity and technician', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update(['shop_fee_enabled' => false, 'tax_enabled' => false]);

    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->vehicle->forceFill([
        'plate' => 'TEST01',
        'plate_state' => 'CO',
        'color' => 'Silver',
    ])->save();

    $technician = User::factory()->create(['name' => 'Bay Tech'])->assignRole(ArkRole::Technician->value);

    // Invoice technician attribution comes from Repair Action owners — not assigned_technician_id.
    $concern = $repairOrder->concerns()->firstOrFail();
    $workGroup = RepairOrderWorkGroup::query()->create([
        'repair_order_concern_id' => $concern->id,
        'title' => 'Brake service',
        'position' => 1,
        'owner_type' => RepairActionOwnerType::Technician,
        'owner_user_id' => $technician->id,
    ]);
    $repairOrder->lines()->update(['repair_order_work_group_id' => $workGroup->id]);

    $invoice = app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder->fresh());

    expect($invoice->snapshot_json['vehicle']['plate'] ?? null)->toBe('TEST01')
        ->and($invoice->snapshot_json['staff']['execution']['technician_name'] ?? null)->toBe('Bay Tech')
        ->and($invoice->snapshot_json['repair_order']['assigned_technician_name'] ?? null)->toBe('Bay Tech');
});

test('final invoice snapshot includes approved work only', function () {
    ShopSettings::current()->update(['shop_fee_enabled' => false, 'tax_enabled' => false]);

    $repairOrder = financialCloseoutRepairOrder();

    $recommended = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Recommended future work',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'recommendation_intent' => 'maintenance',
        'position' => 2,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $recommended->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Future service',
        'quantity' => '1.00',
        'unit_price_cents' => 9900,
    ]);

    $invoice = app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder->fresh());

    expect($invoice->document_type)->toBe(FinancialDocumentType::Invoice)
        ->and($invoice->status)->toBe(InvoiceStatus::Issued->value)
        ->and($invoice->snapshot_json['document_type'])->toBe('invoice')
        ->and($invoice->snapshot_json['totals']['total_cents'])->toBe(15000)
        ->and($invoice->snapshot_json['concerns'])->toHaveCount(1)
        ->and($invoice->snapshot_json['concerns'][0]['disposition'])->toBe('approved');
});

test('customer documents omit deferred work continuity block', function () {
    $html = view('operations.documents.pdf.document', [
        'document' => (object) [],
        'snapshot' => [
            'document_type' => 'estimate',
            'repair_order' => [
                'repair_order_id' => 42,
                'future_work_count' => 1,
                'future_work_summary' => '1 Deferred',
                'future_work_subtotal' => '$257.50',
            ],
            'concerns' => [],
            'totals' => [
                'labor_cents' => 0,
                'parts_cents' => 0,
                'fees_cents' => 0,
                'tax_cents' => 0,
                'total_cents' => 0,
            ],
            'settings' => [],
            'shop' => ['name' => 'Test Shop'],
            'customer' => ['name' => 'Test Customer'],
            'vehicle' => ['display_name' => '2020 Test Car'],
            'intake' => ['concern_summary' => 'Test concern'],
        ],
    ])->render();

    expect($html)
        ->not->toContain('Future Work Retained')
        ->not->toContain('Deferred / Declined Value')
        ->not->toContain('vehicle continuity');
});

test('issued final invoice snapshot is immutable', function () {
    $repairOrder = financialCloseoutRepairOrder();
    $invoice = app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder);

    expect(fn () => $invoice->forceFill(['snapshot_json' => ['tampered' => true]])->save())
        ->toThrow(RuntimeException::class);
});

test('issued invoice snapshot stays frozen when estimate changes after partial payment', function () {
    $calculator = app(EstimateTotalsCalculator::class);
    $repairOrder = financialCloseoutRepairOrder();

    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => true,
        'shop_fee_rate' => '10.000',
        'shop_fee_cap_cents' => null,
    ]);

    $calculator->recalculateRepairOrder($repairOrder->fresh(['customer', 'concerns', 'lines.concern']));

    $withFees = $calculator->totalsForApprovedWork($repairOrder->fresh(['lines.concern']));
    expect($withFees->feesCents())->toBe(1500)
        ->and($withFees->totalCents())->toBe(16500);

    issueFinalInvoiceFor($repairOrder->fresh());

    $invoiceWithFees = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh());
    expect($invoiceWithFees->invoiceTotalCents)->toBe(16500);

    app(RecordLedgerEntryAction::class)->recordPayment(
        $repairOrder->fresh(),
        5000,
        PaymentMethod::Card,
    );

    $repairOrder->concerns()->first()->update(['billing_posture' => ConcernBillingPosture::Warranty]);
    $calculator->recalculateRepairOrder($repairOrder->fresh(['customer', 'concerns', 'lines.concern']));

    $approvedTotal = $calculator->totalsForApprovedWork($repairOrder->fresh(['lines.concern']))->totalCents();
    $balance = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh());

    expect($approvedTotal)->toBe(15000)
        ->and($balance->invoiceTotalCents)->toBe(16500)
        ->and($balance->invoiceTotalCents)->not->toBe($approvedTotal)
        ->and($balance->paymentsAppliedCents)->toBe(5000)
        ->and($balance->balanceDueCents)->toBe(11500)
        ->and($balance->invoiceStatus)->toBe(InvoiceStatus::PartiallyPaid);
});

test('balance due calculator applies deposits and partial payments', function () {
    $repairOrder = financialCloseoutRepairOrder();
    $ledger = app(RecordLedgerEntryAction::class);

    $ledger->recordDeposit($repairOrder, 4000, PaymentMethod::Cash);
    issueFinalInvoiceFor($repairOrder);

    $balance = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh());

    expect($balance->invoiceTotalCents)->toBe(15000)
        ->and($balance->depositsAppliedCents)->toBe(4000)
        ->and($balance->balanceDueCents)->toBe(11000)
        ->and($balance->invoiceStatus)->toBe(InvoiceStatus::PartiallyPaid);

    $ledger->recordPayment($repairOrder->fresh(), 5000, PaymentMethod::Card);

    $partial = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh());

    expect($partial->paymentsAppliedCents)->toBe(5000)
        ->and($partial->balanceDueCents)->toBe(6000)
        ->and($partial->invoiceStatus)->toBe(InvoiceStatus::PartiallyPaid);

    payRepairOrderInFull($repairOrder->fresh());

    $paid = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh());

    expect($paid->balanceDueCents)->toBe(0)
        ->and($paid->invoiceStatus)->toBe(InvoiceStatus::Paid)
        ->and($paid->isPaid())->toBeTrue()
        ->and($repairOrder->fresh()->payment_status)->toBe(RepairOrderPaymentStatus::Paid);
});

test('record payment syncs repair order payment posture from calculator', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    expect($repairOrder->fresh()->payment_status)->toBe(RepairOrderPaymentStatus::Unpaid);

    app(RecordLedgerEntryAction::class)->recordPayment(
        $repairOrder->fresh(),
        5000,
        PaymentMethod::Card,
    );

    expect($repairOrder->fresh()->payment_status)->toBe(RepairOrderPaymentStatus::Unpaid);

    payRepairOrderInFull($repairOrder->fresh());

    expect($repairOrder->fresh()->payment_status)->toBe(RepairOrderPaymentStatus::Paid)
        ->and($repairOrder->fresh()->pickupHandoffLabel())->toBe('Ready to release');
});

test('cash overpayment records balance due only and assumes change given', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $entries = app(RecordLedgerEntryAction::class)->recordPayment(
        $repairOrder->fresh(),
        17000,
        PaymentMethod::Cash,
    );

    $balance = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh());

    expect($balance->balanceDueCents)->toBe(0)
        ->and($balance->invoiceStatus)->toBe(InvoiceStatus::Paid)
        ->and($repairOrder->customer->fresh()->store_credit_balance_cents)->toBe(0)
        ->and($entries)->toHaveCount(1)
        ->and($entries[0]->entry_type)->toBe(LedgerEntryType::Payment)
        ->and($entries[0]->amount_cents)->toBe(15000);
});

test('non-cash overpayment still creates customer store credit', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    app(RecordLedgerEntryAction::class)->recordPayment(
        $repairOrder->fresh(),
        17000,
        PaymentMethod::Card,
    );

    $balance = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh());

    expect($balance->balanceDueCents)->toBe(0)
        ->and($balance->invoiceStatus)->toBe(InvoiceStatus::Paid)
        ->and($repairOrder->customer->fresh()->store_credit_balance_cents)->toBe(2000);
});

test('closeout requires issued invoice and zero balance due', function () {
    $repairOrder = financialCloseoutRepairOrder();
    $closeout = app(RepairOrderCloseoutAuthority::class);

    expect($closeout->blockingReason($repairOrder))->toContain('final invoice');

    issueFinalInvoiceFor($repairOrder);

    expect($closeout->blockingReason($repairOrder->fresh()))->toContain('balance due');

    payRepairOrderInFull($repairOrder->fresh());

    expect($closeout->canClose($repairOrder->fresh()))->toBeTrue();
});

test('zero balance courtesy work can issue final invoice and close without payment', function () {
    $repairOrder = zeroBalanceCourtesyRepairOrder();
    $calculator = app(EstimateTotalsCalculator::class);
    $closeout = app(RepairOrderCloseoutAuthority::class);

    expect($calculator->totalsForApprovedWork($repairOrder)->totalCents())->toBe(0)
        ->and($calculator->hasApprovedInvoiceableWork($repairOrder))->toBeTrue()
        ->and($closeout->blockingReason($repairOrder))->toContain('final invoice');

    issueFinalInvoiceFor($repairOrder);

    $balance = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh());

    expect($balance->invoiceTotalCents)->toBe(0)
        ->and($balance->balanceDueCents)->toBe(0)
        ->and($balance->isPaid())->toBeTrue()
        ->and($balance->invoiceStatus)->toBe(InvoiceStatus::Paid)
        ->and($closeout->canClose($repairOrder->fresh()))->toBeTrue()
        ->and($repairOrder->fresh()->isPaid())->toBeTrue();
});

test('final invoice cannot issue when approved concerns have only note lines', function () {
    ShopSettings::current()->update(['shop_fee_enabled' => false, 'tax_enabled' => false]);
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->lines()->delete();

    $concern = $repairOrder->concerns()->first();
    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Note,
        'description' => 'Internal only',
        'quantity' => '1.00',
        'unit_price_cents' => 0,
    ]);

    expect(app(EstimateTotalsCalculator::class)->hasApprovedInvoiceableWork($repairOrder->fresh()))->toBeFalse()
        ->and(fn () => issueFinalInvoiceFor($repairOrder->fresh()))
        ->toThrow(RuntimeException::class, 'Approved work is required');
});

test('voiding a payment restores balance due', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    payRepairOrderInFull($repairOrder);

    $entry = $repairOrder->ledgerEntries()->where('entry_type', LedgerEntryType::Payment)->first();

    app(VoidLedgerEntryAction::class)->execute($entry);

    $balance = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh());

    expect($balance->balanceDueCents)->toBe(15000)
        ->and($balance->invoiceStatus)->toBe(InvoiceStatus::Issued);
});

test('payment before final invoice is rejected', function () {
    $repairOrder = financialCloseoutRepairOrder();

    expect(fn () => app(RecordLedgerEntryAction::class)->recordPayment(
        $repairOrder,
        1000,
        PaymentMethod::Cash,
    ))->toThrow(RuntimeException::class, 'final invoice');
});

test('invoice pdf generation preserves invoice payment status', function () {
    Storage::fake('local');

    $this->app->bind(PdfRenderer::class, FakeFinancialPdfRenderer::class);

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $invoice = app(BalanceDueCalculator::class)->issuedInvoice($repairOrder->fresh());
    payRepairOrderInFull($repairOrder->fresh());

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value);

    app(\App\Ark\Operations\Documents\EstimateDocumentService::class)->generatePdf($invoice->fresh());

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value)
        ->and($invoice->fresh()->pdf_path)->toEndWith('current-invoice.pdf');
});

test('invoice pdf generation uses a separate file from the living estimate pdf', function () {
    Storage::fake('local');

    $this->app->bind(PdfRenderer::class, FakeFinancialPdfRenderer::class);

    $repairOrder = financialCloseoutRepairOrder();
    $documents = app(\App\Ark\Operations\Documents\EstimateDocumentService::class);

    $estimate = $documents->createOrRefresh($repairOrder);
    $documents->generatePdf($estimate);

    issueFinalInvoiceFor($repairOrder->fresh());

    $invoice = app(BalanceDueCalculator::class)->issuedInvoice($repairOrder->fresh());
    $documents->generatePdf($invoice);

    expect($estimate->fresh()->pdf_path)->toEndWith('current-estimate.pdf')
        ->and($invoice->fresh()->pdf_path)->toEndWith('current-invoice.pdf')
        ->and($estimate->fresh()->pdf_path)->not->toBe($invoice->fresh()->pdf_path)
        ->and(Storage::disk('local')->exists($estimate->fresh()->pdf_path))->toBeTrue()
        ->and(Storage::disk('local')->exists($invoice->fresh()->pdf_path))->toBeTrue();
});

test('invoice pdf html groups approved concerns under severity intent headers', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $invoice = app(BalanceDueCalculator::class)->issuedInvoice($repairOrder->fresh());
    $snapshot = app(\App\Ark\Operations\Documents\EstimateDocumentPdfSnapshot::class)->resolve($invoice);

    $html = view('operations.documents.pdf.document', [
        'document' => $invoice,
        'snapshot' => $snapshot,
    ])->render();

    expect($html)
        ->toContain('concern--intent-maintenance')
        ->toContain('concern-priority-badge--maintenance')
        ->not->toContain('concern-intent-group')
        ->toContain('Approved brakes');
});

test('invoice pdf rendering includes payment history and balance due', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    app(RecordLedgerEntryAction::class)->recordPayment(
        $repairOrder->fresh(),
        5000,
        PaymentMethod::Card,
    );

    $invoice = app(BalanceDueCalculator::class)->issuedInvoice($repairOrder->fresh());
    $snapshot = app(\App\Ark\Operations\Documents\InvoicePdfFinancialSnapshot::class)
        ->append($invoice, $invoice->snapshot_json ?? []);

    expect($snapshot['financial']['payments_applied'])->toBe('$50.00')
        ->and($snapshot['financial']['balance_due'])->toBe('$100.00')
        ->and($snapshot['financial']['entries'])->not->toBeEmpty();

    $snapshot['document_footer'] = app(\App\Ark\Operations\Documents\DocumentFooterPresenter::class)->present($snapshot);

    $html = view('operations.documents.partials._document-footer', [
        'variant' => 'pdf',
        'snapshot' => $snapshot,
    ])->render();

    expect($html)
        ->toContain('Payments')
        ->toContain('Payment')
        ->toContain('$50.00')
        ->toContain('Balance due')
        ->toContain('$100.00');
});

test('recording a payment marks the invoice pdf for refresh', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $invoice = app(BalanceDueCalculator::class)->issuedInvoice($repairOrder->fresh());
    $invoice->forceFill(['needs_pdf_refresh' => false])->save();

    app(RecordLedgerEntryAction::class)->recordPayment(
        $repairOrder->fresh(),
        2500,
        PaymentMethod::Cash,
    );

    expect($invoice->fresh()->needs_pdf_refresh)->toBeTrue();
});

test('living invoice snapshot refreshes when approved work changes before any payment', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $invoiceBefore = app(BalanceDueCalculator::class)->issuedInvoice($repairOrder->fresh());
    $totalBefore = InvoiceSnapshotBuilder::invoiceTotalCents($invoiceBefore->snapshot_json ?? []);

    $line = $repairOrder->lines()->firstOrFail();
    $line->forceFill(['unit_price_cents' => 20000])->save();

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh(['lines.concern', 'concerns']));
    app(RefreshLivingInvoiceSnapshotAction::class)->syncIfEligible($repairOrder->fresh());

    $balance = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh());
    $invoiceAfter = app(BalanceDueCalculator::class)->issuedInvoice($repairOrder->fresh());

    expect($totalBefore)->toBe(15000)
        ->and(InvoiceSnapshotBuilder::invoiceTotalCents($invoiceAfter->snapshot_json ?? []))->toBe(20000)
        ->and($balance->balanceDueCents)->toBe(20000)
        ->and($balance->invoiceTotalCents)->toBe(20000);
});

test('living invoice snapshot still refreshes when only a deposit is on file', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    app(RecordLedgerEntryAction::class)->recordDeposit(
        $repairOrder->fresh(),
        5000,
        PaymentMethod::Card,
    );

    $line = $repairOrder->lines()->firstOrFail();
    $line->forceFill(['unit_price_cents' => 20000])->save();

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh(['lines.concern', 'concerns']));
    $refreshed = app(RefreshLivingInvoiceSnapshotAction::class)->syncIfEligible($repairOrder->fresh());

    $balance = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh());
    $invoice = app(BalanceDueCalculator::class)->issuedInvoice($repairOrder->fresh());

    expect($refreshed)->toBeTrue()
        ->and(InvoiceSnapshotBuilder::invoiceTotalCents($invoice->snapshot_json ?? []))->toBe(20000)
        ->and($balance->depositsAppliedCents)->toBe(5000)
        ->and($balance->balanceDueCents)->toBe(15000)
        ->and($balance->invoiceTotalCents)->toBe(20000);
});

test('living invoice snapshot does not refresh after a payment is recorded', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    app(RecordLedgerEntryAction::class)->recordPayment(
        $repairOrder->fresh(),
        5000,
        PaymentMethod::Cash,
    );

    $line = $repairOrder->lines()->firstOrFail();
    $line->forceFill(['unit_price_cents' => 30000])->save();

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh(['lines.concern', 'concerns']));
    $refreshed = app(RefreshLivingInvoiceSnapshotAction::class)->syncIfEligible($repairOrder->fresh());

    $invoice = app(BalanceDueCalculator::class)->issuedInvoice($repairOrder->fresh());

    expect($refreshed)->toBeFalse()
        ->and(InvoiceSnapshotBuilder::invoiceTotalCents($invoice->snapshot_json ?? []))->toBe(15000)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents)->toBe(10000);
});

test('living invoice does not silently refresh after the customer was shown the invoice', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $invoice = app(BalanceDueCalculator::class)->issuedInvoice($repairOrder->fresh());
    $invoice->markPresentedToCustomer();

    $line = $repairOrder->lines()->firstOrFail();
    $line->forceFill(['unit_price_cents' => 20000])->save();

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh(['lines.concern', 'concerns']));
    $refreshed = app(RefreshLivingInvoiceSnapshotAction::class)->syncIfEligible($repairOrder->fresh());

    $invoiceAfter = app(BalanceDueCalculator::class)->issuedInvoice($repairOrder->fresh());

    expect($refreshed)->toBeFalse()
        ->and(InvoiceSnapshotBuilder::invoiceTotalCents($invoiceAfter->snapshot_json ?? []))->toBe(15000)
        ->and($invoiceAfter->wasPresentedToCustomer())->toBeTrue();
});

test('invoice refresh updates customer-visible note text when totals are unchanged', function () {
    $repairOrder = financialCloseoutRepairOrder();
    $concern = $repairOrder->concerns()->firstOrFail();

    $note = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Note,
        'description' => 'Original note on invoice',
        'quantity' => '0.00',
        'unit_price_cents' => 0,
        'visible_to_advisor' => true,
        'visible_to_technician' => false,
        'visible_to_customer' => true,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh(['lines.concern', 'concerns']));
    issueFinalInvoiceFor($repairOrder->fresh());

    $invoiceBefore = app(BalanceDueCalculator::class)->issuedInvoice($repairOrder->fresh());
    $descriptionsBefore = collect($invoiceBefore->snapshot_json['concerns'] ?? [])
        ->flatMap(fn (array $c) => $c['lines'] ?? [])
        ->pluck('description')
        ->all();

    expect($descriptionsBefore)->toContain('Original note on invoice')
        ->and(InvoiceSnapshotBuilder::invoiceTotalCents($invoiceBefore->snapshot_json ?? []))->toBe(15000);

    $note->forceFill(['description' => 'Updated note on invoice'])->save();

    $refreshed = app(\App\Ark\Operations\Financial\RefreshCustomerInvoiceAction::class)
        ->executeIfNeeded($repairOrder->fresh(['lines.concern', 'concerns', 'customer']));

    $invoiceAfter = app(BalanceDueCalculator::class)->issuedInvoice($repairOrder->fresh());
    $descriptionsAfter = collect($invoiceAfter->snapshot_json['concerns'] ?? [])
        ->flatMap(fn (array $c) => $c['lines'] ?? [])
        ->pluck('description')
        ->all();

    expect($refreshed)->toBeTrue()
        ->and($descriptionsAfter)->toContain('Updated note on invoice')
        ->and($descriptionsAfter)->not->toContain('Original note on invoice')
        ->and(InvoiceSnapshotBuilder::invoiceTotalCents($invoiceAfter->snapshot_json ?? []))->toBe(15000);
});

test('invoice refresh adds customer-visible note after issue when totals are unchanged', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $concern = $repairOrder->concerns()->firstOrFail();

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Note,
        'description' => 'Note added after invoice',
        'quantity' => '0.00',
        'unit_price_cents' => 0,
        'visible_to_advisor' => true,
        'visible_to_technician' => false,
        'visible_to_customer' => true,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh(['lines.concern', 'concerns']));

    $refreshed = app(\App\Ark\Operations\Financial\RefreshCustomerInvoiceAction::class)
        ->executeIfNeeded($repairOrder->fresh(['lines.concern', 'concerns', 'customer']));

    $invoice = app(BalanceDueCalculator::class)->issuedInvoice($repairOrder->fresh());
    $descriptions = collect($invoice->snapshot_json['concerns'] ?? [])
        ->flatMap(fn (array $c) => $c['lines'] ?? [])
        ->pluck('description')
        ->all();

    expect($refreshed)->toBeTrue()
        ->and($descriptions)->toContain('Note added after invoice')
        ->and(InvoiceSnapshotBuilder::invoiceTotalCents($invoice->snapshot_json ?? []))->toBe(15000);
});

test('living invoice silent sync updates note text before customer presentation', function () {
    $repairOrder = financialCloseoutRepairOrder();
    $concern = $repairOrder->concerns()->firstOrFail();

    $note = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Note,
        'description' => 'Living original note',
        'quantity' => '0.00',
        'unit_price_cents' => 0,
        'visible_to_advisor' => true,
        'visible_to_technician' => false,
        'visible_to_customer' => true,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh(['lines.concern', 'concerns']));
    issueFinalInvoiceFor($repairOrder->fresh());

    $note->forceFill(['description' => 'Living updated note'])->save();

    $synced = app(RefreshLivingInvoiceSnapshotAction::class)
        ->syncIfEligible($repairOrder->fresh(['lines.concern', 'concerns', 'customer']));

    $invoice = app(BalanceDueCalculator::class)->issuedInvoice($repairOrder->fresh());
    $descriptions = collect($invoice->snapshot_json['concerns'] ?? [])
        ->flatMap(fn (array $c) => $c['lines'] ?? [])
        ->pluck('description')
        ->all();

    expect($synced)->toBeTrue()
        ->and($descriptions)->toContain('Living updated note')
        ->and(InvoiceSnapshotBuilder::invoiceTotalCents($invoice->snapshot_json ?? []))->toBe(15000);
});

test('explicit invoice refresh archives prior snapshot and matches approved work', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    app(RecordLedgerEntryAction::class)->recordDeposit(
        $repairOrder->fresh(),
        4000,
        PaymentMethod::Card,
    );

    $invoice = app(BalanceDueCalculator::class)->issuedInvoice($repairOrder->fresh());
    $invoice->markPresentedToCustomer();

    $line = $repairOrder->lines()->firstOrFail();
    $line->forceFill(['unit_price_cents' => 22000])->save();
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh(['lines.concern', 'concerns']));

    $refreshed = app(\App\Ark\Operations\Financial\RefreshCustomerInvoiceAction::class)
        ->execute($repairOrder->fresh());

    $balance = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh());

    expect(InvoiceSnapshotBuilder::invoiceTotalCents($refreshed->snapshot_json ?? []))->toBe(22000)
        ->and($refreshed->wasPresentedToCustomer())->toBeFalse()
        ->and($refreshed->snapshot_revisions_json)->toHaveCount(1)
        ->and($refreshed->snapshot_revisions_json[0]['invoice_total_cents'])->toBe(15000)
        ->and($balance->invoiceTotalCents)->toBe(22000)
        ->and($balance->depositsAppliedCents)->toBe(4000)
        ->and($balance->balanceDueCents)->toBe(18000);
});

test('explicit invoice refresh after partial payment revises billed total and keeps payments', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    app(RecordLedgerEntryAction::class)->recordPayment(
        $repairOrder->fresh(),
        5000,
        PaymentMethod::Card,
    );

    $line = $repairOrder->lines()->firstOrFail();
    $line->forceFill(['unit_price_cents' => 20000])->save();
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh(['lines.concern', 'concerns']));

    $refreshed = app(\App\Ark\Operations\Financial\RefreshCustomerInvoiceAction::class)
        ->execute($repairOrder->fresh());

    $balance = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh());
    $position = FinancialPositionProjection::for($repairOrder->fresh());

    expect(InvoiceSnapshotBuilder::invoiceTotalCents($refreshed->snapshot_json ?? []))->toBe(20000)
        ->and($balance->invoiceTotalCents)->toBe(20000)
        ->and($balance->paymentsAppliedCents)->toBe(5000)
        ->and($balance->balanceDueCents)->toBe(15000)
        ->and($position->approvedWorkCents)->toBe(20000)
        ->and($position->customerOwesTodayCents)->toBe(15000);
});

test('approving a concern after partial payment revises invoice balance due', function () {
    ShopSettings::current()->update(['shop_fee_enabled' => false, 'tax_enabled' => false]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder();
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh(['lines.concern', 'concerns', 'customer']));
    issueFinalInvoiceFor($repairOrder->fresh());

    app(RecordLedgerEntryAction::class)->recordPayment(
        $repairOrder->fresh(),
        5000,
        PaymentMethod::Card,
    );

    $extra = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Battery',
        'disposition' => RepairOrderConcernDisposition::Draft,
        'recommendation_intent' => 'maintenance',
        'position' => 2,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $extra->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Auxiliary Battery',
        'quantity' => '1.00',
        'unit_price_cents' => 25000,
        'subtotal_cents' => 25000,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'standing_discount_cents' => 0,
        'total_cents' => 25000,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh(['lines.concern', 'concerns', 'customer']));

    $before = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh());
    expect($before->invoiceTotalCents)->toBe(15000)
        ->and($before->balanceDueCents)->toBe(10000);

    $this->patch(route('operations.repair-orders.concerns.disposition', [$repairOrder, $extra]), [
        'disposition' => RepairOrderConcernDisposition::Approved->value,
    ])->assertRedirect();

    $after = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh());

    expect($after->invoiceTotalCents)->toBe(40000)
        ->and($after->paymentsAppliedCents)->toBe(5000)
        ->and($after->balanceDueCents)->toBe(35000);
});

test('adding an approved line after partial payment revises invoice balance due', function () {
    ShopSettings::current()->update(['shop_fee_enabled' => false, 'tax_enabled' => false]);

    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder();
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh(['lines.concern', 'concerns', 'customer']));
    issueFinalInvoiceFor($repairOrder->fresh());

    app(RecordLedgerEntryAction::class)->recordPayment(
        $repairOrder->fresh(),
        5000,
        PaymentMethod::Card,
    );

    $concern = $repairOrder->concerns()->firstOrFail();

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Sublet->value,
        'description' => 'Battery bolts sublet',
        'quantity' => '1.00',
        'part_cost' => '5.00',
        'unit_price' => '10.00',
    ])->assertRedirect();

    $after = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh());

    expect($after->invoiceTotalCents)->toBe(16000)
        ->and($after->paymentsAppliedCents)->toBe(5000)
        ->and($after->balanceDueCents)->toBe(11000);
});

test('write-off clears remaining invoice balance without counting as payment', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    app(RecordLedgerEntryAction::class)->recordPayment(
        $repairOrder->fresh(),
        14000,
        PaymentMethod::Card,
    );

    app(RecordLedgerEntryAction::class)->recordWriteOff(
        $repairOrder->fresh(),
        1000,
        notes: 'Rounding discrepancy',
        reference: 'test-writeoff',
    );

    $balance = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh());

    expect($balance->paymentsAppliedCents)->toBe(14000)
        ->and($balance->writeOffsCents)->toBe(1000)
        ->and($balance->balanceDueCents)->toBe(0)
        ->and($balance->isPaid())->toBeTrue();
});

class FakeFinancialPdfRenderer implements PdfRenderer
{
    public function renderEstimate(\App\Ark\Operations\Documents\EstimateDocument $document): string
    {
        $preservedStatus = $document->isInvoice() ? $document->status : null;

        $path = \App\Ark\Operations\Documents\DocumentPdfPath::for($document);

        Storage::disk('local')->put($path, 'PDF '.$path);

        $attributes = [
            'status' => $document->isInvoice() ? $preservedStatus : 'generated',
            'pdf_path' => $path,
            'generated_at' => now(),
            'needs_pdf_refresh' => false,
            'pdf_refreshed_at' => now(),
        ];

        $document->forceFill($attributes)->save();

        return $path;
    }
}
