<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'repair_order_id',
    'created_by',
    'communication_type',
    'channel',
    'direction',
    'summary',
    'occurred_at',
])]
/**
 * @deprecated Workflow facts now live in {@see CommunicationEvent}. Table retained for historical rows only.
 */
class OperationalCommunication extends Model
{
    protected function casts(): array
    {
        return [
            'communication_type' => OperationalCommunicationType::class,
            'channel' => OperationalCommunicationChannel::class,
            'direction' => OperationalCommunicationDirection::class,
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): bool => throw new LogicException('Operational communication events are append-only.'));
        static::deleting(fn (): bool => throw new LogicException('Operational communication events are append-only.'));
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
