<?php

use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\PdfRenderer;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Storage;

class CountingInvoicePdfRenderer implements PdfRenderer
{
    public static int $renders = 0;

    public function renderEstimate(EstimateDocument $document): string
    {
        self::$renders++;
        $path = sprintf('estimate-documents/ro-%d/%s', $document->repair_order_id, $document->isInvoice() ? 'current-invoice.pdf' : 'current-estimate.pdf');
        Storage::disk('local')->put($path, '%PDF-re-rendered '.self::$renders);
        $document->forceFill(['pdf_path' => $path, 'needs_pdf_refresh' => false, 'pdf_refreshed_at' => now()])->save();

        return $path;
    }
}

/**
 * @return array{0: \App\Ark\Operations\RepairOrders\RepairOrder, 1: EstimateDocument}
 */
function issuedInvoiceOnClosedRepairOrder(): array
{
    Storage::fake('local');
    app()->instance(PdfRenderer::class, new CountingInvoicePdfRenderer);
    CountingInvoicePdfRenderer::$renders = 0;

    $repairOrder = financialCloseoutRepairOrder();
    test()->post(route('operations.repair-orders.invoice.store', $repairOrder))->assertRedirect();
    $invoice = $repairOrder->fresh()->estimateDocuments()->where('document_type', FinancialDocumentType::Invoice->value)->firstOrFail();
    $repairOrder->forceFill(['status' => RepairOrderStatus::Closed])->save();

    return [$repairOrder->fresh(), $invoice->fresh()];
}

/**
 * @return array<string, mixed>
 */
function storedInvoiceRow(EstimateDocument $invoice): array
{
    return (array) EstimateDocument::query()->toBase()->where('id', $invoice->id)
        ->first(['snapshot_json', 'status', 'pdf_path', 'needs_pdf_refresh', 'pdf_refreshed_at', 'generated_at', 'issued_at']);
}

test('new invoices still render a pdf when issued', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [, $invoice] = issuedInvoiceOnClosedRepairOrder();

    expect(CountingInvoicePdfRenderer::$renders)->toBe(1)
        ->and($invoice->pdf_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($invoice->pdf_path))->toBeTrue();
});

test('a closed repair order invoice serves its stored pdf unchanged even when flagged for refresh or stored elsewhere', function (bool $nonCanonicalPath) {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));
    [$repairOrder, $invoice] = issuedInvoiceOnClosedRepairOrder();

    $path = $nonCanonicalPath ? 'estimate-documents/imported/invoice-'.$invoice->id.'.pdf' : $invoice->pdf_path;
    Storage::disk('local')->put($path, '%PDF-historical evidence');
    EstimateDocument::query()->whereKey($invoice->id)->update(['pdf_path' => $path, 'needs_pdf_refresh' => true]);
    $before = storedInvoiceRow($invoice);
    $renders = CountingInvoicePdfRenderer::$renders;

    $this->get(route('operations.repair-orders.estimate-documents.show', [$repairOrder, $invoice]))->assertOk();
    $pdf = $this->get(route('operations.repair-orders.estimate-documents.pdf.show', [$repairOrder, $invoice]))->assertOk();
    $download = $this->get(route('operations.repair-orders.estimate-documents.pdf.download', [$repairOrder, $invoice]))->assertOk();

    expect($pdf->getContent())->toBe('%PDF-historical evidence')
        ->and($download->getContent())->toBe('%PDF-historical evidence')
        ->and(Storage::disk('local')->get($path))->toBe('%PDF-historical evidence')
        ->and(CountingInvoicePdfRenderer::$renders)->toBe($renders)
        ->and(storedInvoiceRow($invoice))->toBe($before);
})->with(['canonical path' => [false], 'imported path' => [true]]);

test('a closed repair order invoice whose stored pdf is missing gets a clear 404 and is not re-created', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));
    [$repairOrder, $invoice] = issuedInvoiceOnClosedRepairOrder();

    Storage::disk('local')->delete($invoice->pdf_path);
    $before = storedInvoiceRow($invoice);
    $renders = CountingInvoicePdfRenderer::$renders;

    $this->get(route('operations.repair-orders.estimate-documents.show', [$repairOrder, $invoice]))->assertOk();
    $this->get(route('operations.repair-orders.estimate-documents.pdf.show', [$repairOrder, $invoice]))
        ->assertNotFound()
        ->assertSee('Issued invoices are not re-created automatically.');

    expect(CountingInvoicePdfRenderer::$renders)->toBe($renders)
        ->and(Storage::disk('local')->exists($invoice->pdf_path))->toBeFalse()
        ->and(storedInvoiceRow($invoice))->toBe($before);
});

test('opening an open repair order invoice as html does not render its pdf; its pdf still refreshes after a payment', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));
    Storage::fake('local');
    app()->instance(PdfRenderer::class, new CountingInvoicePdfRenderer);
    CountingInvoicePdfRenderer::$renders = 0;

    $repairOrder = financialCloseoutRepairOrder();
    $this->post(route('operations.repair-orders.invoice.store', $repairOrder))->assertRedirect();
    $invoice = $repairOrder->fresh()->estimateDocuments()->where('document_type', FinancialDocumentType::Invoice->value)->firstOrFail();
    EstimateDocument::query()->whereKey($invoice->id)->update(['needs_pdf_refresh' => true]);

    $this->get(route('operations.repair-orders.estimate-documents.show', [$repairOrder, $invoice]))->assertOk();
    expect(CountingInvoicePdfRenderer::$renders)->toBe(1);

    $this->get(route('operations.repair-orders.estimate-documents.pdf.show', [$repairOrder, $invoice]))->assertOk();
    expect(CountingInvoicePdfRenderer::$renders)->toBe(2);
});

test('issued invoice pdfs stay behind staff sign-in', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));
    [$repairOrder, $invoice] = issuedInvoiceOnClosedRepairOrder();

    auth()->logout();

    $this->get(route('operations.repair-orders.estimate-documents.pdf.show', [$repairOrder, $invoice]))->assertRedirect(route('login'));
});
