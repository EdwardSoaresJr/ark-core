<?php

namespace App\Ark\Operations\Messaging\Messenger;

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationMessage;
use Illuminate\Support\Carbon;
use RuntimeException;

class MessengerMessagingWindow
{
    public const REPLY_WINDOW_HOURS = 24;

    public function lastInboundAt(string $psid): ?Carbon
    {
        $normalized = trim($psid);

        if ($normalized === '') {
            return null;
        }

        $conversationIds = Conversation::query()
            ->where('contact_surface', ConversationContactSurface::Messenger)
            ->where('contact_address', $normalized)
            ->pluck('id');

        if ($conversationIds->isEmpty()) {
            return null;
        }

        $occurredAt = ConversationMessage::query()
            ->whereIn('conversation_id', $conversationIds)
            ->where('channel', OperationalCommunicationChannel::Messenger)
            ->where('direction', OperationalCommunicationDirection::Inbound)
            ->latest('occurred_at')
            ->latest('id')
            ->value('occurred_at');

        return $occurredAt instanceof Carbon ? $occurredAt : null;
    }

    public function resolveSendRequest(string $psid, ?MetaMessengerMessageTag $messageTag, ?MetaMessengerMessageTag $defaultTag = null): MetaMessengerSendRequest
    {
        $lastInbound = $this->lastInboundAt($psid);

        if ($lastInbound === null) {
            throw new RuntimeException('Customer must message your Facebook Page before you can reply on Messenger.');
        }

        if ($lastInbound->gte(now()->subHours(self::REPLY_WINDOW_HOURS))) {
            return MetaMessengerSendRequest::response();
        }

        $tag = $messageTag ?? $defaultTag;

        if ($tag === null) {
            throw new RuntimeException('Messenger 24-hour reply window has expired. Select a message tag or wait for the customer to message again.');
        }

        return MetaMessengerSendRequest::messageTag($tag);
    }
}
