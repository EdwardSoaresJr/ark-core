<?php

use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderEstimateChanged;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\ScopeProductionStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Http\Middleware\CommitRepairOrderEstimateLock;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpFoundation\Response;

test('a failed mutation does not keep the line change, advance the version, or broadcast', function () {
    config(['broadcasting.default' => 'log']);
    Event::fake([RepairOrderEstimateChanged::class]);

    [$repairOrder, $concern, $line] = concurrencyRepairOrderFixture();
    $openedVersion = app(RepairOrderConcurrency::class)->openedVersion($repairOrder);
    $request = Request::create('/overlap-rollback', 'POST', [
        RepairOrderConcurrency::FIELD => $openedVersion,
    ]);

    expect(fn () => app(CommitRepairOrderEstimateLock::class)->handle($request, function () use ($request, $repairOrder, $line): Response {
        app(RepairOrderConcurrency::class)->guard($request, $repairOrder);
        $line->update(['description' => 'Should roll back']);
        app(\App\Ark\Operations\RepairOrders\RepairOrderEstimateVersion::class)->bump($repairOrder->fresh());

        throw new RuntimeException('domain mutation failed');
    }))->toThrow(RuntimeException::class, 'domain mutation failed');

    expect($line->fresh()->description)->toBe('Initial diagnostic labor')
        ->and($repairOrder->fresh()->estimate_version)->toBe($openedVersion)
        ->and(Event::dispatched(RepairOrderEstimateChanged::class))->toBeEmpty();
});

test('current tokens still save labor, part, concern, disposition, and production', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    [$repairOrder, $concern, $line] = concurrencyRepairOrderFixture();
    $part = concurrencyRepairOrderFixture(RepairOrderLineType::Part);
    $partOrder = $part[0];
    $partConcern = $part[1];
    $partLine = $part[2];

    $laborVersion = app(RepairOrderConcurrency::class)->openedVersion($repairOrder);
    $this->patch(route('operations.repair-orders.lines.update', [$repairOrder, $line]), [
        RepairOrderConcurrency::FIELD => $laborVersion,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Labor after lock',
        'quantity' => '1.00',
        'unit_price' => '80.00',
    ])->assertRedirect();

    $partVersion = app(RepairOrderConcurrency::class)->openedVersion($partOrder);
    $this->patch(route('operations.repair-orders.lines.update', [$partOrder, $partLine]), [
        RepairOrderConcurrency::FIELD => $partVersion,
        'repair_order_concern_id' => $partConcern->id,
        'type' => RepairOrderLineType::Part->value,
        'description' => 'Part after lock',
        'quantity' => '1.00',
        'part_cost' => '40.00',
        'unit_price' => '90.00',
    ])->assertRedirect();

    $concernVersion = app(RepairOrderConcurrency::class)->openedVersion($repairOrder->fresh());
    $this->patch(route('operations.repair-orders.concerns.update', [$repairOrder, $concern]), [
        RepairOrderConcurrency::FIELD => $concernVersion,
        'summary' => 'Concern after lock',
        'recommendation_intent' => 'maintenance',
    ])->assertRedirect();

    $productionVersion = app(RepairOrderConcurrency::class)->openedVersion($repairOrder->fresh());
    $this->patch(route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]), [
        RepairOrderConcurrency::FIELD => $productionVersion,
        'production_status' => ScopeProductionStatus::InProgress->value,
    ])->assertRedirect();

    expect($concern->fresh()->production_status)->toBe(ScopeProductionStatus::InProgress);

    $dispositionVersion = app(RepairOrderConcurrency::class)->openedVersion($repairOrder->fresh());
    $this->patch(route('operations.repair-orders.concerns.disposition', [$repairOrder, $concern]), [
        RepairOrderConcurrency::FIELD => $dispositionVersion,
        'disposition' => RepairOrderConcernDisposition::Deferred->value,
    ])->assertRedirect();

    expect($line->fresh()->description)->toBe('Labor after lock')
        ->and($partLine->fresh()->description)->toBe('Part after lock')
        ->and($concern->fresh()->summary)->toBe('Concern after lock')
        ->and($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Deferred)
        ->and($repairOrder->fresh()->estimate_version)->toBeGreaterThan($laborVersion);
});
