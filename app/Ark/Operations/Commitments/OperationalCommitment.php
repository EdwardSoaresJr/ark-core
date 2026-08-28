<?php

namespace App\Ark\Operations\Commitments;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'repair_order_id',
    'owner_user_id',
    'created_by',
    'type',
    'status',
    'reason',
    'due_at',
    'fulfilled_at',
    'fulfilled_by',
])]
class OperationalCommitment extends Model
{
    protected function casts(): array
    {
        return [
            'type' => CommitmentType::class,
            'status' => CommitmentStatus::class,
            'due_at' => 'datetime',
            'fulfilled_at' => 'datetime',
        ];
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fulfiller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fulfilled_by');
    }

    public function isOpen(): bool
    {
        return $this->status === CommitmentStatus::Open;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', CommitmentStatus::Open->value);
    }
}
