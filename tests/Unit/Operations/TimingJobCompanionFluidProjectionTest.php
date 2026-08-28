<?php

use App\Ark\Operations\RepairOrders\EstimateCompanionCompletenessProjection;
use App\Ark\Operations\RepairOrders\LearnEstimateCompanionPatternsAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\TimingJobCompanionFluidProjection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function timingJobOrder(string $laborDescription, array $extraLineDescriptions = [], string $concernSummary = 'Timing belt'): RepairOrder
{
    $concern = new RepairOrderConcern([
        'summary' => $concernSummary,
        'recommendation' => '',
    ]);

    $lines = collect([
        new RepairOrderLine([
            'type' => RepairOrderLineType::Labor,
            'description' => $laborDescription,
            'customer_description' => '',
        ]),
    ]);

    foreach ($extraLineDescriptions as $description) {
        $lines->push(new RepairOrderLine([
            'type' => RepairOrderLineType::Part,
            'description' => $description,
            'customer_description' => '',
        ]));
    }

    $repairOrder = new RepairOrder([
        'concern_summary' => $concernSummary,
    ]);
    $repairOrder->setRelation('concerns', collect([$concern]));
    $repairOrder->setRelation('lines', $lines);

    return $repairOrder;
}

test('timing belt without oil and coolant needs attention from the shop catalog', function () {
    $projection = (new EstimateCompanionCompletenessProjection)->for(
        timingJobOrder('Replace timing belt and water pump'),
    );

    expect($projection['needs_attention'])->toBeTrue()
        ->and($projection['missing'])->toBe(['oil', 'coolant'])
        ->and($projection['headline'])->toContain('oil and coolant');
});

test('timing job with oil and coolant is complete', function () {
    $projection = (new EstimateCompanionCompletenessProjection)->for(
        timingJobOrder('Replace timing belt', [
            'Engine oil 5W-30',
            'Coolant — Dex-Cool',
        ]),
    );

    expect($projection['needs_attention'])->toBeFalse()
        ->and($projection['missing'])->toBe([]);
});

test('oil leak and coolant leak findings do not count as fluids', function () {
    $projection = (new EstimateCompanionCompletenessProjection)->for(
        timingJobOrder('Timing chain kit', [
            'Oil leak at valve cover',
            'Coolant leak at hose',
        ]),
    );

    expect($projection['needs_attention'])->toBeTrue()
        ->and($projection['missing'])->toBe(['oil', 'coolant']);
});

test('ignition timing is not a timing job', function () {
    $projection = (new TimingJobCompanionFluidProjection)->for(
        timingJobOrder('Set ignition timing', [], 'Ignition timing'),
    );

    expect($projection['is_timing_job'])->toBeFalse()
        ->and($projection['needs_attention'])->toBeFalse();
});

test('repeated labor-plus-part tickets teach a new companion', function () {
    $learn = app(LearnEstimateCompanionPatternsAction::class);

    $first = repairOrderForCommunication(\App\Ark\Operations\RepairOrders\RepairOrderStatus::Estimate);
    $first->lines()->update(['description' => 'Replace wheel bearing']);
    $first->lines()->create([
        'repair_order_concern_id' => $first->concerns()->first()->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Gear oil',
        'quantity' => '1.00',
        'unit_price_cents' => 2500,
        'subtotal_cents' => 2500,
        'total_cents' => 2500,
        'position' => 2,
    ]);
    $learn->ingest($first->fresh(['lines', 'concerns']));

    $second = repairOrderForCommunication(\App\Ark\Operations\RepairOrders\RepairOrderStatus::Estimate);
    $second->lines()->update(['description' => 'Replace wheel bearing']);
    $second->lines()->create([
        'repair_order_concern_id' => $second->concerns()->first()->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Gear oil',
        'quantity' => '1.00',
        'unit_price_cents' => 2500,
        'subtotal_cents' => 2500,
        'total_cents' => 2500,
        'position' => 2,
    ]);
    $learn->ingest($second->fresh(['lines', 'concerns']));

    $third = timingJobOrder('Replace wheel bearing');
    $projection = (new EstimateCompanionCompletenessProjection)->for($third);

    expect($projection['needs_attention'])->toBeTrue()
        ->and($projection['missing'])->toContain('gear');
});
