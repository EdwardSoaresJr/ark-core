<?php

namespace App\Ark\Operations\Appointments;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Date-specific override for public appointment request acceptance.
 *
 * @property string $date
 * @property string $mode
 * @property string|null $reason
 * @property int|null $created_by_user_id
 */
class AppointmentRequestException extends Model
{
    public const MODE_ENABLE = 'enable';

    public const MODE_DISABLE = 'disable';

    protected $table = 'appointment_request_exceptions';

    protected $fillable = [
        'date',
        'mode',
        'reason',
        'created_by_user_id',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function enablesRequests(): bool
    {
        return $this->mode === self::MODE_ENABLE;
    }

    public function disablesRequests(): bool
    {
        return $this->mode === self::MODE_DISABLE;
    }
}
