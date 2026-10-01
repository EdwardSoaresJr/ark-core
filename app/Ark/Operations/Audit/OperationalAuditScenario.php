<?php

namespace App\Ark\Operations\Audit;

use App\Ark\Operations\RepairOrders\RepairOrder;

final readonly class OperationalAuditScenario
{
    /**
     * @param  list<int>  $relatedRepairOrderIds
     */
    public function __construct(
        public string $key,
        public string $title,
        public RepairOrder $repairOrder,
        public string $purpose,
        public string $expectations,
        public array $relatedRepairOrderIds = [],
    ) {}

    public function reviewUrl(): string
    {
        return route('operations.repair-orders.show', $this->repairOrder);
    }
}
