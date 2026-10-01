<?php

namespace App\Ark\Operations\Recommendations;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'recommendation_id',
    'type',
    'occurred_at',
    'actor_user_id',
    'repair_order_id',
    'repair_order_concern_id',
    'amount_cents',
    'reason_code',
    'note',
    'payload',
])]
class RecommendationEvent extends Model
{
    protected function casts(): array
    {
        return [
            'type' => RecommendationEventType::class,
            'occurred_at' => 'datetime',
            'amount_cents' => 'integer',
            'payload' => 'array',
        ];
    }

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(Recommendation::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function concern(): BelongsTo
    {
        return $this->belongsTo(RepairOrderConcern::class, 'repair_order_concern_id');
    }

    public function reason(): ?RecommendationDecisionReason
    {
        return RecommendationDecisionReason::optionalFrom($this->reason_code);
    }

    public function reasonLabel(): ?string
    {
        return $this->reason()?->label();
    }
}
