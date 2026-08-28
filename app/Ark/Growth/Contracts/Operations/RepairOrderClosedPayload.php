<?php

namespace App\Ark\Growth\Contracts\Operations;

final readonly class RepairOrderClosedPayload
{
    /**
     * @param  array<string, mixed>  $attribution
     */
    public function __construct(
        public int $repairOrderId,
        public int $revenueCents,
        public ?int $leadId = null,
        public ?int $conversationId = null,
        public ?int $growthSessionId = null,
        public ?string $landingPage = null,
        public ?string $searchQuery = null,
        public ?string $source = null,
        public ?string $campaign = null,
        public ?string $referrer = null,
        public array $attribution = [],
    ) {}
}
