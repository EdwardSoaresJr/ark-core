<?php

namespace App\Ark\Growth\Models;

use App\Ark\Growth\Sessions\GrowthTouchpointType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthTouchpoint extends Model
{
    protected $fillable = [
        'growth_session_id',
        'growth_content_id',
        'type',
        'path',
        'payload',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => GrowthTouchpointType::class,
            'payload' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(GrowthSession::class, 'growth_session_id');
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(GrowthContent::class, 'growth_content_id');
    }
}
