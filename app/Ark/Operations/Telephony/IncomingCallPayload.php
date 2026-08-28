<?php

namespace App\Ark\Operations\Telephony;

readonly class IncomingCallPayload
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public TelephonyProviderType $provider,
        public string $providerCallSid,
        public string $fromNumber,
        public string $toNumber,
        public string $normalizedFrom,
        public ?string $normalizedTo,
        public CallSessionStatus $status,
        public array $rawPayload,
        public CallSessionDirection $direction = CallSessionDirection::Inbound,
    ) {}
}
