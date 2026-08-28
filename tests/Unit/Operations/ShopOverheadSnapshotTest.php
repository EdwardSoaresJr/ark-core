<?php

use App\Ark\Operations\Labor\ShopOverheadSnapshot;
use App\Ark\Operations\Labor\ShopPaymentProcessingOverhead;

test('shop overhead snapshot calculates fixed and payment overhead', function () {
    $snapshot = ShopOverheadSnapshot::fromState([
        'costs' => [
            'rent' => '5000',
            'utilities' => '800',
        ],
        'monthly_card_volume' => '100000',
        'card_processing_percent' => '2.9',
        'technician_count' => '2',
        'workdays_per_month' => '22',
        'workday_hours' => '8',
        'billable_utilization' => '85',
    ]);

    expect($snapshot->monthlyFixedOverheadTotal())->toBe(5800.0)
        ->and($snapshot->monthlyProcessingCost())->toBe(2900.0)
        ->and($snapshot->monthlyOverheadTotal())->toBe(8700.0)
        ->and($snapshot->monthlyBillableHours())->toBe(299.2)
        ->and($snapshot->overheadPerBilledHour())->toBe(29.08);
});

test('shop overhead snapshot normalizes state for persistence', function () {
    $snapshot = ShopOverheadSnapshot::fromState([
        'fixed_cost_lines' => [
            ['label' => 'Rent', 'amount' => '1200', 'period' => 'monthly'],
        ],
        'overhead_tab' => 'capacity',
    ]);

    expect($snapshot->normalizedState())->toMatchArray([
        'fixed_cost_lines' => [
            ['label' => 'Rent', 'amount' => '1200', 'period' => 'monthly'],
        ],
        'card_processing_percent' => (string) ShopPaymentProcessingOverhead::DEFAULT_CARD_PROCESSING_PERCENT,
        'overhead_tab' => 'capacity',
    ]);
});

test('shop overhead snapshot converts weekly fixed cost lines to monthly total', function () {
    $snapshot = ShopOverheadSnapshot::fromState([
        'fixed_cost_lines' => [
            ['label' => 'Payroll', 'amount' => '1400', 'period' => 'weekly'],
            ['label' => 'Rent', 'amount' => '3300', 'period' => 'monthly'],
        ],
    ]);

    expect($snapshot->monthlyFixedOverheadTotal())->toBe(9366.67)
        ->and($snapshot->monthlyOfficePayrollTotal())->toBe(6066.67);
});

test('shop overhead snapshot reads legacy office payroll costs', function () {
    $snapshot = ShopOverheadSnapshot::fromState([
        'costs' => [
            'office_payroll' => '9000',
            'rent' => '3300',
        ],
    ]);

    expect($snapshot->monthlyOfficePayrollTotal())->toBe(9000.0);
});
