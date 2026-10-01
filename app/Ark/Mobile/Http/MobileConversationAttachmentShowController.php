<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationMessageAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MobileConversationAttachmentShowController
{
    public function __invoke(
        Request $request,
        Conversation $conversation,
        ConversationMessage $message,
        ConversationMessageAttachment $attachment,
        MobileStaffAccess $access,
    ): StreamedResponse {
        abort_unless($access->canViewConversation($request->user(), $conversation), 403);
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
