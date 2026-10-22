<?php

use App\Ark\Operations\Parts\LinkedPartsQuotes;
use App\Ark\Operations\Parts\UnavailableLinkedPartsQuotes;
use App\Ark\Operations\RepairOrders\RepairOrder;

test('stock core does not connect a linked quote provider', function () {
    $quotes = app(LinkedPartsQuotes::class);

    expect($quotes)->toBeInstanceOf(UnavailableLinkedPartsQuotes::class)
        ->and($quotes->available())->toBeFalse()
        ->and($quotes->present(new RepairOrder()))->toMatchArray([
            'available' => false,
            'provider_label' => '',
            'current' => null,
            'history' => [],
        ]);
});
