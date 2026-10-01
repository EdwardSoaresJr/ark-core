<?php

namespace App\Ark\Operations\Labor;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicianTimeSessionCorrection extends Model
{
    protected $table = 'technician_time_session_corrections';

    protected $fillable = [
        'technician_time_session_id',
        'field',
        'from_value',
        'to_value',
        'reason',
        'corrected_by_user_id',
        'corrected_at',
    ];

    protected function casts(): array
    {
        return [
            'corrected_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TechnicianTimeSession::class, 'technician_time_session_id');
    }

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by_user_id');
    }
}
