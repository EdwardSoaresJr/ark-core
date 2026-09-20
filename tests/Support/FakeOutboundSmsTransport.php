<?php

namespace Tests\Support;

use App\Ark\Operations\Messaging\OutboundSmsResult;
use App\Ark\Operations\Messaging\OutboundSmsTransport;
use RuntimeException;

final class FakeOutboundSmsTransport implements OutboundSmsTransport
{
    /** @var list<array{to: string, body: string, media: list<string>}> */
    public array $sent = [];

    public function __construct(
        private readonly string $messageId = 'SMfake0001',
        private readonly string $status = 'queued',
        private readonly bool $fail = false,
        private readonly string $failureMessage = 'Outbound SMS failed.',
    ) {}

    public function isConfigured(): bool
    {
        return true;
    }

    public function send(string $toPhone, string $body, array $mediaUrls = []): OutboundSmsResult
    {
        if ($this->fail) {
            throw new RuntimeException($this->failureMessage);
        }

        $this->sent[] = [
            'to' => $toPhone,
            'body' => $body,
            'media' => $mediaUrls,
        ];

        return new OutboundSmsResult(
            messageId: $this->messageId,
            status: $this->status,
        );
    }
}
