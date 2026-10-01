<?php

use App\Ark\Operations\RepairOrders\RepairOrderLineType;

test('sublet uses staff and document labels', function () {
    $sublet = RepairOrderLineType::Sublet;

    expect($sublet->label())->toBe('Sublet')
        ->and($sublet->staffLabel())->toBe('Sublet (Service)')
        ->and($sublet->documentLabel())->toBe('Service');
});

test('labor document label stays labor', function () {
    expect(RepairOrderLineType::Labor->documentLabel())->toBe('Labor')
        ->and(RepairOrderLineType::Labor->label())->toBe('Labor');
});

test('legacy service alias maps to sublet', function () {
    $mapper = new \App\Ark\Import\LegacyArkSmsValueMapper;
    $report = new \App\Ark\Import\LegacyImportReport;

    expect($mapper->mapLineType('service', $report, 'test'))
        ->toBe(RepairOrderLineType::Sublet);
});

test('sublet lines are not shop fee eligible', function () {
    expect(RepairOrderLineType::Sublet->isShopFeeEligible())->toBeFalse()
        ->and(RepairOrderLineType::Labor->isShopFeeEligible())->toBeTrue()
        ->and(RepairOrderLineType::Part->isShopFeeEligible())->toBeTrue();
});
