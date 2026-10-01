<?php

namespace App\Ark\Operations\Learn;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnSession extends Model
{
    protected $table = 'learn_sessions';

    protected $fillable = [
        'user_id',
        'article_key',
        'active_seconds',
        'last_interaction_at',
        'last_heartbeat_at',
    ];

    protected function casts(): array
    {
        return [
            'active_seconds' => 'integer',
            'last_interaction_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
