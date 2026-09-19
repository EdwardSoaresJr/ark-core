<?php

namespace App\Ark\Operations\Recommendations;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Support\Collection;

final class VehicleRecommendationsProjection
{
    /**
     * @return array{
     *     open: Collection<int, Recommendation>,
     *     open_count: int,
     *     safety_count: int,
     *     follow_up_due_count: int,
     *     estimated_cents: int,
     * }
     */
    public static function forVehicle(Vehicle $vehicle, ?int $currentMileage = null): array
    {
        $open = Recommendation::query()
            ->with(['events', 'estimateLinks', 'originatingInspectionItem'])
            ->where('vehicle_id', $vehicle->id)
            ->where('lifecycle', RecommendationLifecycle::Open)
            ->orderByDesc('safety_related')
            ->orderByDesc('discovered_at')
            ->orderByDesc('id')
            ->get();

        return self::summarize($open, $currentMileage);
    }

    /**
     * @param  Collection<int, Recommendation>  $open
     * @return array{
     *     open: Collection<int, Recommendation>,
     *     open_count: int,
     *     safety_count: int,
     *     follow_up_due_count: int,
     *     estimated_cents: int,
     * }
     */
    public static function summarize(Collection $open, ?int $currentMileage = null): array
    {
        return [
            'open' => $open,
            'open_count' => $open->count(),
            'safety_count' => $open->where('safety_related', true)->count(),
            'follow_up_due_count' => $open->filter(fn (Recommendation $recommendation): bool => $recommendation->followUpIsDue())->count(),
            'estimated_cents' => (int) $open->sum(fn (Recommendation $recommendation): int => (int) ($recommendation->latestEstimatedAmountCents() ?? 0)),
        ];
    }

    /**
     * @return array{
     *     open: Collection<int, Recommendation>,
     *     open_count: int,
     *     safety_count: int,
     *     follow_up_due_count: int,
     *     estimated_cents: int,
     *     current_mileage: ?int,
     * }
     */
    public static function forRepairOrder(RepairOrder $repairOrder): array
    {
        $repairOrder->loadMissing('vehicle');

        if ($repairOrder->vehicle === null) {
            return [
                ...self::summarize(collect()),
                'current_mileage' => null,
            ];
        }

        $mileage = $repairOrder->resolvedMileageIn();

        return [
            ...self::forVehicle($repairOrder->vehicle, $mileage),
            'current_mileage' => $mileage,
        ];
    }
}
