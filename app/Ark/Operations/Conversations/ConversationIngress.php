<?php

namespace App\Ark\Operations\Conversations;

interface ConversationIngress
{
    /**
     * Normalize a provider payload into an idempotent conversation message.
     *
     * @return array{message: ?ConversationMessage, context: ?CustomerCallContext, created: bool, conversation?: ?Conversation}
     */
    public function ingest(InboundConversationPayload $payload): array;
}
