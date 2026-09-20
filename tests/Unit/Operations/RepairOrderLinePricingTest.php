<?php

use App\Ark\Operations\RepairOrders\RepairOrderLinePricing;

test('sublet preview returns margin and markup from cost and sell', function () {
    $pricing = app(RepairOrderLinePricing::class);

    $preview = $pricing->subletPreviewFor([
        'part_cost' => '100.00',
        'unit_price' => '125.00',
    ]);

    expect($preview['part_cost_cents'])->toBe(10000)
        ->and($preview['unit_price_cents'])->toBe(12500)
        ->and($preview['margin_percentage'])->toBe('20')
        ->and($preview['markup_percentage'])->toBe('25');
});
