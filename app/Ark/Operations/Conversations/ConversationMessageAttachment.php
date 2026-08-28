<?php

namespace App\Ark\Operations\Conversations;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'conversation_message_id',
    'content_type',
    'storage_path',
    'provider_url',
    'provider_media_sid',
    'byte_size',
])]
class ConversationMessageAttachment extends Model
{
    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'conversation_message_id');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->content_type, 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->content_type, 'video/');
    }

    public function isPdf(): bool
    {
        return $this->content_type === 'application/pdf';
    }

    public function isAudio(): bool
    {
        return str_starts_with($this->content_type, 'audio/');
    }
}
