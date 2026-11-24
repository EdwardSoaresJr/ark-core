<?php

namespace App\Ark\Operations\Workboard;

use App\Ark\Operations\Today\AdvisorHomeCardSurface;

/**
 * One Job Board list row per repair order (concerns summarized on the surface).
 */
final readonly class JobBoardListRow
{
    /**
     * @param  list<\App\Ark\Operations\Workboard\WorkboardCardActivityMark>  $activityMarks
     */
    public function __construct(
        public WorkboardTriageCard $card,
        public AdvisorHomeCardSurface $surface,
        public string $laneKey,
        public string $laneLabel,
        public string $laneColor,
        public ?string $technicianName,
        public ?string $technicianId,
        public ?string $partsLabel,
        public ?string $moneyLabel,
        public ?string $moneyCaption,
        public array $activityMarks,
        public string $updatedLabel,
        public int $updatedAtTimestamp,
        public string $searchHaystack,
    ) {}
}
