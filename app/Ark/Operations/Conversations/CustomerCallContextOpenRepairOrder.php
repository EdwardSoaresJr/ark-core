<?php

namespace App\Ark\Operations\Conversations;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Vehicles\Vehicle;

readonly class CustomerCallContextOpenRepairOrder
{
    /**
     * @param  array<string, mixed>|null  $orientation  Compact orientation payload from {@see \App\Ark\Orientation\Orientation}.
     */
    public function __construct(
        public RepairOrder $repairOrder,
        public Vehicle $vehicle,
        public string $workflowPostureLabel,
        public string $workflowNextAction,
        public ?array $orientation = null,
    ) {}
}
