<?php

use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Ark\Operations\Reports\OperationalReportRangeMetrics;
use App\Ark\Operations\Reports\Standards\ReportingStandardsV1;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\ShopExcellence\ShopExcellenceTargets;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

// $3,043.70 and $6,087.40 per month are exactly $100 and $200 per day at 30.437 days per month.
const OWNER_PL_PRORATION_MONTHLY_PAYROLL_TAX_CENTS = 304_370;
const OWNER_PL_PRORATION_MONTHLY_FIXED_CENTS = 608_740;

beforeEach(function () {
    // Friday evening in Denver while UTC is already Saturday.
    Carbon::setTestNow(Carbon::parse('2026-06-06 04:00:00', 'UTC'));

    ShopExcellenceTargets::persist(array_merge(ShopExcellenceTargets::DEFAULTS, [
        'payroll_tax_reserve_percent' => 10,
        'monthly_payroll_tax_cents' => OWNER_PL_PRORATION_MONTHLY_PAYROLL_TAX_CENTS,
        'monthly_fixed_costs_cents' => OWNER_PL_PRORATION_MONTHLY_FIXED_CENTS,
    ]));
});

afterEach(function () {
    Carbon::setTestNow();
});

function useOwnerPlProrationShopTimezone(string $timezone): void
{
    ShopSettings::current()->update(['shop_timezone' => $timezone]);
    ShopDisplayTimezone::apply();
}

test('owner pl and break-even prorate monthly amounts over shop-local calendar days', function (
    string $timezone,
    string $fromDate,
    string $toDate,
    int $days,
    string $payrollTax,
    string $fixedCosts,
) {
    useOwnerPlProrationShopTimezone($timezone);

    [$from, $to] = OperationalReportDateScope::resolveRange($fromDate, $toDate);
    $metrics = new OperationalReportRangeMetrics($from, $to);
    $summary = $metrics->ownerPlSummary();
    $breakEven = $metrics->breakEvenSummary();

    expect($summary['range_days'])->toBe($days)
        ->and(collect($summary['tax_lines'])->firstWhere('label', 'Payroll tax reserve (est.)')['amount'])->toBe($payrollTax)
        ->and(collect($summary['pl_lines'])->firstWhere('label', 'Operating expenses')['amount'])->toBe($fixedCosts)
        ->and(collect($summary['pl_lines'])->firstWhere('label', 'Operating expenses')['note'])->toContain($days.' days in range')
        ->and($breakEven['range_days'])->toBe($days)
        ->and($breakEven['prorated_fixed_label'])->toBe($fixedCosts)
        ->and(OperationalReportDateScope::shopCalendarDayCount($from, $to))->toBe($days);
})->with([
    'one Denver day while UTC is already the next day' => ['America/Denver', '2026-06-05', '2026-06-05', 1, '$100.00', '$200.00'],
    'one Denver day of 25 hours when daylight saving time ends' => ['America/Denver', '2026-11-01', '2026-11-01', 1, '$100.00', '$200.00'],
    'Denver range across the end of daylight saving time' => ['America/Denver', '2026-10-30', '2026-11-02', 4, '$400.00', '$800.00'],
    'Denver range across the start of daylight saving time' => ['America/Denver', '2027-03-13', '2027-03-15', 3, '$300.00', '$600.00'],
    'full Denver month of June' => ['America/Denver', '2026-06-01', '2026-06-30', 30, '$3000.00', '$6000.00'],
    'full Denver month of July' => ['America/Denver', '2026-07-01', '2026-07-31', 31, '$3100.00', '$6200.00'],
    'Denver range spanning June and July' => ['America/Denver', '2026-06-29', '2026-07-02', 4, '$400.00', '$800.00'],
    'one Tokyo day, ahead of UTC' => ['Asia/Tokyo', '2026-06-05', '2026-06-05', 1, '$100.00', '$200.00'],
    'one UTC day' => ['UTC', '2026-06-05', '2026-06-05', 1, '$100.00', '$200.00'],
    'full UTC month of June' => ['UTC', '2026-06-01', '2026-06-30', 30, '$3000.00', '$6000.00'],
    'UTC range spanning June and July' => ['UTC', '2026-06-29', '2026-07-02', 4, '$400.00', '$800.00'],
]);

test('owner pl shows one day of monthly payroll tax for a one-day Denver report', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $shopToday = OperationalReportDateScope::shopDateString(now());
    [$from, $to] = OperationalReportDateScope::resolveRange($shopToday, $shopToday);
    $line = collect((new OperationalReportRangeMetrics($from, $to))->ownerPlSummary()['tax_lines'])
        ->firstWhere('label', 'Payroll tax reserve (est.)');

    expect($shopToday)->toBe('2026-06-05')
        ->and(now()->toDateString())->toBe('2026-06-06')
        ->and($line['source'])->toBe('Owner Targets monthly payroll tax')
        ->and($line['amount'])->toBe('$100.00');

    $this->get(route('operations.reports.operational', [
        'from' => $shopToday,
        'to' => $shopToday,
        'tab' => 'owner-pl',
    ]))
        ->assertOk()
        ->assertSeeInOrder(['Operating expenses', '$200.00', '1 days in range'])
        ->assertSeeInOrder(['Payroll tax reserve (est.)', '$100.00', 'Owner Targets monthly payroll tax']);

    $this->get(route('operations.reports.operational', [
        'from' => $shopToday,
        'to' => $shopToday,
        'tab' => 'margin-health',
    ]))
        ->assertOk()
        ->assertSee('(1 days in range)')
        ->assertSeeInOrder(['Prorated fixed', '$200.00']);
});

test('prorated monthly cents use 30.437 days per month', function () {
    expect(ReportingStandardsV1::proratedMonthlyCents(OWNER_PL_PRORATION_MONTHLY_PAYROLL_TAX_CENTS, 1))->toBe(10_000)
        ->and(ReportingStandardsV1::proratedMonthlyCents(OWNER_PL_PRORATION_MONTHLY_PAYROLL_TAX_CENTS, 30))->toBe(300_000)
        ->and(ReportingStandardsV1::proratedMonthlyCents(100_000, 1))->toBe(3_285)
        ->and(ReportingStandardsV1::proratedMonthlyCents(0, 31))->toBe(0);
});
