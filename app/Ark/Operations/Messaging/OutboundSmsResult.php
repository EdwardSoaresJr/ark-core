<?php

namespace App\Ark\Operations\Messaging;

final class OutboundSmsResult
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $status,
    ) {}
}
