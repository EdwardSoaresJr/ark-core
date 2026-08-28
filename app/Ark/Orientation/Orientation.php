<?php

namespace App\Ark\Orientation;

use App\Ark\Operations\RepairOrders\RepairOrder;

/**
 * Platform orientation service — every interruption surface asks here first.
 *
 * Doctrine: every human interruption in ARK begins with orientation.
 * North star metric: Time to Orientation (TTO) — seconds until an employee
 * can make a good decision, not page load time.
 */
final class Orientation
{
    public function __construct(
        private readonly RepairOrderOrientationEngine $repairOrderEngine,
    ) {}

    public static function forRepairOrder(
        RepairOrder $repairOrder,
        OrientationDensity $density = OrientationDensity::Full,
    ): array {
        return app(self::class)->repairOrder($repairOrder, $density);
    }

    public function repairOrder(
        RepairOrder $repairOrder,
        OrientationDensity $density = OrientationDensity::Full,
    ): array {
        return $this->repairOrderEngine
            ->derive($repairOrder)
            ->present($density);
    }

    public function repairOrderOrientation(RepairOrder $repairOrder): OperationalOrientation
    {
        return $this->repairOrderEngine->derive($repairOrder);
    }
}
