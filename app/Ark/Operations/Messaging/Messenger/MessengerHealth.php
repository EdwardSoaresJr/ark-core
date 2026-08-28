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

    public static function webhookCacheKey(string $pageId): string
    {
        return 'messenger:webhook:last_received_at:'.trim($pageId);
    }

    public static function outboundSuccessCacheKey(string $pageId): string
    {
        return 'messenger:outbound:last_success_at:'.trim($pageId);
    }

    public static function outboundFailureCacheKey(string $pageId): string
    {
        return 'messenger:outbound:last_failure_at:'.trim($pageId);
    }

    public static function rememberWebhookReceived(string $pageId): void
    {
        $pageId = trim($pageId);

        if ($pageId === '') {
            return;
        }

        cache()->put(self::webhookCacheKey($pageId), now(), now()->addDays(30));
    }

    public static function rememberOutboundSuccess(string $pageId): void
    {
        $pageId = trim($pageId);

        if ($pageId === '') {
            return;
        }

        cache()->put(self::outboundSuccessCacheKey($pageId), now(), now()->addDays(30));
    }

    public static function rememberOutboundFailure(string $pageId): void
    {
        $pageId = trim($pageId);

        if ($pageId === '') {
            return;
        }

        cache()->put(self::outboundFailureCacheKey($pageId), now(), now()->addDays(30));
    }

    public function webhookUrl(): string
    {
        return MetaMessengerPlatformConfiguration::current()->webhookUrl();
    }

    public function lastWebhookAt(): ?Carbon
    {
        $pageId = $this->shopConnection->pageId();

        if (! filled($pageId)) {
            return null;
        }

        return $this->cachedTimestamp(self::webhookCacheKey((string) $pageId))
            ?? $this->latestInboundAt();
    }

    public function lastOutboundSuccessAt(): ?Carbon
    {
        $pageId = $this->shopConnection->pageId();

        if (! filled($pageId)) {
            return null;
        }

        return $this->cachedTimestamp(self::outboundSuccessCacheKey((string) $pageId));
    }

    public function lastOutboundFailureAt(): ?Carbon
    {
        $pageId = $this->shopConnection->pageId();

        if (! filled($pageId)) {
            return null;
        }

        return $this->cachedTimestamp(self::outboundFailureCacheKey((string) $pageId));
    }

    public function webhookState(): string
    {
        if (! $this->shopConnection->isEnabled()) {
            return 'muted';
        }

        if (! $this->shopConnection->isConfigured()) {
            return 'error';
        }

        if (! MetaMessengerPlatformConfiguration::current()->isConfigured()) {
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
            'muted' => 'Disabled',
            default => 'Incomplete setup',
        };
    }

    public function webhookTone(): string
    {
        return match ($this->webhookState()) {
            'healthy' => 'success',
            'waiting' => 'warning',
            'muted' => 'muted',
            default => 'danger',
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

        $notes = [];

        if (! MetaMessengerPlatformConfiguration::current()->isConfigured()) {
            $notes[] = 'Platform Meta App credentials are missing (App Secret / Verify Token).';
        }

        if (! $this->shopConnection->isConfigured()) {
            $notes[] = 'Page connection is incomplete — save Facebook Page ID and Page access token.';
        } elseif ($this->lastWebhookAt() === null) {
            $notes[] = 'No inbound Messenger webhook has reached ARK for this Page yet.';
        }

        return $notes;
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

    private function cachedTimestamp(string $key): ?Carbon
    {
        $cached = cache()->get($key);

        if ($cached instanceof Carbon) {
            return $cached;
        }

        if (is_string($cached) && $cached !== '') {
            return Carbon::parse($cached);
        }

        return null;
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
