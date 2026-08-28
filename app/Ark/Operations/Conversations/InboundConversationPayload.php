<?php

namespace App\Ark\Operations\Conversations;

use App\Ark\Operations\Communications\OperationalCommunicationChannel;

final class InboundConversationPayload
{
    /**
     * @param  list<array{url: string, content_type: string, provider_media_sid: ?string}>  $media
     */
    public function __construct(
        public readonly ConversationContactSurface $contactSurface,
        public readonly string $contactKey,
        public readonly string $providerMessageId,
        public readonly OperationalCommunicationChannel $channel,
        public readonly string $body,
        public readonly array $media = [],
        public readonly array $metadata = [],
        public readonly ?string $contactDisplay = null,
    ) {}

    public function isProcessable(): bool
    {
        return $this->providerMessageId !== '' && $this->contactKey !== '';
    }
}
