<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Operations\Leads\Lead;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicSurfaceEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event',
        'session_id',
        'lead_id',
        'attribution',
        'context',
        'occurred_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
