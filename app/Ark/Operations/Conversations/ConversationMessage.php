<?php

namespace App\Ark\Operations\Conversations;

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'conversation_id',
    'conversation_participant_id',
    'channel',
    'direction',
    'body',
    'occurred_at',
    'metadata',
])]
class ConversationMessage extends Model
{
    protected function casts(): array
    {
        return [
            'channel' => OperationalCommunicationChannel::class,
            'direction' => OperationalCommunicationDirection::class,
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): bool => throw new LogicException('Conversation messages are append-only.'));
        static::deleting(fn (): bool => throw new LogicException('Conversation messages are append-only.'));
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(ConversationParticipant::class, 'conversation_participant_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ConversationMessageAttachment::class);
    }

    public function providerMessageSid(): ?string
    {
        $sid = $this->metadata['twilio_message_sid'] ?? null;

        return is_string($sid) && $sid !== '' ? $sid : null;
    }
}
