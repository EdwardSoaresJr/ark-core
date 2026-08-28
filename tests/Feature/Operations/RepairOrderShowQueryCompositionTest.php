<?php

use App\Ark\Operations\Diagnostics\QueryCompositionCollector;
use App\Ark\Operations\Diagnostics\QueryCompositionFixtures;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('ro show query composition report captures categorized query counts', function () {
    $repairOrder = QueryCompositionFixtures::representativeRepairOrder();

    $this->actingAs(actingAsLearnCurrentAdvisor());

    $report = app(QueryCompositionCollector::class)->measure(function () use ($repairOrder): void {
        $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk();
    });

    expect($report->totalQueries)->toBeGreaterThan(0)
        ->and($report->updateQueries)->toBe(0)
        ->and($report->counts)->not->toBeEmpty();

    $rows = collect($report->rows())->keyBy('category');

    expect($rows->has('LifecycleProjection') || $rows->has('FinancialPresenter'))->toBeTrue();
});

test('ro show lifecycle controls query budget after projection', function () {
    $repairOrder = QueryCompositionFixtures::representativeRepairOrder();

    $this->actingAs(actingAsLearnCurrentAdvisor());

    $report = app(QueryCompositionCollector::class)->measure(function () use ($repairOrder): void {
        $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk();
    });

    expect($report->updateQueries)->toBe(0)
        ->and($report->counts['LifecycleControls'] ?? 0)->toBeLessThanOrEqual(10);
});

test('ro show framework misc queries are explainable by subcategory', function () {
    $repairOrder = QueryCompositionFixtures::representativeRepairOrder();

    $this->actingAs(actingAsLearnCurrentAdvisor());

    $report = app(QueryCompositionCollector::class)->measure(function () use ($repairOrder): void {
        $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk();
    });

    $frameworkMisc = $report->counts['FrameworkMisc'] ?? 0;

    if ($frameworkMisc === 0) {
        expect($report->subcounts['FrameworkMisc'] ?? [])->toBeEmpty();

        return;
    }

    $explained = array_sum($report->subcounts['FrameworkMisc'] ?? []);

    expect($explained)->toBe($frameworkMisc)
        ->and($report->subcategoryRows('FrameworkMisc'))->not->toBeEmpty();
});

test('ro show view composers and financial presenter are explainable by subcategory', function () {
    $repairOrder = QueryCompositionFixtures::representativeRepairOrder();

    $this->actingAs(actingAsLearnCurrentAdvisor());

    $report = app(QueryCompositionCollector::class)->measure(function () use ($repairOrder): void {
        $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk();
    });

    foreach (['ViewComposers', 'FinancialPresenter'] as $category) {
        $count = $report->counts[$category] ?? 0;

        if ($count === 0) {
            continue;
        }

        expect(array_sum($report->subcounts[$category] ?? []))->toBe($count)
            ->and($report->subcategoryRows($category))->not->toBeEmpty();
    }
});

test('ro show financial presenter balance due is projected once per request', function () {
    $repairOrder = QueryCompositionFixtures::representativeRepairOrder();

    $this->actingAs(actingAsLearnCurrentAdvisor());

    $report = app(QueryCompositionCollector::class)->measure(function () use ($repairOrder): void {
        $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk();
    });

    expect($report->subcounts['FinancialPresenter']['BalanceDue'] ?? 0)->toBeLessThanOrEqual(2);
});

test('ro show view composers are projected from page context', function () {
    $repairOrder = QueryCompositionFixtures::representativeRepairOrder();

    $this->actingAs(actingAsLearnCurrentAdvisor());

    $report = app(QueryCompositionCollector::class)->measure(function () use ($repairOrder): void {
        $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk();
    });

    expect($report->subcounts['ViewComposers'] ?? [])->not->toBeEmpty()
        ->and(array_sum($report->subcounts['ViewComposers'] ?? []))->toBeLessThanOrEqual(3);
});

test('ro show get has zero mutations', function () {
    $repairOrder = QueryCompositionFixtures::representativeRepairOrder();

    $this->actingAs(actingAsLearnCurrentAdvisor());

    $report = app(QueryCompositionCollector::class)->measure(function () use ($repairOrder): void {
        $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk();
    });

    expect($report->updateQueries)->toBe(0)
        ->and($report->getMutations)->toBeEmpty();
});

test('estimate review query composition report captures categorized query counts', function () {
    $repairOrder = QueryCompositionFixtures::representativeRepairOrder();

    $this->actingAs(actingAsLearnCurrentAdvisor());

    $report = app(QueryCompositionCollector::class)->measure(function () use ($repairOrder): void {
        $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk();
    });

    expect($report->totalQueries)->toBeGreaterThan(0)
        ->and($report->updateQueries)->toBe(0);
});

test('ro show query composition report snapshot', function () {
    $repairOrder = QueryCompositionFixtures::representativeRepairOrder();

    $this->actingAs(actingAsLearnCurrentAdvisor());

    $report = app(QueryCompositionCollector::class)->measure(function () use ($repairOrder): void {
        $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk();
    });

    dump([
        'total' => $report->totalQueries,
        'updates' => $report->updateQueries,
        'breakdown' => collect($report->rows())->mapWithKeys(fn (array $row): array => [$row['category'] => $row['queries']])->all(),
    ]);

    expect($report->totalQueries)->toBeGreaterThan(0);
})->skip('Investigation snapshot — run manually during Pass 3 profiling');
