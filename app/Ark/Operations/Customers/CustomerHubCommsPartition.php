<?php

namespace App\Ark\Operations\Customers;

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Conversations\ConversationMessage;
use Illuminate\Support\Collection;

class CustomerHubCommsPartition
{
    /**
     * @param  Collection<int, ConversationMessage>  $messages
     * @return array{
     *     text: Collection<int, ConversationMessage>,
     *     email: Collection<int, ConversationMessage>,
     *     messenger: Collection<int, ConversationMessage>,
     *     logged: Collection<int, ConversationMessage>,
     * }
     */
    public function partition(Collection $messages): array
    {
        return [
            'text' => $messages
                ->filter(fn (ConversationMessage $message): bool => $message->channel === OperationalCommunicationChannel::Sms)
                ->values(),
            'email' => $messages
                ->filter(fn (ConversationMessage $message): bool => $message->channel === OperationalCommunicationChannel::Email)
                ->values(),
            'messenger' => $messages
                ->filter(fn (ConversationMessage $message): bool => $message->channel === OperationalCommunicationChannel::Messenger)
                ->values(),
            'logged' => $messages
                ->filter(fn (ConversationMessage $message): bool => ! in_array($message->channel, [
                    OperationalCommunicationChannel::Sms,
                    OperationalCommunicationChannel::Email,
                    OperationalCommunicationChannel::Messenger,
                ], true))
                ->values(),
        ];
    }
}
