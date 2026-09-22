<?php

use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\Reports\Standards\ReportingStandardsV1;

test('posted sales applies labor parts sublet fees discounts and tax once', function () {
    $preTax = ReportingStandardsV1::preTaxServiceSalesCents(
        laborCents: 10_000,
        partsCents: 4_000,
        subletCents: 1_500,
        feeCents: 800,
        discountCents: 1_000,
    );
    $posted = ReportingStandardsV1::postedSalesCents(
        laborCents: 10_000,
        partsCents: 4_000,
        subletCents: 1_500,
        feeCents: 800,
        discountCents: 1_000,
        taxCents: 1_120,
    );

    expect($preTax)->toBe(15_300)
        ->and($posted)->toBe(16_420)
        ->and($posted)->toBe($preTax + 1_120);
});

test('aro uses posted pre-tax service sales and the open-queue average uses open repair orders', function () {
    $preTax = 15_300;

    expect(ReportingStandardsV1::aroCents($preTax, postedRepairOrderCount: 2))->toBe(7_650)
        ->and(ReportingStandardsV1::approvedSalesPerOpenRepairOrderCents($preTax, openRepairOrderCount: 3))->toBe(5_100)
        ->and(ReportingStandardsV1::aroCents($preTax, postedRepairOrderCount: 0))->toBe(0);
});

test('cash collected subtracts refunds from receipts', function () {
    expect(ReportingStandardsV1::cashCollectedCents(receiptCents: 12_500, refundCents: 400))->toBe(12_100);
});

test('gross margin uses pre-tax sales and ignores tax collected', function () {
    $preTax = 15_300;
    $tax = 1_120;
    $cost = 4_000;

    expect(ReportingStandardsV1::grossMarginPercent($preTax, $cost))->toBe(74)
        ->and(ReportingStandardsV1::grossMarginPercent($preTax + $tax, $cost))->not->toBe(74);
});

test('dollar approval rate and approved repair order rate stay separate', function () {
    expect(ReportingStandardsV1::approvalRatePercent(approvedCents: 100, presentedCents: 1_000))->toBe(10.0)
        ->and(ReportingStandardsV1::approvedRepairOrderRatePercent(approvedRepairOrderCount: 1, repairOrderCount: 2))->toBe(50.0);
});

test('close ratio is hours sold over hours presented', function () {
    expect(ReportingStandardsV1::closeRatioPercent(hoursSold: 3, hoursPresented: 4))->toBe(75.0)
        ->and(ReportingStandardsV1::closeRatioPercent(hoursSold: 3, hoursPresented: 0))->toBeNull();
});

test('a percentage stays incomplete when required inputs are missing', function () {
    expect(ReportingStandardsV1::percentOrIncomplete(false, 74))->toBe(ReportingStandardsV1::INCOMPLETE_DATA)
        ->and(ReportingStandardsV1::percentOrIncomplete(true, 74))->toBe('74%')
        ->and(ReportingStandardsV1::percentOrIncomplete(true, null))->toBe('n/a');
});

test('recommended declined and deferred work is outside approved revenue', function () {
    expect(ReportingStandardsV1::countsAsApprovedRevenue(RepairOrderConcernDisposition::Approved))->toBeTrue()
        ->and(ReportingStandardsV1::countsAsApprovedRevenue(RepairOrderConcernDisposition::Recommended))->toBeFalse()
        ->and(ReportingStandardsV1::countsAsApprovedRevenue(RepairOrderConcernDisposition::Declined))->toBeFalse()
        ->and(ReportingStandardsV1::countsAsApprovedRevenue(RepairOrderConcernDisposition::Deferred))->toBeFalse();
});
