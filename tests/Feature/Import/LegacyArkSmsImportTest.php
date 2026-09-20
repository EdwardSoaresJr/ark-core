<?php

use App\Ark\Import\ArrayLegacyArkSmsReader;
use App\Ark\Import\LegacyArkSmsImporter;
use App\Ark\Import\LegacyArkSmsValueMapper;
use App\Ark\Import\LegacyImportOptions;
use App\Ark\Import\LegacyImportReport;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\PdfRenderer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

function legacyReaderFixture(): ArrayLegacyArkSmsReader
{
    return new ArrayLegacyArkSmsReader([
        'customers' => [
            [
                'id' => 101,
                'first_name' => 'Legacy',
                'last_name' => 'Customer',
                'phone' => '(719) 555-0101',
                'email' => 'legacy@example.test',
                'customer_type' => 'Retail',
                'notes' => 'VIP',
                'created_at' => '2024-01-01 10:00:00',
                'updated_at' => '2024-06-01 10:00:00',
            ],
        ],
        'vehicles' => [
            [
                'id' => 201,
                'customer_id' => 101,
                'vin' => null,
                'plate' => 'LEG101',
                'plate_state' => 'CO',
                'year' => 2015,
                'make' => 'Subaru',
                'model' => 'Outback',
                'trim' => 'Premium',
                'engine' => '2.5L',
                'transmission' => 'Automatic',
                'drive' => 'AWD',
                'color' => 'Green',
                'nickname' => 'Green Machine',
                'mileage' => 145000,
                'created_at' => '2024-01-02 10:00:00',
                'updated_at' => '2024-06-02 10:00:00',
            ],
        ],
        'repair_orders' => [
            [
                'id' => 301,
                'customer_id' => 101,
                'vehicle_id' => 201,
                'status' => 'invoiced',
                'concern_summary' => 'Brake noise at low speed.',
                'mileage_in' => 165604,
                'mileage_out' => 165892,
                'payment_status' => 'paid',
                'paid_at' => '2024-06-10 15:00:00',
                'created_at' => '2024-06-03 09:00:00',
                'updated_at' => '2024-06-10 15:00:00',
                'closed_at' => '2024-06-10 14:30:00',
            ],
        ],
        'concerns' => [
            [
                'id' => 401,
                'repair_order_id' => 301,
                'summary' => 'Brake noise',
                'notes' => 'Front pads low',
                'disposition' => 'approved',
                'position' => 0,
            ],
        ],
        'lines' => [
            [
                'id' => 501,
                'repair_order_id' => 301,
                'concern_id' => 401,
                'type' => 'labor',
                'description' => 'Brake inspection',
                'quantity' => 1,
                'unit_price' => 120.00,
                'subtotal' => 120.00,
                'tax' => 9.60,
                'shop_fee' => 0,
                'total' => 129.60,
            ],
            [
                'id' => 502,
                'repair_order_id' => 301,
                'concern_id' => 401,
                'type' => 'part',
                'description' => 'Brake pads',
                'quantity' => 1,
                'unit_price' => 89.99,
                'part_cost' => 45.00,
                'subtotal' => 89.99,
                'tax' => 7.20,
                'shop_fee' => 5.00,
                'total' => 102.19,
            ],
            [
                'id' => 503,
                'repair_order_id' => 301,
                'concern_id' => 401,
                'type' => 'sublet',
                'description' => 'Alignment at tire shop',
                'quantity' => 1,
                'unit_price' => 95.00,
                'subtotal' => 95.00,
                'tax' => 7.60,
                'shop_fee' => 0,
                'total' => 102.60,
            ],
        ],
        'invoices' => [
            [
                'id' => 601,
                'repair_order_id' => 301,
                'invoice_number' => 9001,
                'subtotal' => 209.99,
                'tax' => 16.80,
                'shop_fee' => 5.00,
                'total' => 231.79,
                'created_at' => '2024-06-10 15:00:00',
            ],
        ],
    ]);
}

function legacyImporter(): LegacyArkSmsImporter
{
    return new LegacyArkSmsImporter(
        legacyReaderFixture(),
        new LegacyArkSmsValueMapper,
    );
}

test('dry run does not mutate ark v2 database', function () {
    $report = new LegacyImportReport;
    legacyImporter()->run(new LegacyImportOptions(dryRun: true), $report);

    expect(Customer::query()->count())->toBe(0)
        ->and(Vehicle::query()->count())->toBe(0)
        ->and(RepairOrder::query()->count())->toBe(0)
        ->and($report->customersImported)->toBe(1)
        ->and($report->repairOrdersImported)->toBe(1)
        ->and($report->dryRun)->toBeTrue();
});

test('customer import preserves legacy id and normalizes phone', function () {
    $report = new LegacyImportReport;
    legacyImporter()->run(new LegacyImportOptions(dryRun: false), $report);

    $customer = Customer::query()->where('legacy_arksms_customer_id', 101)->first();

    expect($customer)->not->toBeNull()
        ->and($customer->phone)->toBe('7195550101')
        ->and($customer->first_name)->toBe('Legacy');
});

test('vehicle import tolerates missing vin and stores mileage in private notes', function () {
    legacyImporter()->run(new LegacyImportOptions(dryRun: false), new LegacyImportReport);

    $vehicle = Vehicle::query()->where('legacy_arksms_vehicle_id', 201)->first();

    expect($vehicle)->not->toBeNull()
        ->and($vehicle->vin)->toBeNull()
        ->and($vehicle->private_notes)->toContain('Legacy odometer: 145000');
});

test('repair order import preserves legacy status verbatim and line totals', function () {
    legacyImporter()->run(new LegacyImportOptions(dryRun: false), new LegacyImportReport);

    $repairOrder = RepairOrder::query()->where('repair_order_id', 301)->first();
    $labor = RepairOrderLine::query()->where('legacy_arksms_line_id', 501)->first();

    expect($repairOrder)->not->toBeNull()
        ->and($repairOrder->status->is(RepairOrderStatus::Invoiced))->toBeTrue()
        ->and($repairOrder->mileage_in)->toBe(165604)
        ->and($repairOrder->mileage_out)->toBe(165892)
        ->and($repairOrder->opened_at?->toDateTimeString())->toBe('2024-06-03 09:00:00')
        ->and($repairOrder->closed_at?->toDateTimeString())->toBe('2024-06-10 14:30:00')
        ->and($labor)->not->toBeNull()
        ->and($labor->unit_price_cents)->toBe(12000)
        ->and($labor->total_cents)->toBe(12960)
        ->and($labor->is_overridden)->toBeTrue();
});

test('invoice import preserves legacy totals in snapshot', function () {
    legacyImporter()->run(new LegacyImportOptions(dryRun: false), new LegacyImportReport);

    $document = EstimateDocument::query()->where('legacy_arksms_invoice_id', 601)->first();

    expect($document)->not->toBeNull()
        ->and($document->snapshot_json['schema_version'])->toBe('legacy_import')
        ->and($document->snapshot_json['totals']['total_cents'])->toBe(23179);
});

test('legacy imported estimate can render pdf without overwriting imported snapshot', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');

    $this->app->bind(PdfRenderer::class, function (): PdfRenderer {
        return new class implements PdfRenderer
        {
            public function renderEstimate(EstimateDocument $document): string
            {
                $path = 'estimate-documents/ro-'.$document->repair_order_id.'/current-estimate.pdf';
                Storage::disk('local')->put($path, '%PDF-fake');

                $document->forceFill([
                    'status' => 'generated',
                    'pdf_path' => $path,
                    'generated_at' => now(),
                    'needs_pdf_refresh' => false,
                    'pdf_refreshed_at' => now(),
                ])->save();

                return $path;
            }
        };
    });
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    legacyImporter()->run(new LegacyImportOptions(dryRun: false), new LegacyImportReport);

    $repairOrder = RepairOrder::query()->where('repair_order_id', 301)->firstOrFail();
    $document = EstimateDocument::query()->where('legacy_arksms_invoice_id', 601)->firstOrFail();
    $importedSnapshot = $document->snapshot_json;

    $this->get(route('operations.repair-orders.estimate.pdf', $repairOrder))
        ->assertOk();

    $document->refresh();

    expect($document->snapshot_json)->toBe($importedSnapshot)
        ->and($document->status)->toBe('generated')
        ->and($document->pdf_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($document->pdf_path))->toBeTrue();
});

test('running importer twice is idempotent', function () {
    $importer = legacyImporter();

    $importer->run(new LegacyImportOptions(dryRun: false), new LegacyImportReport);
    $importer->run(new LegacyImportOptions(dryRun: false), new LegacyImportReport);

    expect(Customer::query()->where('legacy_arksms_customer_id', 101)->count())->toBe(1)
        ->and(RepairOrder::query()->where('repair_order_id', 301)->count())->toBe(1)
        ->and(RepairOrderLine::query()->where('legacy_arksms_line_id', 501)->count())->toBe(1);
});

test('legacy sublet lines import as sublet with service document label', function () {
    legacyImporter()->run(new LegacyImportOptions(dryRun: false), new LegacyImportReport);

    $sublet = RepairOrderLine::query()->where('legacy_arksms_line_id', 503)->first();

    expect($sublet)->not->toBeNull()
        ->and($sublet->type)->toBe(RepairOrderLineType::Sublet)
        ->and($sublet->type->staffLabel())->toBe('Sublet (Service)')
        ->and($sublet->type->documentLabel())->toBe('Service')
        ->and($sublet->total_cents)->toBe(10260);
});

test('customer type mapper preserves legacy customer type names', function () {
    $mapper = new LegacyArkSmsValueMapper;

    expect($mapper->mapCustomerType('RepairPal'))->toBe('Warranty')
        ->and($mapper->mapCustomerType('Military'))->toBe('Military')
        ->and($mapper->mapCustomerType('Business'))->toBe('Business');
});

test('status mapper preserves mapped legacy slugs and logs unknowns as closed', function () {
    $mapper = new LegacyArkSmsValueMapper;
    $report = new LegacyImportReport;

    expect($mapper->mapRepairOrderStatus('waiting_approval', $report))
        ->toBe(RepairOrderStatus::WaitingApproval)
        ->and($mapper->mapRepairOrderStatus('in_progress', $report))
        ->toBe(RepairOrderStatus::InProgress);

    $unknown = $mapper->mapRepairOrderStatus('mystery_shop_state', $report);

    expect($unknown)->toBe(RepairOrderStatus::Closed)
        ->and($report->unmappedStatuses)->toContain('mystery_shop_state');
});

test('import command is registered with safe dry-run default', function () {
    $command = Artisan::all()['ark:import-legacy-arksms'] ?? null;

    expect($command)->not->toBeNull()
        ->and($command->getDefinition()->getOption('dry-run'))->not->toBeNull()
        ->and($command->getDefinition()->getOption('force'))->not->toBeNull();
});

test('import writes report json to storage', function () {
    Storage::fake('local');

    legacyImporter()->run(new LegacyImportOptions(dryRun: true), new LegacyImportReport);

    Storage::disk('local')->assertExists(config('legacy-arksms-import.report_path'));
});
