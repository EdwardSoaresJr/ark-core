<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Documents\EstimateDocument;

final readonly class RepairOrderBalanceProjection
{
    public function __construct(
        public BalanceDueResult $balance,
        public ?EstimateDocument $invoice,
    ) {}
}
