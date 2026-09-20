<?php

namespace App\Ark\Operations\Workboard;

final readonly class WorkboardTriageBoardProjection
{
    /**
     * @param  list<WorkboardTriageSwimlaneProjection>  $swimlanes
     */
    public function __construct(
        public WorkboardTriageAttentionHeader $attentionHeader,
        public array $swimlanes,
        public ?WorkboardPickupOverflowProjection $pickupOverflow,
        public int $queueCount,
        public bool $focusAttention,
    ) {}
}
