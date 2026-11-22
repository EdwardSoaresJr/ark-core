<?php

use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\PdfRenderer;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderPdfPreview;
use App\Ark\Operations\RepairOrders\RepairOrderPrintMenuProjection;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InlinePreviewPdfRenderer implements PdfRenderer
{
    public static int $renders = 0;

    public function renderEstimate(EstimateDocument $document): string
    {
        self::$renders++;
        $path = sprintf('estimate-documents/ro-%d/%s', $document->repair_order_id, $document->isInvoice() ? 'current-invoice.pdf' : 'current-estimate.pdf');
        Storage::disk('local')->put($path, '%PDF-'.($document->isInvoice() ? 'invoice' : 'estimate').'-'.$document->repair_order_id);
        $document->forceFill(['pdf_path' => $path, 'needs_pdf_refresh' => false, 'pdf_refreshed_at' => now()])->save();

        return $path;
    }
}

/**
 * @return array{0: RepairOrder, 1: EstimateDocument}
 */
function inlinePreviewIssuedInvoice(): array
{
    $repairOrder = financialCloseoutRepairOrder();
    test()->post(route('operations.repair-orders.invoice.store', $repairOrder))->assertRedirect();
    $invoice = $repairOrder->fresh()->estimateDocuments()
        ->where('document_type', FinancialDocumentType::Invoice->value)
        ->firstOrFail();
    $repairOrder->forceFill(['status' => RepairOrderStatus::Closed])->save();

    return [$repairOrder->fresh(), $invoice->fresh()];
}

beforeEach(function () {
    Storage::fake('local');
    app()->instance(PdfRenderer::class, new InlinePreviewPdfRenderer);
    InlinePreviewPdfRenderer::$renders = 0;
    $this->seed(ArkAuthorizationSeeder::class);
    $this->advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($this->advisor);
});

test('the workspace offers View Estimate in the header and footer, wired to the viewer and the existing estimate pdf routes', function () {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Estimate);

    $html = $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk()->getContent();

    expect(substr_count($html, 'data-ro-print-item="view_estimate"'))->toBe(2)
        ->and(substr_count($html, 'data-ark-pdf-preview'))->toBeGreaterThanOrEqual(2)
        ->and($html)->toContain('data-pdf-view-url="'.route('operations.repair-orders.estimate.pdf', $repairOrder).'"')
        ->and($html)->toContain('data-pdf-download-url="'.route('operations.repair-orders.estimate.pdf.download', $repairOrder).'"')
        ->and($html)->toContain('data-pdf-cache-bust')
        ->and($html)->not->toContain('data-ro-print-item="view_invoice"')
        // One viewer: close, print, download, open in new tab, and a fallback for browsers that cannot embed PDFs.
        ->and(substr_count($html, 'data-ark-inline-pdf-viewer'))->toBe(1)
        ->and($html)->toContain('data-bs-dismiss="modal"')
        ->and($html)->toContain('data-pdf-viewer-print')
        ->and($html)->toContain('data-pdf-viewer-download')
        ->and($html)->toContain('data-pdf-viewer-new-tab')
        ->and($html)->toContain('data-pdf-viewer-fallback')
        ->and($html)->toContain('data-pdf-viewer-fallback-link')
        ->and($html)->toContain('modal-fullscreen-md-down');
});

test('View Invoice appears once an invoice is issued and previews the invoice, not the estimate', function () {
    [$repairOrder, $invoice] = inlinePreviewIssuedInvoice();

    $items = collect(RepairOrderPrintMenuProjection::items($repairOrder, ['invoice' => $invoice], $this->advisor))->keyBy('key');
    $preview = RepairOrderPdfPreview::invoice($repairOrder, $invoice);

    expect($items)->toHaveKeys(['view_estimate', 'view_invoice', 'estimate_pdf', 'final_invoice'])
        ->and($items['view_invoice']['preview'])->toBe($preview)
        ->and($preview['view_url'])->toBe(route('operations.repair-orders.estimate-documents.pdf.show', [$repairOrder, $invoice, 'preview' => 1]))
        ->and($preview['download_url'])->toBe(route('operations.repair-orders.estimate-documents.pdf.download', [$repairOrder, $invoice]))
        ->and($preview['cache_bust'])->toBeFalse()
        ->and($items['view_estimate']['preview']['document_id'])->not->toBe($invoice->id);

    $html = $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk()->getContent();

    expect(substr_count($html, 'data-ro-print-item="view_invoice"'))->toBe(2)
        ->and($html)->toContain('data-ro-view-invoice')
        ->and($html)->toContain('data-document-id="'.$invoice->id.'"');
});

test('the invoice preview serves the stored issued pdf without presenting, rendering or changing the invoice; download keeps its existing behavior', function () {
    [$repairOrder, $invoice] = inlinePreviewIssuedInvoice();
    $renders = InlinePreviewPdfRenderer::$renders;
    $stored = Storage::disk('local')->get($invoice->pdf_path);
    $row = fn (): array => (array) EstimateDocument::query()->toBase()->where('id', $invoice->id)
        ->first(['snapshot_json', 'status', 'pdf_path', 'customer_presented_at', 'issued_at', 'updated_at']);
    $before = $row();
    expect($before['customer_presented_at'])->toBeNull();

    $preview = $this->get(RepairOrderPdfPreview::invoice($repairOrder, $invoice)['view_url'])->assertOk();

    expect($preview->getContent())->toBe($stored)
        ->and($preview->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($preview->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($row())->toBe($before)
        ->and(InlinePreviewPdfRenderer::$renders)->toBe($renders);

    // Existing explicit actions are unchanged: opening the plain PDF still presents the invoice.
    $this->get(route('operations.repair-orders.estimate-documents.pdf.show', [$repairOrder, $invoice]))->assertOk();

    expect($invoice->fresh()->customer_presented_at)->not->toBeNull()
        ->and(InlinePreviewPdfRenderer::$renders)->toBe($renders);
});

test('a historical invoice keeps showing its stored pdf after the estimate changes, and prior revisions keep their own endpoint', function () {
    [$repairOrder, $invoice] = inlinePreviewIssuedInvoice();
    $stored = Storage::disk('local')->get($invoice->pdf_path);

    $estimate = $this->get(route('operations.repair-orders.estimate.pdf', $repairOrder))->assertOk()->getContent();
    $repairOrder->lines()->update(['total_cents' => DB::raw('total_cents + 100')]);

    expect($this->get(RepairOrderPdfPreview::invoice($repairOrder, $invoice)['view_url'])->getContent())->toBe($stored)
        ->and($estimate)->not->toBe($stored)
        ->and($this->get(route('operations.repair-orders.estimate-documents.revisions.pdf.show', [$repairOrder, $invoice, 1]))->status())->toBe(404);
});

test('the estimate preview serves the same current estimate pdf as print and download', function () {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Estimate);
    $preview = RepairOrderPdfPreview::estimate($repairOrder);

    $view = $this->get($preview['view_url'])->assertOk();
    $download = $this->get($preview['download_url'])->assertOk();

    expect($view->getContent())->toBe($download->getContent())
        ->and($view->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($download->headers->get('Content-Disposition'))->toStartWith('attachment');
});

test('previewing creates no invoice, approval or financial record', function () {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Estimate);
    $count = fn (): array => [
        EstimateDocument::query()->where('document_type', FinancialDocumentType::Invoice->value)->count(),
        $repairOrder->fresh()->lines()->sum('total_cents'),
        $repairOrder->fresh()->status->value,
    ];
    $before = $count();

    $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk();
    $this->get(RepairOrderPdfPreview::estimate($repairOrder)['view_url'])->assertOk();

    expect($count())->toBe($before);
});

test('previews stay behind staff sign-in, the view capability and the owning repair order', function () {
    [$repairOrder, $invoice] = inlinePreviewIssuedInvoice();
    $url = RepairOrderPdfPreview::invoice($repairOrder, $invoice)['view_url'];
    $estimateUrl = RepairOrderPdfPreview::estimate($repairOrder)['view_url'];
    $other = financialCloseoutRepairOrder(RepairOrderStatus::Estimate);

    // An invoice cannot be previewed through another repair order.
    $this->get(route('operations.repair-orders.estimate-documents.pdf.show', [$other, $invoice, 'preview' => 1]))->assertNotFound();

    $outsider = User::factory()->create();
    $this->actingAs($outsider);
    expect(RepairOrderPrintMenuProjection::items($repairOrder, ['invoice' => $invoice], $outsider))->toBe([]);
    $this->get($url)->assertForbidden();
    $this->get($estimateUrl)->assertForbidden();

    auth()->logout();
    $this->get($url)->assertRedirect(route('login'));
    $this->get($estimateUrl)->assertRedirect(route('login'));
});

test('legacy html estimate links stay valid as an explicit staff record that leads to the in-RO viewer', function () {
    [$repairOrder, $invoice] = inlinePreviewIssuedInvoice();
    $inRo = route('operations.repair-orders.show', [$repairOrder, 'view_document' => $invoice->id]);

    $this->get(route('operations.repair-orders.estimate-documents.show', [$repairOrder, $invoice]))
        ->assertOk()
        ->assertSee('data-estimate-document-staff-record', false)
        ->assertSee($inRo, false);

    $other = financialCloseoutRepairOrder(RepairOrderStatus::Estimate);
    $this->get(route('operations.repair-orders.estimate-documents.show', [$other, $invoice]))->assertNotFound();

    auth()->logout();
    $this->get(route('operations.repair-orders.estimate-documents.show', [$repairOrder, $invoice]))->assertRedirect(route('login'));
});

// Timeline emailed-document links are covered in DocumentEmailRecipientTest (already on the
// communications baseline): they resolve to operations.repair-orders.show?view_document=.

test('the viewer is a progressive enhancement: triggers are real new-tab links to the same pdf', function () {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Estimate);

    $html = $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk()->getContent();

    expect($html)->toMatch('/<a\s+href="'.preg_quote(route('operations.repair-orders.estimate.pdf', $repairOrder), '/').'"\s+target="_blank"\s+rel="noopener"\s+data-ark-pdf-preview/');
});
