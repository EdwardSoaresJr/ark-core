<?php

namespace App\Ark\Operations\Staff\Duty;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffDutyPeriod extends Model
{
    protected $fillable = [
        'user_id',
        'kind',
        'started_at',
        'ends_at',
        'ended_at',
        'end_reason',
        'authorized_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'kind' => StaffDutyKind::class,
            'end_reason' => StaffDutyEndReason::class,
            'started_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
