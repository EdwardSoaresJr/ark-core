<?php

namespace App\Ark\Operations\Display;

use App\Ark\Operations\Today\AdvisorHomeAttentionBoardProjection;
use App\Ark\Operations\Today\AdvisorHomeAttentionZone;
use App\Ark\Operations\Today\AdvisorHomeAttentionZoneKey;
use App\Ark\Operations\Today\AdvisorHomeCardSurfaceProjection;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Workboard\WorkboardTriageProjection;
use App\Ark\Operations\Workboard\WorkboardTriageRepairOrderQuery;

/**
 * Ambient shop display projection — consumes advisor home attention board truth.
 */
final readonly class ShopDisplayBoardProjection
{
    /**
     * @param  list<AdvisorHomeAttentionZone>  $attentionZones
     */
    public function __construct(
        public int $activeCarCount,
        public int $needsActionCount,
        public int $activeWorkCount,
        public int $readyPickupCount,
        public array $attentionZones,
        public string $refreshedAtLabel,
    ) {}

    public static function resolve(
        WorkboardTriageRepairOrderQuery $repairOrderQuery,
        WorkboardTriageProjection $workboardTriage,
        EstimateTotalsCalculator $totalsCalculator,
        AdvisorHomeCardSurfaceProjection $homeCardSurfaces,
        AdvisorHomeAttentionBoardProjection $attentionBoardProjection,
    ): self {
        $repairOrders = $repairOrderQuery->forAdvisor();

        $repairOrderTotals = $repairOrders->mapWithKeys(fn (RepairOrder $repairOrder): array => [
            $repairOrder->id => $totalsCalculator->totalsFor($repairOrder),
        ]);

        $homeBoardColumns = $workboardTriage->forAdvisorHomeBoard($repairOrders);
        $cardSurfaces = $homeCardSurfaces->mapForHomeBoard($repairOrders, $homeBoardColumns);

        $attentionZones = $attentionBoardProjection->zones(
            $repairOrders,
            $cardSurfaces,
            $repairOrderTotals,
        );

        return new self(
            activeCarCount: $repairOrders->count(),
            needsActionCount: self::countFor($attentionZones, AdvisorHomeAttentionZoneKey::NeedsAction),
            activeWorkCount: self::countFor($attentionZones, AdvisorHomeAttentionZoneKey::ActiveWork),
            readyPickupCount: self::countFor($attentionZones, AdvisorHomeAttentionZoneKey::ReadyPickup),
            attentionZones: $attentionZones,
            refreshedAtLabel: now()->format('g:i A'),
        );
    }

    /**
     * @param  list<AdvisorHomeAttentionZone>  $zones
     */
    private static function countFor(array $zones, AdvisorHomeAttentionZoneKey $key): int
    {
        foreach ($zones as $zone) {
            if ($zone->key === $key) {
                return $zone->count;
            }
        }

        return 0;
    }
}
