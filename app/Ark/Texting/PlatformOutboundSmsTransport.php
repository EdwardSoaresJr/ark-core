<?php

namespace App\Ark\Texting;

use App\Ark\Operations\Messaging\OutboundSmsResult;
use App\Ark\Operations\Messaging\OutboundSmsTransport;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Outbound SMS via ARK Platform Texting. Stock Core never talks to Twilio.
 */
final class PlatformOutboundSmsTransport implements OutboundSmsTransport
{
    public function __construct(
        private readonly ArkTextingClient $client,
    ) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    public function send(string $toPhone, string $body, array $mediaUrls = []): OutboundSmsResult
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Outbound SMS is not configured.');
        }

        if ($mediaUrls !== []) {
            throw new RuntimeException('Attachments are not supported for ARK Texting yet.');
        }

        $result = $this->client->sendConversationMessage(
            toPhone: $toPhone,
            body: $body,
            idempotencyKey: 'core-sms:'.hash('sha256', $toPhone.'|'.$body.'|'.Str::uuid()->toString()),
            domainObjectType: 'conversation',
            domainObjectId: null,
            mediaUrls: [],
        );

        if (! ($result['ok'] ?? false)) {
            $message = is_string($result['message'] ?? null)
                ? $result['message']
                : 'ARK Texting rejected the message.';

            throw new RuntimeException($message);
        }

        $providerId = (string) ($result['provider_message_id'] ?? $result['message_id'] ?? '');
        if ($providerId === '') {
            $providerId = 'ark_sms_'.Str::uuid()->toString();
        }

        $status = (string) ($result['status'] ?? 'queued');

        return new OutboundSmsResult($providerId, $status !== '' ? $status : 'queued');
    }
}
