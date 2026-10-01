<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Orientation\Orientation;
use App\Ark\Orientation\OrientationDensity;

/**
 * @deprecated Use {@see Orientation::forRepairOrder()} instead.
 */
final class RepairOrderCurrentSituationProjection
{
    /**
     * @return array<string, mixed>
     */
    public function for(RepairOrder $repairOrder): array
    {
        return Orientation::forRepairOrder($repairOrder, OrientationDensity::Full);
    }
}
