<?php

namespace App\Ark\Operations\Labor;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class TechnicianCompensationAgreement extends Model
{
    protected $table = 'technician_compensation_agreements';

    protected $fillable = [
        'user_id',
        'labor_pay_basis',
        'flag_rate_cents',
        'floor_rate_cents',
        'effective_from',
        'effective_to',
        'created_by_user_id',
        'superseded_by_agreement_id',
    ];

    protected function casts(): array
    {
        return [
            'flag_rate_cents' => 'integer',
            'floor_rate_cents' => 'integer',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_agreement_id');
    }

    public function payBasis(): TechnicianLaborPayBasis
    {
        return TechnicianLaborPayBasis::tryFrom((string) $this->labor_pay_basis)
            ?? TechnicianLaborPayBasis::Hourly;
    }

    public function isCurrent(): bool
    {
        return $this->effective_to === null;
    }

    /**
     * Agreement applicable at a point in time (for Phase 1B composition).
     */
    public static function applicableAt(int $userId, Carbon $at): ?self
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('effective_from', '<=', $at)
            ->where(function (Builder $query) use ($at): void {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>', $at);
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    public static function currentFor(int $userId): ?self
    {
        return static::query()
            ->where('user_id', $userId)
            ->whereNull('effective_to')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }
}
