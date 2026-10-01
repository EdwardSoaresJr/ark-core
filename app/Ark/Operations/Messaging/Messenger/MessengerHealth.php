<?php

namespace App\Ark\Operations\Messaging\Messenger;

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\ConversationMessage;
use Illuminate\Support\Carbon;

class MessengerHealth
{
    public function __construct(
        private readonly MessengerShopConnection $shopConnection,
    ) {}

    public static function forCurrentShop(): self
    {
        return self::forShopConnection(MessengerShopConnection::current());
    }

    public static function forShopConnection(MessengerShopConnection $shopConnection): self
    {
        return new self($shopConnection);
    }

    public function webhookUrl(): string
    {
        return '';
    }

    public function lastWebhookAt(): ?Carbon
    {
        return $this->latestInboundAt();
    }

    public function lastOutboundSuccessAt(): ?Carbon
    {
        return null;
    }

    public function lastOutboundFailureAt(): ?Carbon
    {
        return null;
    }

    public function webhookState(): string
    {
        if (! $this->shopConnection->isEnabled()) {
            return 'muted';
        }

        return 'waiting';
    }

    public function webhookLabel(): string
    {
        return match ($this->webhookState()) {
            'muted' => 'Disabled',
            default => 'Not configured',
        };
    }

    public function webhookTone(): string
    {
        return match ($this->webhookState()) {
            'muted' => 'muted',
            default => 'warning',
        };
    }

    /**
     * @return list<string>
     */
    public function operationalNotes(): array
    {
        if (! $this->shopConnection->isEnabled()) {
            return [];
        }

        return ['Messenger transport is not configured in Core.'];
    }

    public function formatTimestamp(?Carbon $timestamp): ?string
    {
        if ($timestamp === null) {
            return null;
        }

        return $timestamp
            ->timezone(config('app.display_timezone'))
            ->format('M j, Y g:i A');
    }

    public function formatRelative(?Carbon $timestamp): ?string
    {
        if ($timestamp === null) {
            return null;
        }

        return $timestamp->diffForHumans(short: true);
    }

    private function latestInboundAt(): ?Carbon
    {
        $pageId = $this->shopConnection->pageId();

        if (! filled($pageId)) {
            return null;
        }

        $occurredAt = ConversationMessage::query()
            ->where('channel', OperationalCommunicationChannel::Messenger)
            ->where('direction', OperationalCommunicationDirection::Inbound)
            ->where('metadata->page_id', (string) $pageId)
            ->latest('occurred_at')
            ->latest('id')
            ->value('occurred_at');

        return $occurredAt instanceof Carbon ? $occurredAt : null;
    }
}
