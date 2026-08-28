<?php

namespace App\Ark\Operations\Messaging;

final class TwilioOutboundResult
{
    public function __construct(
        public readonly string $messageSid,
        public readonly string $status,
    ) {}
}
