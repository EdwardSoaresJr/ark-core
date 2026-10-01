<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Financial\InvoiceStatus;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Ark\Operations\Reports\OperationalReportTotals;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Support\Carbon;

test('a historical invoice keeps its posted total after the ticket changes', function () {
    [$from, $to] = OperationalReportDateScope::resolveRange('2026-06-08', '2026-06-08');
    $repairOrder = historicalPostedRepairOrder($from);
    $approved = historicalConcern($repairOrder, RepairOrderConcernDisposition::Approved, 'Front brakes');
    $labor = historicalLine($repairOrder, $approved, RepairOrderLineType::Labor, 10_000);

    $document = EstimateDocument::query()->create([
        'repair_order_id' => $repairOrder->id,
        'document_type' => FinancialDocumentType::Invoice,
        'document_number' => 1699,
        'legacy_arksms_invoice_id' => 8801699,
        'status' => InvoiceStatus::Issued->value,
        'snapshot_json' => [
            'schema_version' => 'legacy_import',
            'totals' => [
                'total_cents' => 16_420,
                'tax_cents' => 1_120,
            ],
        ],
        'generated_at' => $from,
    ]);

    $labor->update(['subtotal_cents' => 50_000, 'total_cents' => 50_000]);
    $recommended = historicalConcern($repairOrder, RepairOrderConcernDisposition::Recommended, 'Added later');
    historicalLine($repairOrder, $recommended, RepairOrderLineType::Labor, 39_161);

    $figures = OperationalReportTotals::postedInvoiceFigures($from, $to);

    expect(OperationalReportTotals::postedSalesCents([$repairOrder->id]))->toBe(16_420)
        ->and($figures['sales_cents'])->toBe(15_300)
        ->and($figures['tax_cents'])->toBe(1_120)
        ->and($figures['total_cents'])->toBe(16_420)
        ->and($figures['lines_match'])->toBeFalse()
        ->and($document->fresh()->snapshot_json['totals'])->toBe([
            'total_cents' => 16_420,
            'tax_cents' => 1_120,
        ])
        ->and($recommended->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Recommended);
});

test('a stored pre-tax invoice amount is used when today lines still match it', function () {
    [$from, $to] = OperationalReportDateScope::resolveRange('2026-06-09', '2026-06-09');
    $repairOrder = historicalPostedRepairOrder($from, 1701);
    $approved = historicalConcern($repairOrder, RepairOrderConcernDisposition::Approved, 'Oil service');
    historicalLine($repairOrder, $approved, RepairOrderLineType::Labor, 15_000);

    EstimateDocument::query()->create([
        'repair_order_id' => $repairOrder->id,
        'document_type' => FinancialDocumentType::Invoice,
        'document_number' => 1701,
        'status' => InvoiceStatus::Issued->value,
        'snapshot_json' => [
            'totals' => [
                'subtotal_before_tax_cents' => 15_000,
                'tax_cents' => 1_200,
                'total_cents' => 16_200,
            ],
        ],
        'generated_at' => $from,
    ]);

    $figures = OperationalReportTotals::postedInvoiceFigures($from, $to);

    expect(OperationalReportTotals::postedSalesCents([$repairOrder->id]))->toBe(16_200)
        ->and($figures['sales_cents'])->toBe(15_000)
        ->and($figures['tax_cents'])->toBe(1_200)
        ->and($figures['total_cents'])->toBe(16_200)
        ->and($figures['lines_match'])->toBeTrue();
});

function historicalPostedRepairOrder(Carbon $from, int $number = 1699): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Historical',
        'last_name' => 'Invoice',
        'phone' => '555'.$number,
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2014,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);

    return RepairOrder::query()->create([
        'repair_order_id' => $number,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Invoiced,
        'concern_summary' => 'Historical posted invoice.',
        'opened_at' => $from->copy()->addHour(),
        'posted_at' => $from->copy()->addHours(4),
    ]);
}

function historicalConcern(RepairOrder $repairOrder, RepairOrderConcernDisposition $disposition, string $summary): RepairOrderConcern
{
    return RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => $summary,
        'disposition' => $disposition,
        'position' => $disposition === RepairOrderConcernDisposition::Approved ? 1 : 2,
    ]);
}

function historicalLine(RepairOrder $repairOrder, RepairOrderConcern $concern, RepairOrderLineType $type, int $cents): RepairOrderLine
{
    return RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => $type,
        'description' => $concern->summary,
        'quantity' => '1.00',
        'unit_price_cents' => $cents,
        'subtotal_cents' => $cents,
        'total_cents' => $cents,
    ]);
}
