<?php

namespace App\Ark\Operations\Learn;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnCheckpoint extends Model
{
    protected $table = 'learn_checkpoints';

    protected $fillable = [
        'user_id',
        'article_key',
        'checkpoint_key',
        'checkpoint_index',
        'active_seconds_at_reach',
        'reached_at',
    ];

    protected function casts(): array
    {
        return [
            'checkpoint_index' => 'integer',
            'active_seconds_at_reach' => 'integer',
            'reached_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
