<?php

use App\Ark\Operations\Parts\CustomerDescriptionSource;
use App\Ark\Operations\Parts\CustomerPartDescriptionAttributes;
use App\Ark\Operations\Parts\CustomerPartDescriptionPresenter;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;

test('customer part description attributes snapshot generated labels on create', function () {
    $attributes = new CustomerPartDescriptionAttributes(new CustomerPartDescriptionPresenter);

    expect($attributes->forCreate('Champion Spark Plug Copper Plus 71', brand: 'Champion'))
        ->toMatchArray([
            'customer_description' => 'Spark Plug',
            'customer_description_source' => CustomerDescriptionSource::Generated->value,
        ]);
});

test('customer part description attributes mark explicit overrides as manual', function () {
    $attributes = new CustomerPartDescriptionAttributes(new CustomerPartDescriptionPresenter);

    expect($attributes->forCreate('Champion Spark Plug Copper Plus', 'Copper Spark Plug'))
        ->toMatchArray([
            'customer_description' => 'Copper Spark Plug',
            'customer_description_source' => CustomerDescriptionSource::Manual->value,
        ]);
});

test('customer part description attributes preserve manual overrides across inventory edits', function () {
    $attributes = new CustomerPartDescriptionAttributes(new CustomerPartDescriptionPresenter);
    $line = new RepairOrderLine([
        'type' => RepairOrderLineType::Part,
        'description' => 'Champion Spark Plug Copper Plus',
        'customer_description' => 'Copper Spark Plug',
        'customer_description_source' => CustomerDescriptionSource::Manual,
    ]);

    expect($attributes->forUpdate($line, 'Champion Spark Plug Copper Plus 71', []))
        ->toMatchArray([
            'customer_description' => 'Copper Spark Plug',
            'customer_description_source' => CustomerDescriptionSource::Manual->value,
        ]);
});

test('customer part description attributes regenerate when inventory changes and source is generated', function () {
    $attributes = new CustomerPartDescriptionAttributes(new CustomerPartDescriptionPresenter);
    $line = new RepairOrderLine([
        'type' => RepairOrderLineType::Part,
        'description' => 'Champion Spark Plug Copper Plus',
        'customer_description' => 'Spark Plug',
        'customer_description_source' => CustomerDescriptionSource::Generated,
    ]);

    expect($attributes->forUpdate($line, 'Blue Streak Distributor Cap FD175', []))
        ->toMatchArray([
            'customer_description' => 'Distributor Cap',
            'customer_description_source' => CustomerDescriptionSource::Generated->value,
        ]);
});
