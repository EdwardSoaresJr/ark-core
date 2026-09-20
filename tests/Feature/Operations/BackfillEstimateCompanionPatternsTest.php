<?php

use App\Ark\Operations\RepairOrders\BackfillEstimateCompanionPatternsAction;
use App\Ark\Operations\RepairOrders\EstimateCompanionCompletenessProjection;
use App\Ark\Operations\RepairOrders\EstimateCompanionPattern;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Illuminate\Support\Facades\Artisan;

test('estimate companion backfill learns from closed tickets', function () {
    foreach (range(1, 3) as $_) {
        $ro = repairOrderForCommunication(status: RepairOrderStatus::Closed);
        $ro->forceFill(['posted_at' => now()])->save();
        $ro->lines()->update(['description' => 'Replace wheel bearing']);
        $ro->lines()->create([
            'repair_order_concern_id' => $ro->concerns()->first()->id,
            'type' => RepairOrderLineType::Part,
            'description' => 'Gear oil',
            'quantity' => '1.00',
            'unit_price_cents' => 2500,
            'subtotal_cents' => 2500,
            'total_cents' => 2500,
            'position' => 2,
        ]);
    }

    $result = app(BackfillEstimateCompanionPatternsAction::class)->execute(fresh: true);

    expect($result['ingested'])->toBe(3)
        ->and($result['patterns_after'])->toBeGreaterThanOrEqual(3);

    $open = repairOrderForCommunication(status: RepairOrderStatus::Estimate);
    $open->lines()->update(['description' => 'Replace wheel bearing']);

    $projection = (new EstimateCompanionCompletenessProjection)->for($open->fresh(['lines', 'concerns']));

    expect($projection['needs_attention'])->toBeTrue()
        ->and($projection['missing'])->toContain('oil');
});

test('estimate companion backfill artisan dry-run does not write', function () {
    $before = EstimateCompanionPattern::query()->count();

    Artisan::call('ark:estimate-companions:backfill', ['--dry-run' => true]);

    expect(EstimateCompanionPattern::query()->count())->toBe($before);
});
