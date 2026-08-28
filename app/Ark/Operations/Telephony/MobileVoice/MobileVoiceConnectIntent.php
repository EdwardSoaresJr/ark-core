<?php

namespace App\Ark\Operations\Telephony\MobileVoice;

final readonly class MobileVoiceConnectIntent
{
    public function __construct(
        public int $initiatedByUserId,
        public int $mobileDeviceId,
        public int $endpointId,
        public string $customerE164,
        public string $normalizedCustomerPhone,
        public ?int $customerId = null,
        public ?int $repairOrderId = null,
    ) {}
}
