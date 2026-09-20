<?php

use App\Ark\Operations\RepairOrders\EstimateCompanionCompletenessProjection;
use App\Ark\Operations\RepairOrders\EstimateCompanionPattern;
use App\Ark\Operations\RepairOrders\LearnEstimateCompanionPatternsAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\TimingJobCompanionFluidProjection;
use Tests\TestCase;


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

function teachCompanion(string $laborDescription, string $partDescription, int $times = 3, bool $sameConcern = true): void
{
    $learn = app(LearnEstimateCompanionPatternsAction::class);

    foreach (range(1, $times) as $_) {
        $ro = repairOrderForCommunication(\App\Ark\Operations\RepairOrders\RepairOrderStatus::Estimate);
        $ro->lines()->update(['description' => $laborDescription]);

        $concernId = $ro->concerns()->first()->id;
        if (! $sameConcern) {
            $other = $ro->concerns()->create([
                'summary' => 'Unrelated concern',
                'disposition' => \App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition::Recommended,
                'position' => 2,
            ]);
            $concernId = $other->id;
        }

        $ro->lines()->create([
            'repair_order_concern_id' => $concernId,
            'type' => RepairOrderLineType::Part,
            'description' => $partDescription,
            'quantity' => '1.00',
            'unit_price_cents' => 2500,
            'subtotal_cents' => 2500,
            'total_cents' => 2500,
            'position' => 2,
        ]);
        $learn->ingest($ro->fresh(['lines', 'concerns']));
    }
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

test('repeated labor-plus-part tickets teach a new companion at the support floor', function () {
    teachCompanion('Replace wheel bearing', 'Gear oil', times: 3);

    $projection = (new EstimateCompanionCompletenessProjection)->for(
        timingJobOrder('Replace wheel bearing', [], 'Wheel bearing'),
    );

    expect($projection['needs_attention'])->toBeTrue()
        ->and($projection['missing'])->toContain('oil');
});

test('observed companions below the support floor do not surface', function () {
    teachCompanion('Replace wheel bearing', 'Gear oil', times: 2);

    $projection = (new EstimateCompanionCompletenessProjection)->for(
        timingJobOrder('Replace wheel bearing', [], 'Wheel bearing'),
    );

    expect($projection['missing'])->not->toContain('oil')
        ->and($projection['missing'])->not->toContain('gear');
});

test('companions on a different concern do not teach the labor job', function () {
    teachCompanion('Replace timing belt', 'Brake pads', times: 3, sameConcern: false);

    $projection = (new EstimateCompanionCompletenessProjection)->for(
        timingJobOrder('Replace timing belt'),
    );

    expect($projection['missing'])->not->toContain('brake')
        ->and($projection['missing'])->toContain('oil');
});

test('junk companion text is not learned', function () {
    teachCompanion('Replace alternator', 'Customer provided alternator', times: 3);

    expect(
        EstimateCompanionPattern::query()
            ->where('source', 'observed')
            ->get()
            ->contains(fn (EstimateCompanionPattern $pattern): bool => str_contains(
                mb_strtolower((string) $pattern->companion_label.' '.implode(' ', $pattern->companion_needles ?? [])),
                'customer',
            ))
    )->toBeFalse();
});

test('hardware-only companions are not learned', function () {
    teachCompanion('Replace control arm', 'Bolts', times: 3);

    expect(
        EstimateCompanionPattern::query()
            ->where('source', 'observed')
            ->where('companion_label', 'bolts')
            ->exists()
    )->toBeFalse();
});