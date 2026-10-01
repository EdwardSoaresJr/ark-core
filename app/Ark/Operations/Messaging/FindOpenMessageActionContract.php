<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationResolver;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\PhoneNumber;

final class FindOpenMessageActionContract
{
    public function __construct(
        private readonly CustomerCallContextResolver $callContextResolver,
        private readonly ConversationResolver $conversationResolver,
    ) {}

    public function forPhone(string $fromPhone): ?ConversationMessage
    {
        $normalized = PhoneNumber::normalize($fromPhone);

        if ($normalized === null) {
            return null;
        }

        $context = $this->callContextResolver->resolve($normalized);
        $conversation = null;

        if ($context?->customer !== null) {
            $conversation = $this->conversationResolver->forCustomer($context->customer);
        }

        if (! $conversation instanceof Conversation) {
            return null;
        }

        $candidates = ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('channel', OperationalCommunicationChannel::Sms)
            ->where('direction', OperationalCommunicationDirection::Outbound)
            ->where('occurred_at', '>=', now()->subDays(14))
            ->orderByDesc('occurred_at')
            ->limit(25)
            ->get();

        foreach ($candidates as $message) {
            if (MessageActionContract::isOpen($message)) {
                return $message;
            }
        }

        return null;
    }
}
