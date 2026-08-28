<?php

namespace App\Ark\Operations\Workboard;

final readonly class WorkboardQueueWorkspaceProjection
{
    /**
     * @param  list<WorkboardQueueNavGroup>  $navGroups
     * @param  list<WorkboardTriageCard>  $visibleCards
     */
    public function __construct(
        public array $navGroups,
        public ?string $selectedQueueKey,
        public ?string $selectedQueueLabel,
        public int $selectedQueueCount,
        public array $visibleCards,
        public int $hiddenCount,
        public ?string $viewAllUrl,
        public ?WorkboardPickupOverflowProjection $pickupOverflow,
        public int $queueCount,
        public bool $boardIsEmpty,
        public int $needsAttentionRollup = 0,
        public ?string $needsAttentionRollupUrl = null,
        public bool $needsAttentionRollupIsActive = false,
    ) {}
}
