<?php

namespace App\Ark\Operations\Labor;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TechnicianCompensableTimeEntry extends Model
{
    protected $table = 'technician_compensable_time_entries';

    protected $fillable = [
        'user_id',
        'work_date',
        'compensable_hours',
        'source',
        'manual_locked',
        'recorded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'compensable_hours' => 'decimal:2',
            'manual_locked' => 'boolean',
        ];
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function sourceEnum(): TechnicianCompensableTimeSource
    {
        return TechnicianCompensableTimeSource::tryFrom((string) $this->source)
            ?? TechnicianCompensableTimeSource::ManualOverride;
    }

    public function isManualLocked(): bool
    {
        return (bool) $this->manual_locked
            || $this->sourceEnum() === TechnicianCompensableTimeSource::ManualOverride;
    }

    /**
     * @return Collection<int, self>
     */
    public static function forTechnicianInRange(int $userId, Carbon $from, Carbon $to): Collection
    {
        return static::query()
            ->where('user_id', $userId)
            ->whereDate('work_date', '>=', $from->toDateString())
            ->whereDate('work_date', '<=', $to->toDateString())
            ->orderBy('work_date')
            ->get();
    }

    public static function totalHoursForTechnicianInRange(int $userId, Carbon $from, Carbon $to): float
    {
        return round((float) static::forTechnicianInRange($userId, $from, $to)->sum('compensable_hours'), 2);
    }
}
