<?php

namespace App\Ark\Operations\Events;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'event_name',
    'aggregate_type',
    'aggregate_id',
    'actor_user_id',
    'occurred_at',
    'payload_json',
])]
class OperationalEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'operational_events';

    protected function casts(): array
    {
        return [
            'aggregate_id' => 'integer',
            'actor_user_id' => 'integer',
            'occurred_at' => 'datetime',
            'payload_json' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): bool => throw new LogicException('Operational events are immutable.'));
        static::deleting(fn (): bool => throw new LogicException('Operational events are append-only.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
