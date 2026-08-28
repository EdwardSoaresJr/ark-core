<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'repair_order_id',
    'created_by',
    'event_type',
    'channel',
    'direction',
    'summary',
    'conversation_message_id',
    'occurred_at',
])]
class CommunicationEvent extends Model
{
    protected function casts(): array
    {
        return [
            'event_type' => OperationalCommunicationType::class,
            'channel' => OperationalCommunicationChannel::class,
            'direction' => OperationalCommunicationDirection::class,
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): bool => throw new LogicException('Communication events are append-only.'));
        static::deleting(fn (): bool => throw new LogicException('Communication events are append-only.'));
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function conversationMessage(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class);
    }
}
