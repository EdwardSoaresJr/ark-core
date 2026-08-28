<?php

namespace App\Ark\Growth\Contracts\Operations;

final readonly class LeadConvertedPayload
{
    public function __construct(
        public int $leadId,
        public int $repairOrderId,
        public ?int $conversationId = null,
    ) {}
}
