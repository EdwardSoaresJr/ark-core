<?php

namespace App\Ark\Operations\Learn;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnVideoProgress extends Model
{
    protected $table = 'learn_video_progress';

    protected $fillable = [
        'user_id',
        'article_key',
        'video_key',
        'percent_watched',
        'watched_seconds',
        'completed',
        'last_position_seconds',
    ];

    protected function casts(): array
    {
        return [
            'percent_watched' => 'integer',
            'watched_seconds' => 'integer',
            'completed' => 'boolean',
            'last_position_seconds' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
