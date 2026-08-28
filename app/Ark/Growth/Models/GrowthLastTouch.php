<?php

namespace App\Ark\Growth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthLastTouch extends Model
{
    protected $fillable = [
        'growth_session_id',
        'landing_page',
        'referrer',
        'campaign',
        'search_query',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'touched_at',
    ];

    protected function casts(): array
    {
        return [
            'touched_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(GrowthSession::class, 'growth_session_id');
    }
}
