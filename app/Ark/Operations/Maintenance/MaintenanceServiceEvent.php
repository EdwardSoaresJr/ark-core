<?php

namespace App\Ark\Operations\Maintenance;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceServiceEvent extends Model
{
    protected $table = 'maintenance_service_events';

    protected $fillable = [
        'maintenance_service_id',
        'repair_order_id',
        'vehicle_id',
        'kind',
        'service_sequence',
        'revision',
        'superseded_by_event_id',
        'confirmed_by_user_id',
        'confirmed_at',
        'service_mileage',
        'next_due_mileage',
        'oil_brand',
        'viscosity',
        'quantity_qt',
        'filter_part',
        'washer',
        'reset_reminder',
    ];

    protected function casts(): array
    {
        return [
            'kind' => MaintenanceServiceKind::class,
            'confirmed_at' => 'datetime',
            'quantity_qt' => 'decimal:2',
            'washer' => MaintenanceWasherState::class,
            'reset_reminder' => 'boolean',
            'service_sequence' => 'integer',
            'revision' => 'integer',
            'service_mileage' => 'integer',
            'next_due_mileage' => 'integer',
        ];
    }

    public function maintenanceService(): BelongsTo
    {
        return $this->belongsTo(MaintenanceService::class);
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_event_id');
    }

    public function isCurrent(): bool
    {
        return $this->superseded_by_event_id === null;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('superseded_by_event_id');
    }

    /**
     * Latest current event for vehicle + kind (Auto Detect / history head).
     */
    public static function latestCurrentForVehicle(int $vehicleId, MaintenanceServiceKind $kind): ?self
    {
        return static::query()
            ->current()
            ->where('vehicle_id', $vehicleId)
            ->where('kind', $kind->value)
            ->orderByDesc('service_sequence')
            ->orderByDesc('revision')
            ->first();
    }
}
