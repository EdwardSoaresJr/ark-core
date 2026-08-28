<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationMessageAttachment;
use Illuminate\Support\Facades\Route;

class ConversationMessagePresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(ConversationMessage $message): array
    {
        $message->loadMissing(['participant.user', 'participant.customer', 'attachments']);

        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'participant' => $message->participant->displayLabel(),
            'direction' => $message->direction->value,
            'direction_label' => $message->direction->label(),
            'channel' => $message->channel->value,
            'channel_label' => $message->channel->label(),
            'body' => $message->body,
            'occurred_at' => $message->occurred_at?->timezone(config('app.display_timezone'))->format('M j, g:i A'),
            'attachments' => $message->attachments
                ->map(fn (ConversationMessageAttachment $attachment): array => [
                    'id' => $attachment->id,
                    'content_type' => $attachment->content_type,
                    'url' => Route::has('operations.conversation-attachments.show')
                        ? route('operations.conversation-attachments.show', [
                            'conversation' => $message->conversation_id,
                            'message' => $message->id,
                            'attachment' => $attachment,
                        ])
                        : null,
                    'is_image' => $attachment->isImage(),
                    'is_video' => $attachment->isVideo(),
                    'is_audio' => $attachment->isAudio(),
                    'is_pdf' => $attachment->isPdf(),
                ])
                ->values()
                ->all(),
            'metadata' => $message->metadata ?? [],
        ];
    }
}
