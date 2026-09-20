<?php

namespace App\Ark\Operations\WorkAuthorization;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permission for packaged work — not execution.
 * Owns: package type, status, scope key, outcome, recommendation.
 * Does not own: Repair Actions, Evidence, Pricing, Labor, Financial Position.
 */
class WorkAuthorization extends Model
{
    protected $table = 'work_authorizations';

    protected $fillable = [
        'repair_order_id',
        'package_type',
        'status',
        'scope_key',
        'repair_order_concern_id',
        'repair_order_work_group_id',
        'repair_order_line_id',
        'outcome',
        'recommendation',
        'authorized_by_user_id',
        'authorized_at',
        'completed_by_user_id',
        'completed_at',
        'escalates_to_authorization_id',
    ];

    protected function casts(): array
    {
        return [
            'package_type' => WorkAuthorizationPackageType::class,
            'status' => WorkAuthorizationStatus::class,
            'outcome' => TestingPackageOutcome::class,
            'authorized_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function concern(): BelongsTo
    {
        return $this->belongsTo(RepairOrderConcern::class, 'repair_order_concern_id');
    }

    public function workGroup(): BelongsTo
    {
        return $this->belongsTo(RepairOrderWorkGroup::class, 'repair_order_work_group_id');
    }

    public function packageLine(): BelongsTo
    {
        return $this->belongsTo(RepairOrderLine::class, 'repair_order_line_id');
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by_user_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function escalatesTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'escalates_to_authorization_id');
    }

    public function isLinkedAlive(): bool
    {
        return $this->repair_order_concern_id !== null
            && $this->repair_order_work_group_id !== null
            && $this->concern !== null
            && $this->workGroup !== null;
    }

    public function markCancelledOrphan(): void
    {
        $this->forceFill([
            'status' => WorkAuthorizationStatus::Cancelled,
            'repair_order_concern_id' => null,
            'repair_order_work_group_id' => null,
            'repair_order_line_id' => null,
        ])->save();
    }
}
