<?php

namespace App\Ark\Operations\Workboard;

final readonly class WorkboardPickupOverflowProjection
{
    public function __construct(
        public int $totalAwaitingPickup,
        public int $staleCount,
        public string $viewQueueUrl,
    ) {}
}
