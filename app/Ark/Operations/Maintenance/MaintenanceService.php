<?php

namespace App\Ark\Operations\Maintenance;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroup;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceService extends Model
{
    protected $table = 'maintenance_services';

    protected $fillable = [
        'repair_order_id',
        'vehicle_id',
        'kind',
        'status',
        'repair_order_concern_id',
        'repair_order_work_group_id',
        'repair_order_line_id',
        'reset_reminder',
        'prepared_oil_brand',
        'prepared_viscosity',
        'prepared_quantity_qt',
        'prepared_filter_part',
        'prepared_washer',
        'current_event_id',
    ];

    protected function casts(): array
    {
        return [
            'kind' => MaintenanceServiceKind::class,
            'status' => MaintenanceServiceStatus::class,
            'reset_reminder' => 'boolean',
            'prepared_quantity_qt' => 'decimal:2',
            'prepared_washer' => MaintenanceWasherState::class,
        ];
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
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

    public function currentEvent(): BelongsTo
    {
        return $this->belongsTo(MaintenanceServiceEvent::class, 'current_event_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MaintenanceServiceEvent::class);
    }

    public function isActive(): bool
    {
        return $this->status === MaintenanceServiceStatus::Active;
    }

    public function hasConfirmedEvent(): bool
    {
        return $this->current_event_id !== null
            || $this->status === MaintenanceServiceStatus::Confirmed;
    }

    /**
     * Session still attached to a live concern + package line on this RO.
     * Orphans (manual concern/line delete) must not block Add.
     */
    public function isLinkedAlive(): bool
    {
        if ($this->repair_order_concern_id === null || $this->repair_order_line_id === null) {
            return false;
        }

        if (! $this->relationLoaded('concern') && ! $this->relationLoaded('packageLine')) {
            return RepairOrderConcern::query()
                ->whereKey($this->repair_order_concern_id)
                ->where('repair_order_id', $this->repair_order_id)
                ->exists()
                && RepairOrderLine::query()
                    ->whereKey($this->repair_order_line_id)
                    ->where('repair_order_id', $this->repair_order_id)
                    ->exists();
        }

        return $this->concern !== null && $this->packageLine !== null;
    }

    public function markCancelledOrphan(): void
    {
        $this->update([
            'status' => MaintenanceServiceStatus::Cancelled,
            'repair_order_line_id' => null,
            'repair_order_concern_id' => null,
            'repair_order_work_group_id' => null,
        ]);
    }
}
