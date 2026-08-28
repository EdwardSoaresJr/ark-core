<?php

namespace App\Ark\Operations\Today;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TodayRecommendationSnooze extends Model
{
    protected $fillable = [
        'user_id',
        'repair_order_id',
        'snoozed_at',
        'snoozed_until',
    ];

    protected function casts(): array
    {
        return [
            'repair_order_id' => 'integer',
            'snoozed_at' => 'datetime',
            'snoozed_until' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->snoozed_until !== null
            && $this->snoozed_until->isFuture();
    }
}
