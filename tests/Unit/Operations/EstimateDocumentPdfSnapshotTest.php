<?php

use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\EstimateDocumentPdfSnapshot;
use App\Ark\Operations\Documents\EstimateSnapshotBuilder;
use App\Ark\Operations\Documents\InvoicePdfFinancialSnapshot;
use App\Ark\Operations\RepairOrders\ApprovalForecastProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Tests\TestCase;


it('builds a renderable snapshot from live repair order when import snapshot is minimal', function (): void {
    $repairOrder = new RepairOrder;
    $repairOrder->id = 1;
    $repairOrder->repair_order_id = 88001;

    $document = new EstimateDocument([
        'repair_order_id' => 1,
        'snapshot_json' => [
            'schema_version' => 'legacy_import',
            'repair_order_id' => 88001,
            'legacy_invoice_id' => 9,
            'totals' => ['total_cents' => 99900],
        ],
    ]);
    $document->setRelation('repairOrder', $repairOrder);

    $builder = Mockery::mock(EstimateSnapshotBuilder::class);
    $builder->shouldReceive('build')
        ->once()
        ->with($repairOrder, null)
        ->andReturn([
            'schema_version' => 2,
            'repair_order' => ['repair_order_id' => 88001],
            'totals' => ['total_cents' => 120000],
        ]);

    $resolved = (new EstimateDocumentPdfSnapshot($builder, app(InvoicePdfFinancialSnapshot::class), app(ApprovalForecastProjection::class)))->resolve($document);

    expect($resolved['repair_order']['repair_order_id'])->toBe(88001)
        ->and($resolved['totals']['total_cents'])->toBe(99900);
});

it('builds estimate pdf data from live repair order while estimate is still open', function (): void {
    $repairOrder = new RepairOrder([
        'status' => RepairOrderStatus::Estimate,
        'estimate_version' => 3,
    ]);
    $repairOrder->id = 1;
    $repairOrder->repair_order_id = 88001;

    $document = new EstimateDocument([
        'document_type' => 'estimate',
        'repair_order_id' => 1,
        'snapshot_json' => [
            'schema_version' => 2,
            'repair_order' => ['repair_order_id' => 88001],
            'concerns' => [['id' => 9, 'summary' => 'Stale concern']],
            'totals' => ['total_cents' => 10000],
        ],
    ]);
    $document->setRelation('repairOrder', $repairOrder);

    $builder = Mockery::mock(EstimateSnapshotBuilder::class);
    $builder->shouldReceive('build')
        ->once()
        ->with($repairOrder, null)
        ->andReturn([
            'schema_version' => 2,
            'repair_order' => ['repair_order_id' => 88001],
            'concerns' => [['id' => 9, 'summary' => 'Live concern']],
            'totals' => ['total_cents' => 25000],
        ]);

    $resolved = (new EstimateDocumentPdfSnapshot($builder, app(InvoicePdfFinancialSnapshot::class), app(ApprovalForecastProjection::class)))->resolve($document);

    expect($resolved['concerns'][0]['summary'])->toBe('Live concern')
        ->and($resolved['totals']['total_cents'])->toBe(25000);
});
