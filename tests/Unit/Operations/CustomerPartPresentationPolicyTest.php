<?php

use App\Ark\Operations\Parts\CustomerDescriptionSource;
use App\Ark\Operations\Parts\CustomerPartDescriptionMode;
use App\Ark\Operations\Parts\CustomerPartDescriptionPresenter;
use App\Ark\Operations\Parts\CustomerPartPresentationPolicy;
use App\Ark\Operations\Parts\CustomerPartPresentationPresenter;
use App\Ark\Operations\Parts\CustomerPartPresentationProfile;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;

function partPresentationPolicy(
    CustomerPartDescriptionMode $mode = CustomerPartDescriptionMode::Cleaned,
    bool $showManufacturerNumber = false,
    bool $showSupplier = false,
    bool $showSupplierSku = false,
    bool $labelsLocked = false,
): CustomerPartPresentationPolicy {
    return new CustomerPartPresentationPolicy(
        descriptionMode: $mode,
        showManufacturerNumber: $showManufacturerNumber,
        showSupplier: $showSupplier,
        showSupplierSku: $showSupplierSku,
        allowDescriptionOverride: true,
        labelsLocked: $labelsLocked,
    );
}

function presentPartLine(array $line, CustomerPartPresentationPolicy $policy): array
{
    return (new CustomerPartPresentationPresenter(new CustomerPartDescriptionPresenter))
        ->presentLine($policy, [
            'type' => RepairOrderLineType::Part->value,
            ...$line,
        ]);
}

test('default cleaned policy hides brand and catalog text', function () {
    $presented = presentPartLine([
        'description' => 'Champion Spark Plug Copper Plus',
        'part_number' => '71',
        'vendor_name' => "O'Reilly",
    ], partPresentationPolicy());

    expect($presented['customer_part_description'])->toBe('Spark Plug')
        ->and($presented)->not->toHaveKey('customer_part_number')
        ->and($presented)->not->toHaveKey('customer_part_vendor');
});

test('verbatim policy shows imported description', function () {
    $presented = presentPartLine([
        'description' => 'Champion Spark Plug Copper Plus',
    ], partPresentationPolicy(CustomerPartDescriptionMode::Verbatim));

    expect($presented['customer_part_description'])->toBe('Champion Spark Plug Copper Plus');
});

test('cleaned with brand prepends manufacturer once', function () {
    $presented = presentPartLine([
        'description' => 'Champion Spark Plug Copper Plus',
    ], partPresentationPolicy(CustomerPartDescriptionMode::CleanedWithBrand));

    expect($presented['customer_part_description'])->toBe('Champion Spark Plug');
});

test('manual override wins over shop mode', function () {
    $presented = presentPartLine([
        'description' => 'Champion Spark Plug Copper Plus',
        'customer_description' => 'Copper Spark Plug',
        'customer_description_source' => CustomerDescriptionSource::Manual->value,
    ], partPresentationPolicy(CustomerPartDescriptionMode::Verbatim));

    expect($presented['customer_part_description'])->toBe('Copper Spark Plug');
});

test('manual mode uses explicit description then inventory fallback', function () {
    $withExplicit = presentPartLine([
        'description' => 'Champion Spark Plug Copper Plus',
        'customer_description' => 'Spark Plug',
    ], partPresentationPolicy(CustomerPartDescriptionMode::Manual));

    $withoutExplicit = presentPartLine([
        'description' => 'Champion Spark Plug Copper Plus',
    ], partPresentationPolicy(CustomerPartDescriptionMode::Manual));

    expect($withExplicit['customer_part_description'])->toBe('Spark Plug')
        ->and($withoutExplicit['customer_part_description'])->toBe('Champion Spark Plug Copper Plus');
});

test('manufacturer number and supplier appear only when enabled', function () {
    $hidden = presentPartLine([
        'description' => 'Gates 43527 Water Pump',
        'part_number' => '43527',
        'vendor_name' => 'WorldPac',
    ], partPresentationPolicy());

    $shown = presentPartLine([
        'description' => 'Gates 43527 Water Pump',
        'part_number' => '43527',
        'vendor_name' => 'WorldPac',
    ], partPresentationPolicy(showManufacturerNumber: true, showSupplier: true));

    expect($hidden)->not->toHaveKey('customer_part_number')
        ->and($hidden)->not->toHaveKey('customer_part_vendor')
        ->and($shown['customer_part_number'])->toBe('Gates 43527')
        ->and($shown['customer_part_vendor'])->toBe('WorldPac');
});

test('cleaned policy regenerates stale generated part labels', function () {
    $presented = presentPartLine([
        'description' => 'B1-Spring Pad',
        'customer_description' => 'Pad',
        'customer_description_source' => CustomerDescriptionSource::Generated->value,
    ], partPresentationPolicy());

    expect($presented['customer_part_description'])->toBe('B1-Spring Pad');
});

test('locked historical labels prefer stored wording without re-normalizing', function () {
    $presented = presentPartLine([
        'description' => 'Champion Spark Plug Copper Plus',
        'customer_description' => null,
    ], partPresentationPolicy(labelsLocked: true));

    expect($presented['customer_part_description'])->toBe('Champion Spark Plug Copper Plus');
});

test('retail profile composition still unlocks warranty part number via policy flags', function () {
    $profile = CustomerPartPresentationProfile::Warranty;

    expect($profile->showsPartNumber())->toBeTrue()
        ->and($profile->showsVendor())->toBeTrue();
});
