<?php

namespace App\Ark\Operations\Learn;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnCompletion extends Model
{
    protected $table = 'learn_completions';

    protected $fillable = [
        'user_id',
        'article_key',
        'catalog_version',
        'article_version',
        'active_seconds',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'catalog_version' => 'integer',
            'article_version' => 'integer',
            'active_seconds' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
