<?php

namespace App\Ark\Operations\Intake;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Workboard\WorkboardLens;
use App\Models\User;

/**
 * Read-only intake queue count for navigation pressure — draft scopes in build.
 */
class AdvisorIntakePressure
{
    private const REQUEST_CACHE_KEY = 'advisor_intake_pressure';

    public function unresolvedCount(?User $viewer): int
    {
        if ($viewer === null) {
            return 0;
        }

        return (int) ($this->resolve($viewer)['count'] ?? 0);
    }

    /**
     * @return array{count: int, intake_url: string}
     */
    public function resolve(?User $viewer): array
    {
        if ($viewer === null) {
            return [
                'count' => 0,
                'intake_url' => route('operations.intake.index'),
            ];
        }

        $request = request();

        if ($request !== null && $request->attributes->has(self::REQUEST_CACHE_KEY)) {
            return $request->attributes->get(self::REQUEST_CACHE_KEY);
        }

        $count = RepairOrder::query()
            ->whereIn('status', WorkboardLens::intakeQueueStatusValues())
            ->count();

        $resolved = [
            'count' => $count,
            'intake_url' => route('operations.intake.index'),
        ];

        $request?->attributes->set(self::REQUEST_CACHE_KEY, $resolved);

        return $resolved;
    }
}
