<?php

namespace App\Ark\Operations\Messaging\Messenger;

final class MetaMessengerReceiptPayload
{
    /**
     * @param  list<string>  $messageIds
     */
    public function __construct(
        public readonly string $psid,
        public readonly string $kind,
        public readonly array $messageIds = [],
        public readonly ?int $watermark = null,
    ) {}

    public function isDelivery(): bool
    {
        return $this->kind === 'delivery';
    }

    public function isRead(): bool
    {
        return $this->kind === 'read';
    }
}
