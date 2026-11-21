<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\ScopeProductionStatus;
use App\Ark\Operations\RepairOrders\UpdateConcernDispositionAction;
use App\Ark\Operations\Reports\OperationalReportTotals;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\ShopExcellence\PartsMatrixTune\PartsMatrixHistoricalSampler;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Synthetic volume smoke for #235 report paths. Writes JSON metrics under storage/app for the
 * acceptance report; asserts correctness on a known raised-line fixture inside the same seed.
 */
beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'default_labor_rate_cents' => 10000,
    ]);
});

test('financial report paths stay correct and record query/memory/runtime at synthetic volume', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $tech = User::factory()->create()->assignRole(ArkRole::Technician->value);

    $openIds = [];
    $closedIds = [];
    $expectedSold = 0;
    $expectedLabor = 0;

    // 120 open/active-style ROs with approved concern + later price raise (integrity case).
    for ($i = 0; $i < 120; $i++) {
        [$repairOrder, $soldCents, $laborCents] = finPerfSeedRaisedRo($advisor, open: true, technicianId: null, index: $i);
        $openIds[] = (int) $repairOrder->id;
        $expectedSold += $soldCents;
        $expectedLabor += $laborCents;
    }

    // 40 closed/posted ROs in a 30-day window for labor-by-tech + parts matrix.
    $from = Carbon::parse('2026-10-01 00:00:00', config('app.timezone'));
    $to = Carbon::parse('2026-10-31 23:59:59', config('app.timezone'));
    for ($i = 0; $i < 40; $i++) {
        [$repairOrder] = finPerfSeedRaisedRo(
            $advisor,
            open: false,
            technicianId: (int) $tech->id,
            index: 1000 + $i,
            postedAt: $from->copy()->addDays($i % 28)->setTime(14, 0),
        );
        $closedIds[] = (int) $repairOrder->id;
    }

    $allIds = array_merge($openIds, $closedIds);
    $matrixKey = ShopSettings::current()->defaultPartsMatrix()['key'];

    $metrics = [];

    $metrics['sold'] = finPerfMeasure(function () use ($openIds) {
        return OperationalReportTotals::soldTotalCentsByRepairOrderId($openIds)->sum();
    });
    $metrics['labor'] = finPerfMeasure(function () use ($openIds) {
        return OperationalReportTotals::laborCentsByRepairOrderId($openIds)->sum();
    });
    $metrics['sold_then_labor_same_ids'] = finPerfMeasure(function () use ($openIds) {
        $sold = OperationalReportTotals::soldTotalCentsByRepairOrderId($openIds)->sum();
        $labor = OperationalReportTotals::laborCentsByRepairOrderId($openIds)->sum();

        return ['sold' => $sold, 'labor' => $labor];
    });
    $metrics['closed_labor_by_tech'] = finPerfMeasure(function () use ($from, $to) {
        return OperationalReportTotals::closedLaborSalesCentsByTechnicianId($from, $to)->all();
    });
    $metrics['parts_matrix_sample'] = finPerfMeasure(function () use ($from, $to, $matrixKey) {
        return app(PartsMatrixHistoricalSampler::class)->sample($from, $to, $matrixKey)->count();
    });

    $soldById = OperationalReportTotals::soldTotalCentsByRepairOrderId($openIds);
    $laborById = OperationalReportTotals::laborCentsByRepairOrderId($openIds);
    $wrongSold = $soldById->filter(fn (int $cents): bool => $cents !== 20000)->all();
    $wrongLabor = $laborById->filter(fn (int $cents): bool => $cents !== 12000)->all();

    expect($wrongSold)->toBe([])
        ->and($wrongLabor)->toBe([])
        ->and($metrics['sold']['result'])->toBe($expectedSold)
        ->and($metrics['labor']['result'])->toBe($expectedLabor)
        ->and($metrics['sold_then_labor_same_ids']['result']['sold'])->toBe($expectedSold)
        ->and($metrics['sold_then_labor_same_ids']['result']['labor'])->toBe($expectedLabor);

    $payload = [
        'sha' => trim((string) shell_exec('git rev-parse HEAD')),
        'volume' => ['open' => count($openIds), 'closed' => count($closedIds), 'all' => count($allIds)],
        'expected' => ['sold_cents' => $expectedSold, 'labor_cents' => $expectedLabor],
        'metrics' => collect($metrics)->map(fn (array $row): array => [
            'queries' => $row['queries'],
            'ms' => $row['ms'],
            'memory_bytes' => $row['memory_bytes'],
            'result_summary' => is_array($row['result']) ? $row['result'] : $row['result'],
        ])->all(),
    ];

    $path = storage_path('app/financial-perf-benchmark-'.substr($payload['sha'], 0, 8).'.json');
    file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT));
    // Also echo for CI/agent capture.
    fwrite(STDERR, "\nFIN_PERF_BENCHMARK ".$path."\n".json_encode($payload)."\n");

    // Authorized totals warm scopes/events once per batch; memoization makes the second
    // sold/labor call essentially free. Absolute ms varies by host; query counts are the gate.
    expect($metrics['sold']['queries'])->toBeLessThan(15)
        ->and($metrics['labor']['queries'])->toBeLessThan(3)
        ->and($metrics['sold_then_labor_same_ids']['queries'])->toBeLessThan(5)
        ->and($metrics['closed_labor_by_tech']['queries'])->toBeLessThan(15)
        ->and($metrics['parts_matrix_sample']['queries'])->toBeLessThan(20);
});


/**
 * @return array{0: RepairOrder, 1: int, 2: int}
 */
function finPerfSeedRaisedRo(
    User $advisor,
    bool $open,
    ?int $technicianId,
    int $index,
    ?Carbon $postedAt = null,
): array {
    $customer = Customer::query()->create([
        'first_name' => 'Perf',
        'last_name' => 'Ro'.$index,
        'phone' => sprintf('71955%05d', $index % 100000),
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'PF'.str_pad((string) ($index % 10000), 4, '0', STR_PAD_LEFT),
        'year' => 2016,
        'make' => 'Honda',
        'model' => 'Civic',
        'vin' => sprintf('1HGCM%d%06d', 1000 + ($index % 8000), $index),
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $open ? RepairOrderStatus::Estimate : RepairOrderStatus::Closed,
        'assigned_technician_id' => $technicianId,
        'mileage_in' => 1000 + $index,
        'mileage_out' => 1001 + $index,
        'concern_summary' => 'Perf '.$index,
        'posted_at' => $postedAt,
    ]);

    $partConcern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Pads',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);
    $laborConcern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Labor',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 2,
    ]);

    $part = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $partConcern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Pads',
        'quantity' => '1.00',
        'unit_price_cents' => 8000,
        'part_cost_cents' => 3000,
        'subtotal_cents' => 8000,
        'total_cents' => 8000,
        'pricing_matrix_key' => ShopSettings::current()->defaultPartsMatrix()['key'],
        'procurement_state' => PartProcurementState::Installed,
    ]);
    $labor = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $laborConcern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace pads',
        'quantity' => '1.00',
        'unit_price_cents' => 12000,
        'subtotal_cents' => 12000,
        'total_cents' => 12000,
    ]);

    app(UpdateConcernDispositionAction::class)->execute(
        $repairOrder->fresh(),
        $partConcern,
        RepairOrderConcernDisposition::Approved,
        $advisor,
    );
    app(UpdateConcernDispositionAction::class)->execute(
        $repairOrder->fresh(),
        $laborConcern,
        RepairOrderConcernDisposition::Approved,
        $advisor,
    );

    $laborConcern->update(['production_status' => ScopeProductionStatus::Completed->value]);

    // Raise after approval — living totals exceed authorized.
    $part->update(['unit_price_cents' => 11000, 'subtotal_cents' => 11000, 'total_cents' => 11000]);
    $labor->update(['unit_price_cents' => 16000, 'subtotal_cents' => 16000, 'total_cents' => 16000]);
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    return [$repairOrder->fresh(['lines.concern', 'concerns']), 8000 + 12000, 12000];
}

/**
 * @template T
 * @param  callable(): T  $callback
 * @return array{result: T, queries: int, ms: float, memory_bytes: int}
 */
function finPerfMeasure(callable $callback): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $memBefore = memory_get_usage(true);
    $start = hrtime(true);
    $result = $callback();
    $ms = (hrtime(true) - $start) / 1e6;
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();
    DB::flushQueryLog();

    return [
        'result' => $result,
        'queries' => $queries,
        'ms' => round($ms, 2),
        'memory_bytes' => max(0, memory_get_usage(true) - $memBefore),
    ];
}
