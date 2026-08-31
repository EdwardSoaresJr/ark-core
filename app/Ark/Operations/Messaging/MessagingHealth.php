<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use Illuminate\Support\Carbon;

final class MessagingHealth
{
    public const WEBHOOK_RECEIVED_CACHE_KEY = 'messaging:last-webhook-at';

    public function __construct(
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    public function webhookUrl(): string
    {
        return '';
    }

    public function lastWebhookAt(): ?Carbon
    {
        $cached = cache()->get(self::WEBHOOK_RECEIVED_CACHE_KEY);

        if ($cached instanceof Carbon) {
            return $cached;
        }

        if (is_string($cached) && $cached !== '') {
            return Carbon::parse($cached);
        }

        $occurredAt = ConversationMessage::query()
            ->where('channel', OperationalCommunicationChannel::Sms)
            ->latest('occurred_at')
            ->latest('id')
            ->value('occurred_at');

        return $occurredAt instanceof Carbon ? $occurredAt : null;
    }

    public function webhookState(): string
    {
        if (! $this->credentials->messagingConfigured()) {
            return 'error';
        }

        if ($this->lastWebhookAt() === null) {
            return 'waiting';
        }

        return 'healthy';
    }

    public function webhookLabel(): string
    {
        return match ($this->webhookState()) {
            'healthy' => 'Healthy',
            'waiting' => 'Waiting for first message',
            default => 'Error',
        };
    }

    public function webhookTone(): string
    {
        return match ($this->webhookState()) {
            'healthy' => 'success',
            'waiting' => 'warning',
            default => 'danger',
        };
    }

    /**
     * @return list<string>
     */
    public function operationalNotes(): array
    {
        $notes = [];

        if ($this->credentials->messagingConfigured() === false) {
            $notes[] = 'Outbound SMS transport is not configured in stock Core.';
        } elseif ($this->webhookState() === 'waiting') {
            $notes[] = 'No inbound SMS has reached ARK yet.';
        }

        return $notes;
    }
}
