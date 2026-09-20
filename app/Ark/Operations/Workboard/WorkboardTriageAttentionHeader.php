<?php

namespace App\Ark\Operations\Workboard;

final readonly class WorkboardTriageAttentionHeader
{
    public function __construct(
        public int $needsAttention,
        public int $customerWaiting,
        public int $unassigned,
        public int $overduePickup,
        public ?string $needsAttentionUrl,
        public ?string $customerWaitingUrl,
        public ?string $unassignedUrl,
        public ?string $overduePickupUrl,
    ) {}
}
