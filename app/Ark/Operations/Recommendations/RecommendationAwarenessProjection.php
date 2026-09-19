<?php

namespace App\Ark\Operations\Recommendations;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Vehicles\Vehicle;

final class RecommendationAwarenessProjection
{
    /**
     * @return array{
     *     open_count: int,
     *     safety_count: int,
     *     estimated_cents: int,
     *     headline: ?string,
     *     strong: bool,
     * }
     */
    public static function forVehicle(?Vehicle $vehicle, ?int $currentMileage = null): array
    {
        if ($vehicle === null) {
            return [
                'open_count' => 0,
                'safety_count' => 0,
                'estimated_cents' => 0,
                'headline' => null,
                'strong' => false,
            ];
        }

        $summary = VehicleRecommendationsProjection::forVehicle($vehicle, $currentMileage);

        return self::fromSummary($summary);
    }

    /**
     * @return array{
     *     open_count: int,
     *     safety_count: int,
     *     estimated_cents: int,
     *     headline: ?string,
     *     strong: bool,
     * }
     */
    public static function forRepairOrder(RepairOrder $repairOrder): array
    {
        $summary = VehicleRecommendationsProjection::forRepairOrder($repairOrder);

        return self::fromSummary($summary);
    }

    /**
     * @param  array{open_count: int, safety_count: int, estimated_cents: int}  $summary
     * @return array{
     *     open_count: int,
     *     safety_count: int,
     *     estimated_cents: int,
     *     headline: ?string,
     *     strong: bool,
     * }
     */
    private static function fromSummary(array $summary): array
    {
        $open = $summary['open_count'];
        $safety = $summary['safety_count'];
        $cents = $summary['estimated_cents'];

        if ($open === 0) {
            return [
                'open_count' => 0,
                'safety_count' => 0,
                'estimated_cents' => 0,
                'headline' => null,
                'strong' => false,
            ];
        }

        $parts = [$open.' open recommendation'.($open === 1 ? '' : 's')];

        if ($safety > 0) {
            $parts[] = $safety === 1 ? '1 safety' : $safety.' safety';
        }

        if ($cents > 0) {
            $parts[] = '$'.number_format($cents / 100, 2).' previously estimated';
        }

        return [
            'open_count' => $open,
            'safety_count' => $safety,
            'estimated_cents' => $cents,
            'headline' => implode(' · ', $parts),
            'strong' => $safety > 0,
        ];
    }
}
