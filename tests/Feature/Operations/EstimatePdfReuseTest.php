<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\DocumentPdfPath;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Documents\PdfRenderer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');
    CountingEstimatePdfRenderer::$renders = 0;
    $this->app->bind(PdfRenderer::class, CountingEstimatePdfRenderer::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));
});

test('an unchanged living estimate is served from its stored PDF without rendering or rewriting the document', function () {
    $repairOrder = repairOrderForEstimatePdfReuse();

    $this->get(route('operations.repair-orders.estimate.pdf', $repairOrder))->assertOk();
    $document = EstimateDocument::query()->where('repair_order_id', $repairOrder->id)->firstOrFail();
    $before = $document->only(['status', 'snapshot_json', 'refreshed_at', 'pdf_refreshed_at', 'updated_at']);

    $writes = [];
    DB::listen(function ($query) use (&$writes): void {
        if (preg_match('/^\s*(update|insert|delete)\b.*estimate_documents/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });

    $response = $this->get(route('operations.repair-orders.estimate.pdf', $repairOrder));

    $response->assertOk();
    expect($response->getContent())->toContain('Counting render 1')
        ->and(CountingEstimatePdfRenderer::$renders)->toBe(1)
        ->and($writes)->toBe([])
        ->and($document->fresh()->only(['status', 'snapshot_json', 'refreshed_at', 'pdf_refreshed_at', 'updated_at']))->toEqual($before);
});

test('a change to the estimate content renders a new PDF on the next open', function () {
    $repairOrder = repairOrderForEstimatePdfReuse();
    $this->get(route('operations.repair-orders.estimate.pdf', $repairOrder))->assertOk();

    RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->firstOrFail()->update([
        'description' => 'Replace serpentine belt and tensioner',
    ]);

    $this->get(route('operations.repair-orders.estimate.pdf', $repairOrder))->assertOk();

    expect(CountingEstimatePdfRenderer::$renders)->toBe(2);
});

test('an estimate marked dirty renders again even when its content looks the same', function () {
    $repairOrder = repairOrderForEstimatePdfReuse();
    $this->get(route('operations.repair-orders.estimate.pdf', $repairOrder))->assertOk();

    app(EstimateDocumentService::class)->markDirtyForRepairOrder($repairOrder->fresh());
    $this->get(route('operations.repair-orders.estimate.pdf', $repairOrder))->assertOk();
    $this->get(route('operations.repair-orders.estimate.pdf', $repairOrder))->assertOk();

    expect(CountingEstimatePdfRenderer::$renders)->toBe(2);
});

test('a missing stored PDF is rendered again', function () {
    $repairOrder = repairOrderForEstimatePdfReuse();
    $this->get(route('operations.repair-orders.estimate.pdf', $repairOrder))->assertOk();

    $document = EstimateDocument::query()->where('repair_order_id', $repairOrder->id)->firstOrFail();
    Storage::disk('local')->delete($document->pdf_path);

    $this->get(route('operations.repair-orders.estimate.pdf', $repairOrder))->assertOk();

    expect(CountingEstimatePdfRenderer::$renders)->toBe(2);
    Storage::disk('local')->assertExists($document->fresh()->pdf_path);
});

function repairOrderForEstimatePdfReuse(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Dana',
        'last_name' => 'Reuse',
        'phone' => '5550100199',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2016,
        'make' => 'Toyota',
        'model' => 'Tacoma',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Squeal on startup.',
    ]);
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Belt squeal',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'position' => 1,
    ]);
    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace serpentine belt',
        'quantity' => 1,
        'unit_price_cents' => 12000,
        'subtotal_cents' => 12000,
    ]);

    return $repairOrder->fresh();
}

class CountingEstimatePdfRenderer implements PdfRenderer
{
    public static int $renders = 0;

    public function renderEstimate(EstimateDocument $document): string
    {
        self::$renders++;
        $path = DocumentPdfPath::for($document);
        Storage::disk('local')->put($path, "%PDF-1.4\nCounting render ".self::$renders."\n");
        $document->forceFill([
            'status' => $document->isInvoice() ? $document->status : 'generated',
            'pdf_path' => $path,
            'generated_at' => $document->generated_at ?? now(),
            'needs_pdf_refresh' => false,
            'pdf_refreshed_at' => now(),
        ])->save();

        return $path;
    }
}
