<?php

use App\Ark\Operations\LaborGuides\Rte\RteLaborAddIdCatalog;
use App\Ark\Operations\LaborGuides\Rte\RteLaborAddOnResolver;

test('rte add-on catalog maps cooling job family codes', function (): void {
    $catalog = new RteLaborAddIdCatalog;

    expect($catalog->describe('100', '3461'))->toBe('COOLANT RECOVERY & DISPOSAL')
        ->and($catalog->describe('1500', '3461'))->toBe('SHOP SUPPLIES')
        ->and($catalog->describe('100', '3261'))->toBe('ENGINE SERVICE PREP');
});

test('rte add-on catalog fallback includes add id code', function (): void {
    expect((new RteLaborAddIdCatalog)->fallbackLabel('9999'))->toBe('RTE add-on 9999');
});

test('rte related operation catalog maps radiator jobs to cooling operations', function (): void {
    $catalog = new \App\Ark\Operations\LaborGuides\Rte\RteLaborRelatedOperationCatalog;

    expect($catalog->relatedJobCodesForLabRow(['lab_id' => '3461BTTT12199']))
        ->toBe(['1421', '1431']);
});
