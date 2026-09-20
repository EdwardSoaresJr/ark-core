<?php

namespace App\Ark\Operations\Labor;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Durable compensation agreement history. User columns remain current-state projection.
 */
final class RecordTechnicianCompensationAgreementAction
{
    /**
     * Persist a new agreement version when terms change. No-op when unchanged.
     */
    public function syncCurrent(
        User $technician,
        string $laborPayBasis,
        ?int $flagRateCents,
        ?int $floorRateCents,
        ?User $actor = null,
    ): ?TechnicianCompensationAgreement {
        $current = TechnicianCompensationAgreement::currentFor((int) $technician->id);

        if ($current !== null
            && $current->labor_pay_basis === $laborPayBasis
            && $current->flag_rate_cents === $flagRateCents
            && $current->floor_rate_cents === $floorRateCents
        ) {
            return $current;
        }

        return DB::transaction(function () use (
            $technician,
            $laborPayBasis,
            $flagRateCents,
            $floorRateCents,
            $actor,
            $current,
        ): TechnicianCompensationAgreement {
            $now = now();

            $next = TechnicianCompensationAgreement::query()->create([
                'user_id' => $technician->id,
                'labor_pay_basis' => $laborPayBasis,
                'flag_rate_cents' => $flagRateCents,
                'floor_rate_cents' => $floorRateCents,
                'effective_from' => $now,
                'effective_to' => null,
                'created_by_user_id' => $actor?->id,
                'superseded_by_agreement_id' => null,
            ]);

            if ($current !== null) {
                $current->forceFill([
                    'effective_to' => $now,
                    'superseded_by_agreement_id' => $next->id,
                ])->save();
            }

            return $next;
        });
    }
}
