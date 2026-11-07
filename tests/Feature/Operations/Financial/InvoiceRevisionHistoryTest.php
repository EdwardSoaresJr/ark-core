<?php

use App\Ark\Operations\Documents\DocumentPdfPath;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\InvoicePdfFinancialSnapshot;
use App\Ark\Operations\Documents\InvoiceRevisionPdf;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\FinancialPositionCalculator;
use App\Ark\Operations\Financial\InvoiceSnapshotBuilder;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Operations\Financial\RefreshCustomerInvoiceAction;
use App\Ark\Operations\Financial\RepairOrderCloseoutAuthority;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\WorkCompletionAuthorization;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    $this->advisor = User::factory()->create(['name' => 'Synthetic Advisor'])->assignRole(ArkRole::Advisor->value);
    $this->actingAs($this->advisor);
});

/**
 * One approved concern with 1.00 hr of labor at $150 and an issued Final Invoice of $150 whose PDF is stored.
 *
 * @return array{0: RepairOrder, 1: RepairOrderLine, 2: EstimateDocument}
 */
function revisionHistoryRepairOrder(): array
{
    $repairOrder = financialCloseoutRepairOrder();
    $line = $repairOrder->lines()->firstOrFail();
    $line->forceFill([
        'labor_category_key' => 'mechanical',
        'labor_category_name' => 'Mechanical',
        'labor_entered_hours' => '1.00',
        'labor_billed_hours' => '1.00',
        'labor_adjustment' => 'normal',
        'labor_adjustment_factor' => '1.00',
        'labor_rate_cents' => 15000,
        'policy_resolved_labor_rate_cents' => 15000,
    ])->save();

    approveCurrentAmounts($repairOrder);
    issueFinalInvoiceFor($repairOrder->fresh());

    $invoice = app(BalanceDueCalculator::class)->issuedInvoice($repairOrder->fresh());
    revisionHistoryStorePdf($invoice, 'PDF of the invoice as first issued');

    return [$repairOrder->fresh(), $line->fresh(), $invoice->fresh()];
}

function revisionHistoryStorePdf(EstimateDocument $invoice, string $contents): void
{
    $path = DocumentPdfPath::for($invoice);
    Storage::disk('local')->put($path, $contents);
    DB::table('estimate_documents')->where('id', $invoice->id)->update(['pdf_path' => $path, 'needs_pdf_refresh' => false]);
}

function revisionHistoryPatchHours(RepairOrder $repairOrder, RepairOrderLine $line, string $hours): void
{
    test()->patch(route('operations.repair-orders.lines.update', [$repairOrder, $line]), [
        'repair_order_concern_id' => $line->repair_order_concern_id,
        'type' => 'labor',
        'description' => $line->description,
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => $hours,
        'labor_adjustment' => 'normal',
        'unit_price' => '150.00',
        'labor_rate_overridden' => '0',
    ])->assertSessionHasNoErrors();
}

function revisionHistoryTotal(EstimateDocument $invoice): int
{
    return InvoiceSnapshotBuilder::invoiceTotalCents($invoice->fresh()->snapshot_json ?? []);
}

test('a presented invoice updates with the estimate and keeps the version the customer saw, with its PDF', function () {
    [$repairOrder, $line, $invoice] = revisionHistoryRepairOrder();
    $invoice->markPresentedToCustomer();
    $presentedAt = $invoice->fresh()->customer_presented_at;

    revisionHistoryPatchHours($repairOrder, $line, '0.50');

    $invoice = $invoice->fresh();
    $revision = $invoice->priorRevisions()[0];

    expect(revisionHistoryTotal($invoice))->toBe(7500)
        ->and($invoice->currentRevisionNumber())->toBe(2)
        ->and($invoice->wasPresentedToCustomer())->toBeFalse()
        ->and($invoice->pdf_path)->toBeNull()
        ->and($revision)->toMatchArray([
            'revision' => 1,
            'trigger' => 'line_updated',
            'actor_user_id' => $this->advisor->id,
            'invoice_total_cents' => 15000,
            'presented_at' => $presentedAt->toIso8601String(),
            'pdf_path' => InvoiceRevisionPdf::pathFor($invoice, 1),
            'collected_cents' => 0,
        ])
        ->and(Storage::disk('local')->get($revision['pdf_path']))->toBe('PDF of the invoice as first issued')
        ->and(OperationalEvent::query()->where('event_name', OperationalEventName::InvoiceRevised->value)->sole()->payload_json)->toMatchArray([
            'invoice_id' => $invoice->id,
            'trigger' => 'line_updated',
            'revision' => 2,
            'archived_revision' => 1,
            'prior_was_presented' => true,
            'prior_total_cents' => 15000,
            'new_total_cents' => 7500,
            'balance_due_cents' => 7500,
            'overpaid_cents' => 0,
        ]);
});

test('a partly paid invoice updates, keeps payments, and does not add a revision for every later edit', function () {
    [$repairOrder, $line, $invoice] = revisionHistoryRepairOrder();
    app(RecordLedgerEntryAction::class)->recordPayment($repairOrder->fresh(), 5000, PaymentMethod::Card);

    revisionHistoryPatchHours($repairOrder, $line, '0.50');

    $balance = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh());
    expect(revisionHistoryTotal($invoice))->toBe(7500)
        ->and($invoice->fresh()->priorRevisions())->toHaveCount(1)
        ->and($invoice->fresh()->priorRevisions()[0]['collected_cents'])->toBe(5000)
        ->and($balance->paymentsAppliedCents)->toBe(5000)
        ->and($balance->balanceDueCents)->toBe(2500)
        ->and($balance->overpaidCents)->toBe(0);

    revisionHistoryPatchHours($repairOrder, $line, '0.75');
    revisionHistoryPatchHours($repairOrder, $line, '1.00');

    $balance = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh());
    expect(revisionHistoryTotal($invoice))->toBe(15000)
        ->and($invoice->fresh()->priorRevisions())->toHaveCount(1)
        ->and($balance->balanceDueCents)->toBe(10000);

    app(RecordLedgerEntryAction::class)->recordPayment($repairOrder->fresh(), 2000, PaymentMethod::Card);
    revisionHistoryPatchHours($repairOrder, $line, '0.75');

    expect($invoice->fresh()->priorRevisions())->toHaveCount(2)
        ->and($invoice->fresh()->priorRevisions()[1])->toMatchArray(['revision' => 2, 'invoice_total_cents' => 15000, 'collected_cents' => 7000, 'pdf_path' => null])
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents)->toBe(4250);
});

test('a paid invoice corrected below the amount paid shows the credit due instead of hiding it', function () {
    [$repairOrder, $line, $invoice] = revisionHistoryRepairOrder();
    payRepairOrderInFull($repairOrder->fresh());

    revisionHistoryPatchHours($repairOrder, $line, '0.50');

    $balance = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh());
    expect(revisionHistoryTotal($invoice))->toBe(7500)
        ->and($balance->paymentsAppliedCents)->toBe(15000)
        ->and($balance->balanceDueCents)->toBe(0)
        ->and($balance->overpaidCents)->toBe(7500)
        ->and(app(InvoicePdfFinancialSnapshot::class)->append($invoice->fresh(), [])['financial'])->toMatchArray([
            'overpaid' => '$75.00',
            'overpaid_cents' => 7500,
        ]);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('data-invoice-overpaid="7500"', false)
        ->assertSee('Refund or credit due: $75.00', false);

    app(RecordLedgerEntryAction::class)->recordRefund($repairOrder->fresh(), 7500);

    expect(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh()))
        ->overpaidCents->toBe(0)
        ->balanceDueCents->toBe(0);
});

test('an invoice nobody has seen or paid follows the estimate without adding revisions', function () {
    [$repairOrder, $line, $invoice] = revisionHistoryRepairOrder();

    revisionHistoryPatchHours($repairOrder, $line, '0.50');
    revisionHistoryPatchHours($repairOrder, $line, '0.75');

    expect(revisionHistoryTotal($invoice))->toBe(11250)
        ->and($invoice->fresh()->priorRevisions())->toBe([]);
});

test('a revision PDF is served exactly as stored, never rendered again, and does not count as presenting', function () {
    [$repairOrder, $line, $invoice] = revisionHistoryRepairOrder();
    $invoice->markPresentedToCustomer();
    revisionHistoryPatchHours($repairOrder, $line, '0.50');
    revisionHistoryStorePdf($invoice->fresh(), 'PDF of revision 2');

    $this->get(route('operations.repair-orders.estimate-documents.revisions.pdf.show', [$repairOrder, $invoice, 1]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertSee('PDF of the invoice as first issued', false);

    $this->get(route('operations.repair-orders.estimate-documents.revisions.pdf.download', [$repairOrder, $invoice, 1]))
        ->assertOk()
        ->assertDownload('invoice-ro-'.$repairOrder->repair_order_id.'-revision-1.pdf');

    expect($invoice->fresh()->wasPresentedToCustomer())->toBeFalse()
        ->and(Storage::disk('local')->get(DocumentPdfPath::for($invoice)))->toBe('PDF of revision 2')
        ->and(Storage::disk('local')->get(InvoiceRevisionPdf::pathFor($invoice, 1)))->toBe('PDF of the invoice as first issued');
});

test('revision PDFs need repair order access and only exist where a PDF was stored', function () {
    [$repairOrder, $line, $invoice] = revisionHistoryRepairOrder();
    DB::table('estimate_documents')->where('id', $invoice->id)->update(['needs_pdf_refresh' => true]);
    app(RecordLedgerEntryAction::class)->recordPayment($repairOrder->fresh(), 5000, PaymentMethod::Card);
    revisionHistoryPatchHours($repairOrder, $line, '0.50');

    expect($invoice->fresh()->priorRevisions()[0]['pdf_path'])->toBeNull();

    $this->get(route('operations.repair-orders.estimate-documents.revisions.pdf.show', [$repairOrder, $invoice, 1]))->assertNotFound();
    $this->get(route('operations.repair-orders.estimate-documents.revisions.pdf.show', [$repairOrder, $invoice, 9]))->assertNotFound();

    [$otherRepairOrder] = revisionHistoryRepairOrder();
    $this->get(route('operations.repair-orders.estimate-documents.revisions.pdf.show', [$otherRepairOrder, $invoice, 1]))->assertNotFound();

    $this->actingAs(User::factory()->create());
    $this->get(route('operations.repair-orders.estimate-documents.revisions.pdf.show', [$repairOrder, $invoice, 1]))->assertForbidden();
});

test('revision history lists every version with its PDF actions', function () {
    [$repairOrder, $line, $invoice] = revisionHistoryRepairOrder();
    $invoice->markPresentedToCustomer();
    revisionHistoryPatchHours($repairOrder, $line, '0.50');

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('data-invoice-revision-history', false)
        ->assertSee('Revision history (2 versions)', false)
        ->assertSee('Revision 2 · current · $75.00', false)
        ->assertSee('Revision 1 · $150.00', false)
        ->assertSee('by Synthetic Advisor', false)
        ->assertSee(route('operations.repair-orders.estimate-documents.revisions.pdf.download', [$repairOrder, $invoice, 1]), false)
        ->assertSee('data-invoice-revision-reprint', false);
});

test('raising approved work does not raise the invoice before the customer approves the increase', function () {
    [$repairOrder, $line, $invoice] = revisionHistoryRepairOrder();
    $invoice->markPresentedToCustomer();

    revisionHistoryPatchHours($repairOrder, $line, '2.00');

    $repairOrder = $repairOrder->fresh(['concerns', 'lines.concern']);

    expect(revisionHistoryTotal($invoice))->toBe(15000)
        ->and($invoice->fresh()->priorRevisions())->toBe([])
        ->and($invoice->fresh()->wasPresentedToCustomer())->toBeTrue()
        ->and(app(WorkCompletionAuthorization::class)->linesRequiringAuthorization($repairOrder)->pluck('id')->all())->toBe([$line->id])
        ->and(app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder)->totalCents())->toBe(15000);
});

test('a repair order closes with credit due, which stays visible and is settled as store credit once', function () {
    [$repairOrder, $line, $invoice] = revisionHistoryRepairOrder();
    payRepairOrderInFull($repairOrder->fresh());
    revisionHistoryPatchHours($repairOrder, $line, '0.50');

    expect(app(RepairOrderCloseoutAuthority::class)->canClose($repairOrder->fresh()))->toBeTrue();
    $repairOrder->forceFill(['status' => RepairOrderStatus::Closed])->save();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Refund or credit due: $75.00', false)
        ->assertSee('data-credit-due-form', false);
    $this->get(route('operations.customers.show', $repairOrder->customer))
        ->assertOk()
        ->assertSee('data-customer-credit-due', false)
        ->assertSee('$75.00 to refund or issue as store credit', false);

    $this->post(route('operations.repair-orders.credit-due.store', $repairOrder), ['amount' => '80.00', 'settle_as' => 'store_credit'])
        ->assertSessionHasErrors(['amount' => 'Enter an amount up to the credit due of $75.00.']);

    $this->post(route('operations.repair-orders.credit-due.store', $repairOrder), ['amount' => '75.00', 'settle_as' => 'store_credit'])
        ->assertSessionHasNoErrors();

    $balance = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh());
    expect($balance)
        ->overpaidCents->toBe(0)
        ->balanceDueCents->toBe(0)
        ->creditDueToStoreCreditCents->toBe(7500)
        ->collectedCents()->toBe(7500)
        ->and($repairOrder->customer->fresh()->store_credit_balance_cents)->toBe(7500)
        ->and(app(FinancialPositionCalculator::class)->for($repairOrder->fresh())->customerOwesTodayCents)->toBe(0)
        ->and(OperationalEvent::query()->where('event_name', OperationalEventName::InvoiceCreditDueResolved->value)->sole()->payload_json)
        ->toMatchArray(['settled_as' => 'store_credit', 'amount_cents' => 7500]);

    $this->get(route('operations.customers.show', $repairOrder->customer))->assertOk()->assertDontSee('data-customer-credit-due', false);
});

test('a refund settles credit due, and settled invoices stop updating with a clear explanation', function () {
    [$repairOrder, $line, $invoice] = revisionHistoryRepairOrder();
    payRepairOrderInFull($repairOrder->fresh());
    revisionHistoryPatchHours($repairOrder, $line, '0.50');

    $this->post(route('operations.repair-orders.credit-due.store', $repairOrder), ['amount' => '75.00', 'settle_as' => 'refund'])
        ->assertSessionHasNoErrors();

    expect(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh()))
        ->overpaidCents->toBe(0)
        ->refundsAppliedCents->toBe(7500)
        ->and($repairOrder->customer->fresh()->store_credit_balance_cents)->toBe(0);

    revisionHistoryPatchHours($repairOrder, $line, '1.00');

    expect(revisionHistoryTotal($invoice))->toBe(7500);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee(RefreshCustomerInvoiceAction::SETTLED_EXPLANATION, false);
});
