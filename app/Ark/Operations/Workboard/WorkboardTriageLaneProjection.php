<?php

namespace App\Ark\Operations\Workboard;

final readonly class WorkboardTriageLaneProjection
{
    /**
     * @param  list<WorkboardTriageCard>  $visibleCards
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $tone,
        public int $totalCount,
        public array $visibleCards,
        public int $hiddenCount,
        public ?string $viewAllUrl,
        public ?string $inventoryUrl,
    ) {}
}
