<?php

namespace App\Ark\Operations\Conversations;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConversationAttachmentShowController
{
    public function __invoke(
        Conversation $conversation,
        ConversationMessage $message,
        ConversationMessageAttachment $attachment,
    ): StreamedResponse {
        abort_unless($message->conversation_id === $conversation->id, 404);
        abort_unless($attachment->conversation_message_id === $message->id, 404);
        abort_unless(filled($attachment->storage_path), 404);

        if (Storage::disk('local')->exists($attachment->storage_path)) {
            return Storage::disk('local')->response(
                $attachment->storage_path,
                headers: ['Content-Type' => $attachment->content_type],
            );
        }

        abort_unless(Storage::disk('public')->exists($attachment->storage_path), 404);

        return Storage::disk('public')->response(
            $attachment->storage_path,
            headers: ['Content-Type' => $attachment->content_type],
        );
    }
}
