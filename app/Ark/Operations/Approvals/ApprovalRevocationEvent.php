<?php

namespace App\Ark\Operations\Approvals;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderEstimateVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'visit_id',
    'approval_event_id',
    'source',
    'revoked_by',
    'revoked_at',
    'notes',
    'recorded_by_user_id',
    'reverted_concern_ids',
])]
class ApprovalRevocationEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'source' => ApprovalSource::class,
            'revoked_at' => 'datetime',
            'reverted_concern_ids' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (ApprovalRevocationEvent $event): void {
            $repairOrder = $event->visit;

            if ($repairOrder instanceof RepairOrder) {
                app(RepairOrderEstimateVersion::class)->bump($repairOrder);
            }
        });

        static::updating(fn (): bool => throw new LogicException('Approval revocation events are immutable.'));
        static::deleting(fn (): bool => throw new LogicException('Approval revocation events are append-only.'));
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class, 'visit_id');
    }

    public function approvalEvent(): BelongsTo
    {
        return $this->belongsTo(ApprovalEvent::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
