<?php

use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\Recommendations\Recommendation;
use App\Ark\Operations\Recommendations\RecommendationEvent;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('backfill creates open recommendations without inventing decline events', function () {
    $repairOrder = workspaceTabRepairOrder();
    $repairOrder->concerns->first()->update([
        'disposition' => RepairOrderConcernDisposition::Declined,
        'summary' => 'Rear brakes',
    ]);

    $this->artisan('ark:recommendations:backfill')->assertSuccessful();

    $recommendation = Recommendation::query()
        ->where('originating_repair_order_concern_id', $repairOrder->concerns->first()->id)
        ->first();

    expect($recommendation)->not->toBeNull()
        ->and($recommendation->title)->toBe('Rear brakes')
        ->and($recommendation->lifecycle->value)->toBe('open')
        ->and(RecommendationEvent::query()->where('recommendation_id', $recommendation->id)->count())->toBe(0);

    $this->artisan('ark:recommendations:backfill')->assertSuccessful();

    expect(Recommendation::query()->count())->toBe(1);
});
