<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Workstations\Workstation;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id',
    'vehicle_id',
    'advisor_user_id',
    'technician_user_id',
    'workstation_id',
    'repair_order_id',
    'created_by_user_id',
    'starts_at',
    'ends_at',
    'estimated_labor_hours',
    'concern',
    'notes',
    'status',
    'canceled_at',
    'arrived_at',
    'no_show_at',
    'kind',
    'confirmation_sms_sent_at',
    'reminder_day_before',
    'reminder_hours_before',
    'reminder_day_before_sent_at',
    'reminder_hours_before_sent_at',
])]
class Appointment extends Model
{
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'estimated_labor_hours' => 'decimal:2',
            'status' => AppointmentStatus::class,
            'kind' => AppointmentKind::class,
            'canceled_at' => 'datetime',
            'arrived_at' => 'datetime',
            'no_show_at' => 'datetime',
            'confirmation_sms_sent_at' => 'datetime',
            'reminder_day_before' => 'boolean',
            'reminder_hours_before' => 'integer',
            'reminder_day_before_sent_at' => 'datetime',
            'reminder_hours_before_sent_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_user_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_user_id');
    }

    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isUpcoming(): bool
    {
        return $this->status->isUpcoming();
    }
}
