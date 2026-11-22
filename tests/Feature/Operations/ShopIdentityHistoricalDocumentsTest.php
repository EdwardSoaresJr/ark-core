<?php

use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\EstimateDocumentPdfSnapshot;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Documents\PdfRenderer;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ShopIdentityFakePdfRenderer implements PdfRenderer
{
    public function renderEstimate(EstimateDocument $document): string
    {
        $path = sprintf('estimate-documents/ro-%d/%s', $document->repair_order_id, $document->isInvoice() ? 'current-invoice.pdf' : 'current-estimate.pdf');
        Storage::disk('local')->put($path, '%PDF-identity');
        $document->forceFill(['pdf_path' => $path, 'needs_pdf_refresh' => false, 'pdf_refreshed_at' => now()])->save();

        return $path;
    }
}

function shopIdentityPayload(string $name, ?UploadedFile $logo = null, array $extra = []): array
{
    return array_filter([
        'shop_name' => $name,
        'shop_timezone' => 'America/Denver',
        'phone' => '555-0110',
        'email' => 'service@example.test',
        'website' => 'https://example.test',
        'address_line_1' => $extra['address_line_1'] ?? '1 Old Road',
        'city' => 'Oldtown',
        'state' => 'CO',
        'postal_code' => '80903',
        'logo' => $logo,
        ...$extra,
    ], fn (mixed $value): bool => $value !== null);
}

function shopIdentityAsAdmin(): void
{
    test()->seed(ArkAuthorizationSeeder::class);
    Storage::fake('public');
    Storage::fake('local');
    app()->instance(PdfRenderer::class, new ShopIdentityFakePdfRenderer);
    test()->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));
}

function saveShopIdentity(string $name, ?UploadedFile $logo = null, array $extra = []): void
{
    test()->patch(route('operations.settings.shop.general.update'), shopIdentityPayload($name, $logo, $extra))
        ->assertRedirect(route('operations.settings.shop.edit'));
}

/** @return array{0: \App\Ark\Operations\RepairOrders\RepairOrder, 1: EstimateDocument} */
function issuedInvoiceForIdentity(RepairOrderStatus $closeAs = RepairOrderStatus::Closed): array
{
    $repairOrder = financialCloseoutRepairOrder();
    test()->post(route('operations.repair-orders.invoice.store', $repairOrder))->assertRedirect();
    $invoice = $repairOrder->fresh()->estimateDocuments()
        ->where('document_type', FinancialDocumentType::Invoice->value)->firstOrFail();
    $repairOrder->forceFill(['status' => $closeAs])->save();

    return [$repairOrder->fresh(), $invoice->fresh()];
}

function resolvedShop(EstimateDocument $document): array
{
    return app(EstimateDocumentPdfSnapshot::class)->resolve($document->fresh())['shop'];
}

test('issued invoice keeps its original shop identity and logo after settings change', function () {
    shopIdentityAsAdmin();
    saveShopIdentity('Original Garage', UploadedFile::fake()->image('old.png', 80, 40));
    $oldLogo = ShopSettings::current()->logo_path;

    [$repairOrder, $invoice] = issuedInvoiceForIdentity();
    $before = (array) EstimateDocument::query()->toBase()->where('id', $invoice->id)->first(['snapshot_json', 'status', 'pdf_path']);

    saveShopIdentity('Renamed Garage', UploadedFile::fake()->image('new.png', 90, 50), ['address_line_1' => '99 New Avenue']);

    $shop = resolvedShop($invoice);
    expect($shop['name'])->toBe('Original Garage')
        ->and($shop['address_line_1'])->toBe('1 Old Road')
        ->and($shop['logo_path'])->toBe($oldLogo)
        ->and($shop['logo_data_uri'])->toStartWith('data:image/png;base64,')
        ->and(ShopSettings::current()->logo_path)->not->toBe($oldLogo)
        ->and((array) EstimateDocument::query()->toBase()->where('id', $invoice->id)->first(['snapshot_json', 'status', 'pdf_path']))->toBe($before);

    // The prior asset is still referenced by the issued document, so it is not deleted.
    Storage::disk('public')->assertExists($oldLogo);

    $this->get(route('operations.repair-orders.estimate-documents.show', [$repairOrder, $invoice]))
        ->assertOk()
        ->assertSee('Original Garage')
        ->assertSee('1 Old Road')
        ->assertDontSee('Renamed Garage')
        ->assertDontSee('99 New Avenue');
});

test('removing the logo keeps an asset still embedded by an issued invoice', function () {
    shopIdentityAsAdmin();
    saveShopIdentity('Original Garage', UploadedFile::fake()->image('old.png', 80, 40));
    $oldLogo = ShopSettings::current()->logo_path;
    [, $invoice] = issuedInvoiceForIdentity();

    saveShopIdentity('Original Garage', null, ['remove_logo' => '1']);

    expect(ShopSettings::current()->logo_path)->toBeNull()
        ->and(resolvedShop($invoice)['logo_data_uri'])->toStartWith('data:image/png;base64,');
    Storage::disk('public')->assertExists($oldLogo);
});

test('a replaced logo that no document references is deleted', function () {
    shopIdentityAsAdmin();
    saveShopIdentity('Original Garage', UploadedFile::fake()->image('old.png', 80, 40));
    $oldLogo = ShopSettings::current()->logo_path;

    saveShopIdentity('Original Garage', UploadedFile::fake()->image('new.png', 90, 50));

    Storage::disk('public')->assertMissing($oldLogo);
    Storage::disk('public')->assertExists(ShopSettings::current()->logo_path);
});

test('an older issued snapshot that stored only the logo path re-embeds the retained asset, never today logo', function () {
    shopIdentityAsAdmin();
    saveShopIdentity('Original Garage', UploadedFile::fake()->image('old.png', 80, 40));
    $oldLogo = ShopSettings::current()->logo_path;
    [, $invoice] = issuedInvoiceForIdentity();

    $snapshot = $invoice->snapshot_json;
    unset($snapshot['shop']['logo_data_uri'], $snapshot['shop']['logo_url'], $snapshot['shop']['hours_summary']);
    EstimateDocument::query()->whereKey($invoice->id)->update(['snapshot_json' => json_encode($snapshot)]);

    saveShopIdentity('Renamed Garage', UploadedFile::fake()->image('new.png', 90, 50));

    $shop = resolvedShop($invoice);
    expect($shop['name'])->toBe('Original Garage')
        ->and($shop['logo_path'])->toBe($oldLogo)
        ->and($shop['logo_data_uri'])->toStartWith('data:image/png;base64,')
        ->and($shop['hours_summary'])->toBeNull();
});

test('closed repair order estimate and living estimate show current identity while invoices stay historical', function () {
    shopIdentityAsAdmin();
    saveShopIdentity('Original Garage');
    $service = app(EstimateDocumentService::class);

    $living = financialCloseoutRepairOrder(RepairOrderStatus::WaitingApproval);
    $livingDoc = $service->createOrRefresh($living);
    $closed = financialCloseoutRepairOrder();
    $closedDoc = $service->createOrRefresh($closed);
    $closed->forceFill(['status' => RepairOrderStatus::Closed])->save();

    saveShopIdentity('Renamed Garage', null, ['address_line_1' => '99 New Avenue']);

    // Living estimate: refreshed via the settings-change invalidation, flagged for a new PDF.
    $livingDoc->refresh();
    expect(data_get($livingDoc->snapshot_json, 'shop.name'))->toBe('Renamed Garage')
        ->and($livingDoc->needs_pdf_refresh)->toBeTrue();

    // Closed RO estimate: the stored snapshot is untouched, the workspace presents the current profile.
    expect(data_get($closedDoc->fresh()->snapshot_json, 'shop.name'))->toBe('Original Garage')
        ->and(resolvedShop($closedDoc)['name'])->toBe('Renamed Garage')
        ->and(resolvedShop($closedDoc)['address_line_1'])->toBe('99 New Avenue');
});

test('closed repair orders are not rewritten by the settings-change refresh', function () {
    shopIdentityAsAdmin();
    saveShopIdentity('Original Garage');
    $closed = financialCloseoutRepairOrder();
    $doc = app(EstimateDocumentService::class)->createOrRefresh($closed);
    $closed->forceFill(['status' => RepairOrderStatus::Closed])->save();
    $stored = $doc->fresh()->snapshot_json;

    saveShopIdentity('Renamed Garage');

    expect($doc->fresh()->snapshot_json)->toBe($stored);
});

test('newly issued invoices use the current identity and each invoice keeps its own', function () {
    shopIdentityAsAdmin();
    saveShopIdentity('First Identity');
    [, $first] = issuedInvoiceForIdentity();

    saveShopIdentity('Second Identity');
    [, $second] = issuedInvoiceForIdentity();

    saveShopIdentity('Third Identity');

    expect(resolvedShop($first)['name'])->toBe('First Identity')
        ->and(resolvedShop($second)['name'])->toBe('Second Identity')
        ->and(ShopSettings::current()->shop_name)->toBe('Third Identity');
});

test('settings change does not alter issued invoice amounts, pricing profile or document history', function () {
    shopIdentityAsAdmin();
    saveShopIdentity('Original Garage');
    [$repairOrder, $invoice] = issuedInvoiceForIdentity();
    $profile = $repairOrder->fresh()->commercial_pricing_profile;
    $totals = data_get($invoice->snapshot_json, 'totals');
    $documentCount = EstimateDocument::query()->count();

    saveShopIdentity('Renamed Garage');

    expect(data_get($invoice->fresh()->snapshot_json, 'totals'))->toBe($totals)
        ->and($repairOrder->fresh()->commercial_pricing_profile)->toBe($profile)
        ->and(EstimateDocument::query()->count())->toBe($documentCount)
        ->and(data_get(resolvedShop($invoice), 'name'))->toBe('Original Garage');
});
