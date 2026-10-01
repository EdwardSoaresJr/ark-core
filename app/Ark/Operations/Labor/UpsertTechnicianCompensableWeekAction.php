<?php

namespace App\Ark\Operations\Labor;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Upsert a week's daily compensable hours. Period totals always derive from daily rows.
 */
final class UpsertTechnicianCompensableWeekAction
{
    public function __construct(
        private readonly RecomputeTechnicianCompensableDayAction $recompute,
    ) {}

    /**
     * @param  array<string, float|int|string|null>  $hoursByDate  Y-m-d => hours
     */
    public function handle(User $technician, array $hoursByDate, ?User $actor = null): void
    {
        DB::transaction(function () use ($technician, $hoursByDate, $actor): void {
            foreach ($hoursByDate as $date => $hours) {
                $date = (string) $date;
                if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    continue;
                }

                if ($hours === null || $hours === '') {
                    TechnicianCompensableTimeEntry::query()
                        ->where('user_id', $technician->id)
                        ->whereDate('work_date', $date)
                        ->delete();

                    $this->recompute->recomputeOne($technician, $date);

                    continue;
                }

                $value = round((float) $hours, 2);
                if ($value < 0) {
                    $value = 0.0;
                }

                $existing = TechnicianCompensableTimeEntry::query()
                    ->where('user_id', $technician->id)
                    ->whereDate('work_date', $date)
                    ->first();

                $payload = [
                    'compensable_hours' => $value,
                    'source' => TechnicianCompensableTimeSource::ManualOverride->value,
                    'manual_locked' => true,
                    'recorded_by_user_id' => $actor?->id,
                ];

                if ($existing !== null) {
                    $existing->update($payload);

                    continue;
                }

                TechnicianCompensableTimeEntry::query()->create([
                    'user_id' => $technician->id,
                    'work_date' => $date,
                    ...$payload,
                ]);
            }
        });
    }
}
