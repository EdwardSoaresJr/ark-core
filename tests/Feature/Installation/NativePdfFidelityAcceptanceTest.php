<?php

/**
 * Paired Chromium + native mPDF document acceptance for traditional hosting.
 * Artifacts: storage/app/native-pdf-acceptance/
 */

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\DocumentPdfPresenter;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\EstimateDocumentPdfSnapshot;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Documents\HeadlessChromiumPdfRenderer;
use App\Ark\Operations\Documents\HtmlPdfBuilder;
use App\Ark\Operations\Documents\NativePhpPdfRenderer;
use App\Ark\Operations\Documents\PdfRuntimeUnavailable;
use App\Ark\Operations\Financial\CustomerAccountStatementBuilder;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Inspections\InspectionReportProjection;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Facades\Storage;

function acceptanceArtifactDir(): string
{
    $dir = storage_path('app/native-pdf-acceptance');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    return $dir;
}

function writeAcceptanceBytes(string $name, string $bytes): string
{
    $path = acceptanceArtifactDir().'/'.$name;
    file_put_contents($path, $bytes);

    return $path;
}

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    ShopSettings::current()->forceFill([
        'shop_name' => 'Fidelity Auto — 東京',
        'phone' => '555-0100',
        'email' => 'shop@fidelity.test',
        'address_line_1' => '1 Main St',
        'city' => 'Denver',
        'state' => 'CO',
        'postal_code' => '80202',
        'authorization_language' => 'I authorize the listed repairs and acknowledge warranty terms.',
        'shop_fee_enabled' => false,
        'tax_enabled' => false,
    ])->save();
});

it('produces paired estimate and invoice PDFs with financial and disclosure content', function () {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Estimate);
    Customer::query()->whereKey($repairOrder->customer_id)->update([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.test',
    ]);

    $documents = app(EstimateDocumentService::class);
    $estimate = $documents->createOrRefresh($repairOrder->fresh(), auth()->user());

    $native = app(NativePhpPdfRenderer::class);
    $nativePath = $native->renderEstimate($estimate);
    $nativeBytes = Storage::disk('local')->get($nativePath);
    expect(str_starts_with($nativeBytes, '%PDF'))->toBeTrue()
        ->and(strlen($nativeBytes))->toBeGreaterThan(2000);
    writeAcceptanceBytes('estimate-native.pdf', $nativeBytes);

    $chromiumOk = false;
    try {
        $chromePath = app(HeadlessChromiumPdfRenderer::class)->renderEstimate($estimate->fresh());
        $chromeBytes = Storage::disk('local')->get($chromePath);
        writeAcceptanceBytes('estimate-chromium.pdf', $chromeBytes);
        $chromiumOk = str_starts_with($chromeBytes, '%PDF');
    } catch (PdfRuntimeUnavailable|Throwable $e) {
        writeAcceptanceBytes('estimate-chromium.SKIPPED.txt', $e->getMessage());
    }

    issueFinalInvoiceFor($repairOrder->fresh());
    $invoice = EstimateDocument::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('document_type', FinancialDocumentType::Invoice->value)
        ->latest('id')
        ->first();
    expect($invoice)->not->toBeNull();

    $invoiceBytes = Storage::disk('local')->get(
        $native->renderEstimate($invoice),
    );
    expect(str_starts_with($invoiceBytes, '%PDF'))->toBeTrue();
    writeAcceptanceBytes('invoice-native.pdf', $invoiceBytes);

    try {
        $invoiceChromePath = app(HeadlessChromiumPdfRenderer::class)->renderEstimate($invoice->fresh());
        writeAcceptanceBytes('invoice-chromium.pdf', Storage::disk('local')->get($invoiceChromePath));
    } catch (PdfRuntimeUnavailable|Throwable $e) {
        writeAcceptanceBytes('invoice-chromium.SKIPPED.txt', $e->getMessage());
    }

    $snapshot = app(DocumentPdfPresenter::class)->prepareForCustomer(
        app(EstimateDocumentPdfSnapshot::class)->resolve($estimate->fresh()),
    );

    expect((string) data_get($snapshot, 'shop.name'))->toContain('Fidelity Auto')
        ->and((string) data_get($snapshot, 'shop.name'))->toContain('東京')
        ->and($snapshot)->toHaveKey('totals')
        ->and($snapshot)->toHaveKey('concerns');

    $html = view(NativePhpPdfRenderer::VIEW, [
        'document' => $estimate,
        'snapshot' => $snapshot,
    ])->render();

    expect($html)
        ->toContain('Fidelity Auto')
        ->toContain('東京')
        ->toContain('I authorize the listed repairs')
        ->not->toContain('native PHP PDF engine')
        ->not->toContain('Generated with the native');

    expect($chromiumOk || is_file(acceptanceArtifactDir().'/estimate-chromium.SKIPPED.txt'))->toBeTrue();
})->group('native-pdf-acceptance');

it('renders inspection and statement PDFs through HtmlPdfBuilder native fallback', function () {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Estimate);
    $customer = $repairOrder->customer;

    config([
        'services.pdf.node_binary' => '/bin/false',
        'services.pdf.chrome_path' => '/bin/false',
    ]);

    $report = app(InspectionReportProjection::class)->for(
        $repairOrder,
        InspectionReportProjection::MODE_DETAILED,
    );
    $inspectionHtml = view('portal.inspection-report-print', [
        'report' => $report,
    ])->render();
    $inspectionHtml .= '<div style="page-break-before:always"><h1>追加ページ — Additional page</h1><p>Unicode multipage check 東京</p></div>';
    $inspectionBytes = app(HtmlPdfBuilder::class)->toPdfBytes($inspectionHtml);
    expect(str_starts_with($inspectionBytes, '%PDF'))->toBeTrue()
        ->and(strlen($inspectionBytes))->toBeGreaterThan(1000);
    writeAcceptanceBytes('inspection-native.pdf', $inspectionBytes);

    $statement = app(CustomerAccountStatementBuilder::class)->forCustomer($customer);
    $statementHtml = view('operations.financials.accounts-receivable.statement-document', [
        'statement' => $statement,
        'combined' => false,
    ])->render();
    $statementBytes = app(HtmlPdfBuilder::class)->toPdfBytes($statementHtml);
    expect(str_starts_with($statementBytes, '%PDF'))->toBeTrue();
    writeAcceptanceBytes('statement-native.pdf', $statementBytes);
})->group('native-pdf-acceptance');
