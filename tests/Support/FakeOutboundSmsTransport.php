<?php

namespace Tests\Support;

use App\Ark\Operations\Messaging\OutboundSmsResult;
use App\Ark\Operations\Messaging\OutboundSmsTransport;

final class FakeOutboundSmsTransport implements OutboundSmsTransport
{
    public function __construct(
        private readonly string $messageId = 'SMfake0001',
        private readonly string $status = 'queued',
    ) {}

    public function isConfigured(): bool
    {
        return true;
    }

    public function send(string $toPhone, string $body, array $mediaUrls = []): OutboundSmsResult
    {
        return new OutboundSmsResult(
            messageId: $this->messageId,
            status: $this->status,
        );
    }
}
