<?php

namespace App\Ark\Operations\Approvals;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderEstimateVersion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

#[Fillable([
    'visit_id',
    'estimate_snapshot_reference',
    'approval_type',
    'approved_amount_cents',
    'source',
    'approved_by',
    'approved_at',
    'notes',
    'signature_path',
])]
class ApprovalEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'approval_type' => ApprovalType::class,
            'approved_amount_cents' => 'integer',
            'source' => ApprovalSource::class,
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (ApprovalEvent $event): void {
            $repairOrder = $event->visit;

            if ($repairOrder instanceof RepairOrder) {
                app(RepairOrderEstimateVersion::class)->bump($repairOrder);
            }
        });

        static::updating(fn (): bool => throw new LogicException('Approval events are immutable.'));
        static::deleting(fn (): bool => throw new LogicException('Approval events are append-only.'));
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class, 'visit_id');
    }

    public function revocation(): HasOne
    {
        return $this->hasOne(ApprovalRevocationEvent::class);
    }

    public function isRevoked(): bool
    {
        if ($this->relationLoaded('revocation')) {
            return $this->revocation !== null;
        }

        return $this->revocation()->exists();
    }
}
