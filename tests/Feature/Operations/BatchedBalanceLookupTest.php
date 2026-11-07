<?php

use App\Ark\Operations\Attention\CustomerDecisionPressure;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Reports\OperationalReportRangeMetrics;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('customer decision pressure batches balance lookups and keeps paid-exclusion semantics', function () {
    actingAsLearnCurrentAdvisor();

    $unpaidReady = decisionPressureRepairOrder(
        firstName: 'Unpaid',
        lastName: 'Ready',
        status: RepairOrderStatus::ReadyPickup,
        lineCents: 25_000,
        disposition: RepairOrderConcernDisposition::Approved,
    );
    issueFinalInvoiceFor($unpaidReady);

    $paidReady = decisionPressureRepairOrder(
        firstName: 'Paid',
        lastName: 'Ready',
        status: RepairOrderStatus::ReadyPickup,
        lineCents: 15_000,
        disposition: RepairOrderConcernDisposition::Approved,
    );
    issueFinalInvoiceFor($paidReady);
    payRepairOrderInFull($paidReady->fresh());

    decisionPressureRepairOrder(
        firstName: 'Waiting',
        lastName: 'Decision',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 40_000,
    );

    $openCount = 3;
    $invoiceQueries = 0;
    $ledgerQueries = 0;
    $listener = function (QueryExecuted $query) use (&$invoiceQueries, &$ledgerQueries): void {
        $sql = strtolower($query->sql);
        if (str_contains($sql, 'estimate_documents')) {
            $invoiceQueries++;
        }
        if (str_contains($sql, 'repair_order_ledger_entries')) {
            $ledgerQueries++;
        }
    };

    Event::listen(QueryExecuted::class, $listener);
    $started = hrtime(true);
    $projection = app(CustomerDecisionPressure::class)->resolve();
    $elapsedMs = (hrtime(true) - $started) / 1e6;
    Event::forget(QueryExecuted::class);

    $names = collect([
        ...$projection['estimate_ready_not_sent'],
        ...$projection['customer_decision_needed'],
        ...$projection['approved_work_stalled'],
    ])->pluck('customer_name')->all();

    expect($names)->toContain('Unpaid Ready')
        ->and($names)->toContain('Waiting Decision')
        ->and($names)->not->toContain('Paid Ready')
        // One invoice query + one ledger query for the whole open set (not per RO).
        ->and($invoiceQueries)->toBe(1)
        ->and($ledgerQueries)->toBe(1)
        ->and($invoiceQueries + $ledgerQueries)->toBeLessThan($openCount * 2)
        ->and($elapsedMs)->toBeLessThan(2_000);
});

test('report unpaid pickups batch balance lookups and memoize the unpaid set', function () {
    actingAsLearnCurrentAdvisor();

    Carbon::setTestNow('2026-06-15 12:00:00');

    $unpaid = decisionPressureRepairOrder(
        firstName: 'Report',
        lastName: 'Unpaid',
        status: RepairOrderStatus::ReadyPickup,
        lineCents: 30_000,
        disposition: RepairOrderConcernDisposition::Approved,
    );
    $unpaid->forceFill(['opened_at' => now(), 'created_at' => now()])->save();
    issueFinalInvoiceFor($unpaid);

    $paid = decisionPressureRepairOrder(
        firstName: 'Report',
        lastName: 'Paid',
        status: RepairOrderStatus::ReadyPickup,
        lineCents: 20_000,
        disposition: RepairOrderConcernDisposition::Approved,
    );
    $paid->forceFill(['opened_at' => now(), 'created_at' => now()])->save();
    issueFinalInvoiceFor($paid);
    payRepairOrderInFull($paid->fresh());

    $metrics = new OperationalReportRangeMetrics(now()->copy()->startOfDay(), now()->copy()->endOfDay());
    $method = (new ReflectionClass($metrics))->getMethod('unpaidReadyPickupRepairOrders');
    $method->setAccessible(true);

    $invoiceQueries = 0;
    $ledgerQueries = 0;
    $listener = function (QueryExecuted $query) use (&$invoiceQueries, &$ledgerQueries): void {
        $sql = strtolower($query->sql);
        if (str_contains($sql, 'estimate_documents')) {
            $invoiceQueries++;
        }
        if (str_contains($sql, 'repair_order_ledger_entries')) {
            $ledgerQueries++;
        }
    };

    Event::listen(QueryExecuted::class, $listener);
    $first = $method->invoke($metrics);
    $afterFirstInvoice = $invoiceQueries;
    $afterFirstLedger = $ledgerQueries;

    $second = $method->invoke($metrics);
    $afterSecondInvoice = $invoiceQueries;
    $afterSecondLedger = $ledgerQueries;
    Event::forget(QueryExecuted::class);

    expect($first)->toHaveCount(1)
        ->and($first->first()->customer->last_name)->toBe('Unpaid')
        ->and($second->count())->toBe(1)
        ->and($afterFirstInvoice)->toBe(1)
        ->and($afterFirstLedger)->toBe(1)
        ->and($afterSecondInvoice)->toBe($afterFirstInvoice)
        ->and($afterSecondLedger)->toBe($afterFirstLedger);

    Carbon::setTestNow();
});
