<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Conversations\ConversationMessage;
use Illuminate\Support\Facades\Blade;

class ConversationMessageRenderer
{
    public function render(ConversationMessage $message, string $class = ''): string
    {
        $message->loadMissing(['participant.user', 'participant.customer', 'attachments']);

        return Blade::render(
            '<x-operations.conversation-message :message="$message" :class="$class" />',
            ['message' => $message, 'class' => $class],
        );
    }
}
