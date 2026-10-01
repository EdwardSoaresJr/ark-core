<?php

namespace App\Ark\Operations\RepairOrders;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'repair_order_work_group_id',
    'event_kind',
    'from_owner_type',
    'from_owner_user_id',
    'to_owner_type',
    'to_owner_user_id',
    'reason',
    'actor_user_id',
    'occurred_at',
])]
class RepairActionOwnershipEvent extends Model
{
    public const KIND_ASSIGNED = 'assigned';

    public const KIND_TRANSFERRED = 'transferred';

    protected $table = 'repair_action_ownership_events';

    protected function casts(): array
    {
        return [
            'from_owner_type' => RepairActionOwnerType::class,
            'to_owner_type' => RepairActionOwnerType::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function workGroup(): BelongsTo
    {
        return $this->belongsTo(RepairOrderWorkGroup::class, 'repair_order_work_group_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function toOwnerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_owner_user_id');
    }
}
