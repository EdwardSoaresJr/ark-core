<?php

namespace App\Ark\Operations\Labor;

use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TechnicianFlagRecognition extends Model
{
    protected $table = 'technician_flag_recognitions';

    protected $fillable = [
        'repair_order_id',
        'repair_order_concern_id',
        'technician_user_id',
        'recognized_at',
        'flag_hours_total',
        'source_operational_event_id',
        'recognition_policy',
        'recognition_policy_version',
        'actor_user_id',
        'technician_attribution_source',
    ];

    protected function casts(): array
    {
        return [
            'recognized_at' => 'datetime',
            'flag_hours_total' => 'decimal:2',
            'recognition_policy_version' => 'integer',
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

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_user_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function sourceEvent(): BelongsTo
    {
        return $this->belongsTo(OperationalEvent::class, 'source_operational_event_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(TechnicianFlagRecognitionLine::class, 'technician_flag_recognition_id');
    }
}
