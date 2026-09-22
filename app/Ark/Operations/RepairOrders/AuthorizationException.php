<?php

namespace App\Ark\Operations\RepairOrders;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuthorizationException extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'repair_order_id',
        'repair_order_concern_id',
        'reason',
        'note',
        'line_ids',
        'recorded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'reason' => AuthorizationExceptionReason::class,
            'line_ids' => 'array',
            'establishes_customer_consent' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $exception): void {
            $exception->establishes_customer_consent = false;
        });
        static::updating(fn (): bool => throw new LogicException('Authorization exceptions are append-only.'));
        static::deleting(fn (): bool => throw new LogicException('Authorization exceptions are append-only.'));
    }

    public function concern(): BelongsTo
    {
        return $this->belongsTo(RepairOrderConcern::class, 'repair_order_concern_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /**
     * @return list<int>
     */
    public function lineIds(): array
    {
        return array_values(array_map(
            static fn (mixed $id): int => (int) $id,
            is_array($this->line_ids) ? $this->line_ids : [],
        ));
    }
}
