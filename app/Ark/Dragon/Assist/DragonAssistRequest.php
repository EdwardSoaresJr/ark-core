<?php

namespace App\Ark\Dragon\Assist;

use App\Ark\Dragon\Bridge\DragonNode;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DragonAssistRequest extends Model
{
    public const MAX_ATTEMPTS = 3;

    public const ACCEPT_TIMEOUT_SECONDS = 120;

    protected $table = 'dragon_assist_requests';

    protected $fillable = [
        'public_id',
        'task_type',
        'status',
        'payload_json',
        'repair_order_id',
        'vehicle_id',
        'requested_by_user_id',
        'dragon_node_id',
        'attempt_count',
        'last_error',
        'dispatched_at',
        'accepted_at',
        'completed_at',
        'failed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'task_type' => DragonAssistTaskType::class,
            'status' => DragonAssistStatus::class,
            'payload_json' => 'array',
            'attempt_count' => 'integer',
            'dispatched_at' => 'datetime',
            'accepted_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            if (! filled($request->public_id)) {
                $request->public_id = (string) Str::uuid();
            }
            if ($request->status === null) {
                $request->status = DragonAssistStatus::Pending;
            }
            if ($request->expires_at === null) {
                $request->expires_at = Carbon::now()->addMinutes(15);
            }
        });
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(DragonNode::class, 'dragon_node_id');
    }

    public function result(): HasOne
    {
        return $this->hasOne(DragonAssistResult::class);
    }

    public function isExpired(?Carbon $now = null): bool
    {
        $now ??= Carbon::now();

        return $this->expires_at !== null && $this->expires_at->lte($now);
    }

    public function envelope(): array
    {
        return [
            'type' => 'assist.request',
            'version' => 1,
            'message_id' => (string) Str::uuid(),
            'request_id' => $this->public_id,
            'task_type' => $this->task_type->value,
            'created_at' => ($this->created_at ?? Carbon::now())->toIso8601String(),
            'payload' => $this->payload_json,
        ];
    }
}
