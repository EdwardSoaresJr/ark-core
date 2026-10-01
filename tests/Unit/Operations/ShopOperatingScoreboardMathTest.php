<?php

use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\Scoreboard\PresentedConcernPopulation;
use App\Ark\Operations\Scoreboard\ShopOperatingScoreboardMath;
use App\Ark\Operations\Scoreboard\ShopOperatingScoreboardPeriod;
use Illuminate\Support\Carbon;

test('median is not replaced by the average when one repair order is long', function () {
    $cycles = [1, 1, 30];

    expect(ShopOperatingScoreboardMath::median($cycles))->toBe(1.0)
        ->and(ShopOperatingScoreboardMath::average($cycles))->toBe(10.67)
        ->and(ShopOperatingScoreboardMath::median([]))->toBeNull()
        ->and(ShopOperatingScoreboardMath::perOpenDay(4, 0))->toBeNull()
        ->and(ShopOperatingScoreboardMath::perOpenDay(4, 2))->toBe(2.0);
});

test('a metric without a target has no health tone', function () {
    expect(ShopOperatingScoreboardMath::toneForMinimum(30, null))->toBeNull()
        ->and(ShopOperatingScoreboardMath::toneForMinimum(null, 60))->toBeNull()
        ->and(ShopOperatingScoreboardMath::toneForMinimum(60, 60))->toBe('good')
        ->and(ShopOperatingScoreboardMath::toneForMaximum(4, 3))->toBe('warn')
        ->and(ShopOperatingScoreboardMath::toneForMaximum(2, 3))->toBe('good');
});

test('presented population is every customer-facing disposition and excludes draft', function () {
    expect(PresentedConcernPopulation::dispositions())->toBe([
        RepairOrderConcernDisposition::Recommended,
        RepairOrderConcernDisposition::Approved,
        RepairOrderConcernDisposition::Deferred,
        RepairOrderConcernDisposition::Declined,
    ])
        ->and(RepairOrderConcernDisposition::Draft->visibleToCustomer())->toBeFalse()
        ->and(PresentedConcernPopulation::includes(RepairOrderConcernDisposition::Deferred))->toBeTrue();
});

test('scoreboard periods use shop days and an equivalent previous window', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-24 18:00:00', 'America/Denver'));

    $week = ShopOperatingScoreboardPeriod::resolve('last_7');
    $today = ShopOperatingScoreboardPeriod::resolve('today');
    $unknown = ShopOperatingScoreboardPeriod::resolve('not-a-period');

    expect($week['key'])->toBe('last_7')
        ->and($week['from']->timezone('America/Denver')->toDateString())->toBe('2026-09-18')
        ->and($week['to']->timezone('America/Denver')->toDateString())->toBe('2026-09-24')
        ->and($week['previous_from']?->timezone('America/Denver')->toDateString())->toBe('2026-09-11')
        ->and($week['previous_to']?->timezone('America/Denver')->toDateString())->toBe('2026-09-17')
        ->and($today['from']->timezone('America/Denver')->toDateString())->toBe('2026-09-24')
        ->and($today['previous_label'])->toBe('Yesterday')
        ->and($unknown['key'])->toBe('this_month');

    Carbon::setTestNow();
});
