<?php

namespace App\Ark\Growth\Models;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GrowthSession extends Model
{
    protected $fillable = [
        'visitor_id',
        'laravel_session_id',
        'started_at',
        'first_landing_page',
        'first_referrer',
        'first_search_query',
        'first_campaign',
        'first_growth_content_id',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'device',
        'country',
        'state',
        'city',
        'identity_confidence_score',
        'identity_confidence_reason',
        'identity_confidence_evidence',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'visitor_id' => 'string',
            'started_at' => 'datetime',
            'identity_confidence_score' => 'integer',
            'identity_confidence_evidence' => 'array',
            'metadata' => 'array',
        ];
    }

    public function firstContent(): BelongsTo
    {
        return $this->belongsTo(GrowthContent::class, 'first_growth_content_id');
    }

    public function touchpoints(): HasMany
    {
        return $this->hasMany(GrowthTouchpoint::class)->orderBy('recorded_at');
    }

    public function lastTouch(): HasOne
    {
        return $this->hasOne(GrowthLastTouch::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function repairOrders(): HasMany
    {
        return $this->hasMany(RepairOrder::class);
    }
}
