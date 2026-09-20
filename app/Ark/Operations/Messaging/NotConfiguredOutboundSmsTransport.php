<?php

namespace App\Ark\Operations\Messaging;

use RuntimeException;

final class NotConfiguredOutboundSmsTransport implements OutboundSmsTransport
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function send(string $toPhone, string $body, array $mediaUrls = []): OutboundSmsResult
    {
        throw new RuntimeException('Outbound SMS is not configured.');
    }
}
