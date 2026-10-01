<?php

namespace App\Ark\Operations\Staff;

use App\Ark\Operations\Telephony\CallSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffCoachingLog extends Model
{
    protected $fillable = [
        'call_session_id',
        'staff_user_id',
        'recorded_by_user_id',
        'notes',
        'discussed_at',
    ];

    protected function casts(): array
    {
        return [
            'discussed_at' => 'datetime',
        ];
    }

    public function callSession(): BelongsTo
    {
        return $this->belongsTo(CallSession::class);
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
