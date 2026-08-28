<?php

namespace App\Ark\Operations\Labor;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class TechnicianTimeSession extends Model
{
    protected $table = 'technician_time_sessions';

    protected $fillable = [
        'user_id',
        'clocked_in_at',
        'clocked_out_at',
        'clocked_in_by_user_id',
        'clocked_out_by_user_id',
        'status',
        'close_reason',
        'origin',
    ];

    protected function casts(): array
    {
        return [
            'clocked_in_at' => 'datetime',
            'clocked_out_at' => 'datetime',
        ];
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function clockedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'clocked_in_by_user_id');
    }

    public function clockedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'clocked_out_by_user_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(TechnicianTimeSessionCorrection::class, 'technician_time_session_id')
            ->orderByDesc('corrected_at');
    }

    public function isOpen(): bool
    {
        return $this->status === TechnicianTimeSessionStatus::Open->value;
    }

    public function isNeedsResolution(): bool
    {
        return $this->status === TechnicianTimeSessionStatus::NeedsResolution->value;
    }

    public function isDeleted(): bool
    {
        return $this->status === TechnicianTimeSessionStatus::Deleted->value;
    }

    public function statusEnum(): TechnicianTimeSessionStatus
    {
        return TechnicianTimeSessionStatus::from((string) $this->status);
    }

    public function closeReasonEnum(): ?TechnicianTimeSessionCloseReason
    {
        return $this->close_reason !== null
            ? TechnicianTimeSessionCloseReason::tryFrom((string) $this->close_reason)
            : null;
    }

    public function originEnum(): ?TechnicianTimeSessionOrigin
    {
        return $this->origin !== null
            ? TechnicianTimeSessionOrigin::tryFrom((string) $this->origin)
            : null;
    }

    /**
     * The technician's unresolved punch (open or overnight needs_resolution) — never a closed or deleted session.
     */
    public static function openForTechnician(int $userId): ?self
    {
        return static::query()
            ->where('user_id', $userId)
            ->whereNull('clocked_out_at')
            ->whereIn('status', [
                TechnicianTimeSessionStatus::Open->value,
                TechnicianTimeSessionStatus::NeedsResolution->value,
            ])
            ->latest('clocked_in_at')
            ->first();
    }

    /**
     * Active punches only — deleted sessions remain for audit but never feed hours.
     */
    public function scopeActive($query)
    {
        return $query->where('status', '!=', TechnicianTimeSessionStatus::Deleted->value);
    }

    /**
     * Elapsed seconds to now (open sessions) or clock-out (closed sessions).
     */
    public function elapsedSeconds(): int
    {
        if ($this->clocked_in_at === null) {
            return 0;
        }

        $end = $this->clocked_out_at ?? Carbon::now();

        return max(0, (int) $this->clocked_in_at->diffInSeconds($end, true));
    }

    public function durationHours(): float
    {
        return round($this->elapsedSeconds() / 3600, 2);
    }
}
