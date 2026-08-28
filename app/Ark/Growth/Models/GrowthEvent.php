<?php

namespace App\Ark\Growth\Models;

use App\Ark\Growth\Events\GrowthEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthEvent extends Model
{
    protected $fillable = [
        'type',
        'visitor_id',
        'growth_session_id',
        'growth_content_id',
        'path',
        'payload',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => GrowthEventType::class,
            'visitor_id' => 'string',
            'payload' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(GrowthContent::class, 'growth_content_id');
    }
}
