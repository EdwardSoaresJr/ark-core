<?php

namespace App\Ark\Growth\Models;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthAttribution extends Model
{
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \RuntimeException('Growth attribution records are immutable.');
        });

        static::deleting(function (): void {
            throw new \RuntimeException('Growth attribution records are immutable.');
        });
    }

    protected $fillable = [
        'growth_session_id',
        'repair_order_id',
        'lead_id',
        'conversation_id',
        'growth_content_id',
        'source',
        'campaign',
        'landing_page',
        'search_query',
        'referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'revenue_cents',
        'gross_profit_cents',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'revenue_cents' => 'integer',
            'gross_profit_cents' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(GrowthContent::class, 'growth_content_id');
    }

    public function revenueDollars(): float
    {
        return round($this->revenue_cents / 100, 2);
    }
}
