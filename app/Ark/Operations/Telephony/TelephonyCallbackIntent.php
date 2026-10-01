<?php

namespace App\Ark\Operations\Telephony;

final readonly class TelephonyCallbackIntent
{
    public function __construct(
        public int $initiatedByUserId,
        public int $endpointId,
        public string $customerE164,
        public string $normalizedCustomerPhone,
        public ?int $customerId = null,
        public ?int $repairOrderId = null,
    ) {}
}
