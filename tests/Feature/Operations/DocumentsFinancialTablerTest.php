<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\Document;
use App\Ark\Operations\Documents\DocumentType;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\StoreDocumentAction;
use App\Ark\Operations\Parts\DealerQuote;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\WorkAuthorization\AuthorizeTestingPackageAction;
use App\Ark\Operations\WorkAuthorization\TestingPackageOutcome;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Database\Seeders\ShopSettingsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    $this->seed(ShopSettingsSeeder::class);
    Storage::fake('local');
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
});

/**
 * Counts `.ark-legacy` islands under the elements matched by $scope, ignoring islands inside an element that carries
 * $allowedAttribute (a document body).
 */
function docsTablerStrayIslands(string $html, string $scope, ?string $allowedAttribute = null): int
{
    $dom = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $island = "contains(concat(' ', normalize-space(@class), ' '), ' ark-legacy ')";
    $allowed = $allowedAttribute === null ? '' : "[not(ancestor-or-self::*[@{$allowedAttribute}])]";

    return (new DOMXPath($dom))->query("{$scope}//*[{$island}]{$allowed}")->length;
}

function docsTablerCount(string $html, string $xpath): int
{
    $dom = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return (new DOMXPath($dom))->query($xpath)->length;
}

function docsTablerPageScope(): string
{
    return "//div[contains(concat(' ', normalize-space(@class), ' '), ' page-header ') or contains(concat(' ', normalize-space(@class), ' '), ' page-body ')]";
}

/**
 * @return array{0: Customer, 1: RepairOrder}
 */
function docsTablerCustomer(): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Paper',
        'last_name' => 'Trail',
        'phone' => '555-0311',
        'email' => 'paper.trail@example.test',
        'customer_type' => 'Retail',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'DOC311',
        'year' => 2017,
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Warranty paperwork',
    ])->fresh();

    return [$customer, $repairOrder];
}

function docsTablerDocument(Customer $customer, User $actor): Document
{
    return app(StoreDocumentAction::class)->handle(
        $customer,
        UploadedFile::fake()->create('warranty.pdf', 40, 'application/pdf'),
        $actor,
        DocumentType::Warranty,
        'Powertrain warranty contract',
    );
}

function docsTablerEstimateRepairOrder(): RepairOrder
{
    [, $repairOrder] = docsTablerCustomer();
    $repairOrder->update(['status' => RepairOrderStatus::WaitingApproval]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front brake grinding',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'recommendation_intent' => 'immediate_attention',
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace front pads and rotors',
        'quantity' => '1.50',
        'unit_price_cents' => 15000,
    ]);

    return $repairOrder->fresh();
}

function docsTablerDealerQuoteText(): string
{
    return <<<'TEXT'
Bob Penkhus Volkswagen
Invoice #: Q31100
Vehicle: 2013 Tiguan
VIN: WVGAV7AX0DW555555

Qty Part Number Description Cost
1 06J-103-603-BD Upper Oil Sump 406.08
12 N-910-488-02 Screw 6.53

Dealer Total $484.44
TEXT;
}

/**
 * @return array{0: RepairOrder, 1: DealerQuote}
 */
function docsTablerDealerQuote(TestCase $test, User $advisor): array
{
    [, $repairOrder] = docsTablerCustomer();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Oil leak',
        'position' => 1,
    ]);

    $analyze = $test->actingAs($advisor)
        ->postJson(route('operations.repair-orders.dealer-quotes.analyze', $repairOrder), [
            'quote_text' => docsTablerDealerQuoteText(),
        ])
        ->assertOk()
        ->json();

    $test->actingAs($advisor)
        ->post(route('operations.repair-orders.dealer-quotes.store', $repairOrder), [
            RepairOrderConcurrency::FIELD => $repairOrder->fresh()->estimate_version,
            'capture' => [
                'supplier_name' => $analyze['supplier_name'],
                'quote_number' => $analyze['quote_number'],
                'vehicle_description' => $analyze['vehicle_description'],
                'vin' => $analyze['vin'],
                'dealer_total_cents' => $analyze['dealer_total_cents'],
                'raw_text' => $analyze['raw_text'],
                'original_filename' => null,
                'temp_storage_path' => null,
                'lines' => $analyze['lines'],
            ],
            'assignments' => collect($analyze['lines'])->map(fn (array $line): array => [
                'source_key' => $line['source_key'],
                'repair_order_concern_id' => $concern->id,
                'part_cost' => $line['part_cost'],
            ])->all(),
        ])
        ->assertRedirect();

    return [$repairOrder, DealerQuote::query()->where('repair_order_id', $repairOrder->id)->sole()];
}

test('the document viewer renders in the Tabler shell with its forms and commands', function (): void {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    [$customer] = docsTablerCustomer();
    $document = docsTablerDocument($customer, $admin);

    $html = $this->actingAs($admin)
        ->get(route('operations.customers.documents.viewer', [$customer, $document]))
        ->assertOk()
        ->assertSee('navbar navbar-vertical', false)
        ->assertSee('class="page-title">Powertrain warranty contract</h2>', false)
        ->assertSee('role="group" aria-label="Document commands"', false)
        ->assertSee('href="'.route('operations.customers.show', [$customer, 'tab' => 'documents']).'"', false)
        ->assertSee('onclick="window.print()"', false)
        ->assertSee('href="#document-email-'.$document->id.'"', false)
        ->assertSee('id="document-email-'.$document->id.'" class="card"', false)
        ->assertSee('action="'.route('operations.customers.documents.email', [$customer, $document]).'"', false)
        ->assertSee('type="email"', false)
        ->assertSee('name="email"', false)
        ->assertSee('value="paper.trail@example.test"', false)
        ->assertSee('name="message"', false)
        ->assertSee('<input type="hidden" name="direction" value="left">', false)
        ->assertSee('<input type="hidden" name="direction" value="right">', false)
        ->assertSee('Rotate Left')
        ->assertSee('Rotate Right')
        ->assertSee('data-document-viewer-meta', false)
        ->assertSee('Paper Trail')
        ->assertSee('Shop only')
        ->assertSee('<iframe', false)
        ->assertDontSee('ops-eyebrow', false)
        ->assertDontSee('ops-meta', false)
        ->getContent();

    expect(docsTablerStrayIslands($html, docsTablerPageScope()))->toBe(0)
        ->and(docsTablerCount($html, docsTablerPageScope()."//*[contains(@class, 'slate-')]"))->toBe(0);
});

test('the document viewer email log is a Tabler card', function (): void {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    [$customer] = docsTablerCustomer();
    $document = docsTablerDocument($customer, $admin);
    Storage::disk('local')->put($document->storage_path, '%PDF-1.4 warranty');
    usePlatformPortalMail();

    $this->actingAs($admin)
        ->from(route('operations.customers.documents.viewer', [$customer, $document]))
        ->post(route('operations.customers.documents.email', [$customer, $document]), [
            'message' => 'Your warranty contract.',
        ])
        ->assertRedirect(route('operations.customers.documents.viewer', [$customer, $document]));

    $this->actingAs($admin)
        ->get(route('operations.customers.documents.viewer', [$customer, $document]))
        ->assertOk()
        ->assertSee('alert alert-success', false)
        ->assertSee('data-document-email-log', false)
        ->assertSee('Email log')
        ->assertSee('paper.trail@example.test')
        ->assertSee('Note: Your warranty contract.');
});

test('the document viewer keeps today\'s role access', function (): void {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    [$customer] = docsTablerCustomer();
    $document = docsTablerDocument($customer, $admin);
    $viewer = route('operations.customers.documents.viewer', [$customer, $document]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get($viewer)
        ->assertOk()
        ->assertSee('Rotate Left')
        ->assertSee('Email document');

    $this->actingAs(actingAsLearnCurrentStaff(ArkRole::Technician))
        ->get($viewer)
        ->assertOk()
        ->assertSee('navbar navbar-vertical', false)
        ->assertDontSee('Rotate Left')
        ->assertDontSee('Email document')
        ->assertDontSee('href="#document-email-'.$document->id.'"', false);

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Customer->value))
        ->get($viewer)
        ->assertForbidden();
});

test('the customer hub document list and add-document panel are Tabler with the same forms', function (): void {
    $advisor = actingAsLearnCurrentAdvisor();
    [$customer, $repairOrder] = docsTablerCustomer();
    $document = docsTablerDocument($customer, $advisor);

    $html = $this->actingAs($advisor)
        ->get(route('operations.customers.show', ['customer' => $customer, 'tab' => 'documents']))
        ->assertOk()
        ->assertSee('data-document-list', false)
        ->assertSee('class="list-group list-group-flush" data-document-list', false)
        ->assertSee('Powertrain warranty contract')
        ->assertSee('href="'.route('operations.customers.documents.viewer', [$customer, $document]).'"', false)
        ->assertSee('x-data="{ emailOpen: false }"', false)
        ->assertSee('@click="emailOpen = ! emailOpen"', false)
        ->assertSee('id="document-email-'.$document->id.'" class="card card-sm"', false)
        ->assertSee('action="'.route('operations.customers.documents.visibility', [$customer, $document]).'"', false)
        ->assertSee('<input type="hidden" name="customer_visible" value="1">', false)
        ->assertSee('Show to Customer')
        ->assertSee('action="'.route('operations.customers.documents.retire', [$customer, $document]).'"', false)
        ->assertSee('onsubmit="return confirm(\'Retire this document? It will leave active lists.\')"', false)
        ->assertSee('<input type="hidden" name="_method" value="DELETE">', false)
        ->assertSee('action="'.route('operations.customers.documents.attach', [$customer, $document]).'"', false)
        ->assertSee('name="repair_order_id" required class="form-select form-select-sm"', false)
        ->assertSee('<option value="'.$repairOrder->id.'">RO #'.$repairOrder->repair_order_id.'</option>', false)
        ->assertSee('data-add-document-panel', false)
        ->assertSee('x-data="arkDocumentAdd({', false)
        ->assertSee('action="'.route('operations.customers.documents.store', $customer).'" enctype="multipart/form-data" data-workspace-modal-form="document-upload"', false)
        ->assertSee('action="'.route('operations.customers.documents.scan', $customer).'" enctype="multipart/form-data" data-workspace-modal-form="document-scan" @submit="prepareScanSubmit($event)"', false)
        ->assertSee('<input type="hidden" name="_document_mode" value="upload">', false)
        ->assertSee('<input type="hidden" name="_document_mode" value="scan">', false)
        ->assertSee('id="document-file" name="file" type="file" required accept="application/pdf,image/jpeg,image/png,image/heic,image/heif,.pdf,.jpg,.jpeg,.png,.heic" class="form-control"', false)
        ->assertSee('id="document-title" name="title"', false)
        ->assertSee('id="document-type" name="type" required class="form-select"', false)
        ->assertSee('id="document-ro" name="repair_order_id" class="form-select"', false)
        ->assertSee('id="document-scan-title" name="title"', false)
        ->assertSee('id="document-scan-type" name="type" required class="form-select"', false)
        ->assertSee('id="document-scan-ro" name="repair_order_id" class="form-select"', false)
        ->assertSee('accept="image/*" capture="environment" class="visually-hidden"', false)
        ->assertSee(':disabled="pages.length === 0"', false)
        ->getContent();

    expect(docsTablerStrayIslands($html, "//*[@id='customer-documents']"))->toBe(0)
        ->and(docsTablerCount($html, "//*[@id='customer-documents' or @data-add-document-panel]//*[contains(@class, 'slate-') or contains(@class, 'rounded-sm')]"))->toBe(0)
        ->and(docsTablerCount($html, "//*[@data-document-list][ancestor::*[contains(concat(' ', normalize-space(@class), ' '), ' ark-legacy ')]]"))->toBe(0)
        ->and(docsTablerCount($html, "//*[@data-add-document-panel][ancestor::*[contains(concat(' ', normalize-space(@class), ' '), ' ark-legacy ')]]"))->toBe(0)
        ->and(docsTablerStrayIslands($html, '//*[@data-add-document-panel]'))->toBe(0);
});

test('the repair order workspace keeps the legacy add-document panel', function (): void {
    $advisor = actingAsLearnCurrentAdvisor();
    [, $repairOrder] = docsTablerCustomer();

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('data-workspace-modal-form="document-upload" class="space-y-3"', false)
        ->assertDontSee('data-add-document-panel', false);
});

test('the estimate document page has a Tabler header around the unchanged document body', function (): void {
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = docsTablerEstimateRepairOrder();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.estimate-documents.store', $repairOrder))
        ->assertRedirect();

    $document = EstimateDocument::query()->where('repair_order_id', $repairOrder->id)->firstOrFail();
    $roNumber = $repairOrder->repair_order_id;

    $html = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.estimate-documents.show', [$repairOrder, $document]))
        ->assertOk()
        ->assertSee('navbar navbar-vertical', false)
        ->assertSee('class="page-title">RO #'.$roNumber.' Estimate</h2>', false)
        ->assertSee('role="group" aria-label="Estimate document commands"', false)
        ->assertSee('Back to RO Review')
        ->assertSee('Edit Estimate')
        ->assertSee('href="'.route('operations.repair-orders.estimate.pdf', $repairOrder).'"', false)
        ->assertSee('target="_blank"', false)
        ->assertSee("x-on:click=\"(\$event.currentTarget.href = '".route('operations.repair-orders.estimate.pdf', $repairOrder)."?t=' + Date.now())\"", false)
        ->assertSee("x-on:click=\"(\$event.currentTarget.href = '".route('operations.repair-orders.estimate.pdf.download', $repairOrder)."?t=' + Date.now())\"", false)
        ->assertSee('class="ark-legacy bg-white text-dark" data-estimate-document-body', false)
        ->assertSee('<article class="bg-white px-4 pb-4 pt-2 ring-1 ring-slate-300 print:p-0 print:ring-0">', false)
        ->assertSee('Front brake grinding')
        ->assertSee('Replace front pads and rotors')
        ->assertSee('$150.00')
        ->assertSee('$225.00')
        ->assertSee('Authorization Gate')
        ->assertDontSee('print:hidden', false)
        ->getContent();

    expect(docsTablerStrayIslands($html, docsTablerPageScope(), 'data-estimate-document-body'))->toBe(0)
        ->and(docsTablerCount($html, docsTablerPageScope()."//*[contains(@class, 'slate-')][not(ancestor-or-self::*[@data-estimate-document-body])]"))->toBe(0)
        ->and(docsTablerCount($html, '//*[@data-estimate-document-body]'))->toBe(1);
});

test('the estimate document page keeps today\'s role access', function (): void {
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = docsTablerEstimateRepairOrder();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.estimate-documents.store', $repairOrder))
        ->assertRedirect();

    $document = EstimateDocument::query()->where('repair_order_id', $repairOrder->id)->firstOrFail();
    $page = route('operations.repair-orders.estimate-documents.show', [$repairOrder, $document]);

    $this->actingAs(actingAsLearnCurrentStaff(ArkRole::Admin))->get($page)->assertOk()->assertSee('Edit Estimate');

    $this->actingAs(actingAsLearnCurrentStaff(ArkRole::Technician))
        ->get($page)
        ->assertOk()
        ->assertSee('Back to RO Review')
        ->assertDontSee('Edit Estimate');

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Customer->value))
        ->get($page)
        ->assertForbidden();
});

test('record testing outcome is a Tabler form with the same fields and route', function (): void {
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForEstimateWorkspace();
    $authorization = app(AuthorizeTestingPackageAction::class)->handle($repairOrder, $advisor);
    $page = route('operations.repair-orders.work-authorization.outcome', [$repairOrder, $authorization]);

    $html = $this->actingAs($advisor)
        ->get($page)
        ->assertOk()
        ->assertSee('navbar navbar-vertical', false)
        ->assertSee('class="page-title">Record Testing Outcome</h2>', false)
        ->assertSee('action="'.route('operations.repair-orders.work-authorization.outcome.store', [$repairOrder, $authorization]).'"', false)
        ->assertSee('method="POST"', false)
        ->assertSee('name="_token"', false)
        ->assertSee('id="outcome"', false)
        ->assertSee('name="outcome"', false)
        ->assertSee('<option value="'.TestingPackageOutcome::NoFaultFound->value.'"', false)
        ->assertSee('id="recommendation"', false)
        ->assertSee('name="recommendation"', false)
        ->assertSee('RO #'.$repairOrder->repair_order_id)
        ->assertSee('href="'.route('operations.repair-orders.show', $repairOrder).'#work-authorization"', false)
        ->assertSee('Save Outcome')
        ->getContent();

    expect(docsTablerStrayIslands($html, docsTablerPageScope()))->toBe(0)
        ->and(docsTablerCount($html, docsTablerPageScope()."//*[contains(@class, 'slate-')]"))->toBe(0);

    $this->actingAs($advisor)
        ->from($page)
        ->followingRedirects()
        ->post(route('operations.repair-orders.work-authorization.outcome.store', [$repairOrder, $authorization]), [
            'outcome' => TestingPackageOutcome::RepairRecommended->value,
        ])
        ->assertOk()
        ->assertSee('class="form-control is-invalid"', false)
        ->assertSee('class="invalid-feedback"', false);

    expect($authorization->fresh()->outcome)->toBeNull();

    $this->actingAs(actingAsLearnCurrentStaff(ArkRole::Admin))->get($page)->assertOk();
    $this->actingAs(actingAsLearnCurrentStaff(ArkRole::Technician))->get($page)->assertForbidden();
});

test('the dealer parts quote is a Tabler page with the same lines and costs', function (): void {
    Storage::fake('local');
    $advisor = actingAsLearnCurrentAdvisor();
    [$repairOrder, $quote] = docsTablerDealerQuote($this, $advisor);
    $page = route('operations.repair-orders.dealer-quotes.show', [$repairOrder, $quote]);

    $html = $this->actingAs($advisor)
        ->get($page)
        ->assertOk()
        ->assertSee('navbar navbar-vertical', false)
        ->assertSee('class="page-pretitle">Dealer Quote</div>', false)
        ->assertSee('href="'.route('operations.repair-orders.show', $repairOrder).'#estimate-lines"', false)
        ->assertSee('Q31100')
        ->assertSee('WVGAV7AX0DW555555')
        ->assertSee('06J-103-603-BD')
        ->assertSee('Upper Oil Sump')
        ->assertSee('$406.08')
        ->assertSee('$6.53')
        ->assertSee('table card-table', false)
        ->assertDontSee('ops-review-action', false)
        ->getContent();

    expect(docsTablerStrayIslands($html, docsTablerPageScope()))->toBe(0)
        ->and(docsTablerCount($html, docsTablerPageScope()."//*[contains(@class, 'slate-')]"))->toBe(0);

    $this->actingAs(actingAsLearnCurrentStaff(ArkRole::Admin))->get($page)->assertOk();
    $this->actingAs(actingAsLearnCurrentStaff(ArkRole::Technician))->get($page)->assertForbidden();
});

test('parts matrix tuning is a Tabler page with the same filters and simulation form', function (): void {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $page = route('operations.owner.parts-matrix-tune');

    $html = $this->actingAs($admin)
        ->get($page)
        ->assertOk()
        ->assertSee('navbar navbar-vertical', false)
        ->assertSee('Matrix tune assistant')
        ->assertSee('Simulate parts matrix changes from closed truth')
        ->assertSee('action="'.$page.'"', false)
        ->assertSee('method="GET"', false)
        ->assertSee('type="date" name="from"', false)
        ->assertSee('type="date" name="to"', false)
        ->assertSee('name="matrix" class="form-select"', false)
        ->assertSee('<input type="hidden" name="simulate" value="1">', false)
        ->assertSee('name="proposed_markup[0]"', false)
        ->assertSee('inputmode="decimal"', false)
        ->assertSee('Refresh sample')
        ->assertSee('Simulate proposed markups')
        ->assertSee('Tier simulation')
        ->assertSee('GP on')
        ->assertSee('href="'.route('operations.owner.day-review').'"', false)
        ->assertSee('href="'.e(route('operations.settings.shop.edit', ['section' => 'financial', 'financialTab' => 'parts'])).'"', false)
        ->getContent();

    expect(docsTablerStrayIslands($html, docsTablerPageScope()))->toBe(0)
        ->and(docsTablerCount($html, docsTablerPageScope()."//*[contains(@class, 'slate-')]"))->toBe(0);

    $this->actingAs(actingAsLearnCurrentAdvisor())->get($page)->assertForbidden();
    $this->actingAs(actingAsLearnCurrentStaff(ArkRole::Technician))->get($page)->assertForbidden();
});
