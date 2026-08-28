<?php

namespace App\Ark\Operations\Realtime;

use App\Ark\Operations\Telephony\CallSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SessionEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'call_session_id',
        'event_type',
        'payload',
        'actor_user_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => SessionEventType::class,
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): bool => throw new LogicException('Session events are append-only.'));
        static::deleting(fn (): bool => throw new LogicException('Session events are append-only.'));
    }

    public function callSession(): BelongsTo
    {
        return $this->belongsTo(CallSession::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
