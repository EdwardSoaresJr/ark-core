<?php

namespace App\Ark\Operations\Messaging\Messenger;

final class MetaMessengerSendRequest
{
    public function __construct(
        public readonly string $messagingType,
        public readonly ?MetaMessengerMessageTag $tag = null,
    ) {}

    public static function response(): self
    {
        return new self(messagingType: 'RESPONSE');
    }

    public static function messageTag(MetaMessengerMessageTag $tag): self
    {
        return new self(messagingType: 'MESSAGE_TAG', tag: $tag);
    }
}
