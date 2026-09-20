<?php

namespace App\Ark\Operations\Labor;

use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Phase 1B composition — management visibility + base compensation assist.
 * Not payroll authority.
 */
final class TechnicianProductionAssistProjection
{
    public static function recognitionAuthorityStartsAt(): Carbon
    {
        return Carbon::parse((string) config('technician_compensation.recognition_authority_starts_at', '2026-07-27'))
            ->startOfDay();
    }

    public static function weeklyOtThreshold(): float
    {
        return (float) config('technician_compensation.overtime_review.weekly_hours_threshold', 40);
    }

    public static function dailyOtThreshold(): float
    {
        return (float) config('technician_compensation.overtime_review.daily_hours_threshold', 12);
    }

    /**
     * Default Mon–Sun shop week containing $anchor (or previous week).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function defaultWeekRange(?Carbon $anchor = null): array
    {
        $anchor ??= now();
        $from = $anchor->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $to = $from->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay();

        return [$from, $to];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function shopSummaries(Carbon $from, Carbon $to): array
    {
        $technicians = User::query()
            ->role(ArkRole::Technician->value)
            ->active()
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($technicians as $technician) {
            $detail = self::forTechnician($technician, $from, $to);
            if (! ($detail['applies'] ?? false)) {
                continue;
            }
            $rows[] = $detail['summary'];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public static function forTechnician(User $technician, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();
        $adoption = self::recognitionAuthorityStartsAt();

        $isFlag = $technician->laborPayBasis() === TechnicianLaborPayBasis::Flag
            || TechnicianCompensationAgreement::currentFor((int) $technician->id)?->payBasis() === TechnicianLaborPayBasis::Flag
            || TechnicianFlagRecognition::query()->where('technician_user_id', $technician->id)->exists();

        if (! $isFlag && $technician->flag_rate_cents === null && $technician->floor_rate_cents === null) {
            // Still show Flag-basis from any historical agreement covering the period.
            $hasAgreementInRange = TechnicianCompensationAgreement::query()
                ->where('user_id', $technician->id)
                ->where('labor_pay_basis', TechnicianLaborPayBasis::Flag->value)
                ->where('effective_from', '<=', $to->copy()->endOfDay())
                ->where(function ($q) use ($from): void {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>', $from);
                })
                ->exists();
            $isFlag = $hasAgreementInRange;
        }

        if (! $isFlag) {
            return [
                'applies' => false,
                'summary' => null,
                'detail' => null,
            ];
        }

        $historyUnavailable = $to->lt($adoption);
        $recognitionFrom = $from->copy()->max($adoption);

        $timeEntries = TechnicianCompensableTimeEntry::forTechnicianInRange((int) $technician->id, $from, $to);
        $clockHours = round((float) $timeEntries->sum('compensable_hours'), 2);

        $dailyTime = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $dateKey = $cursor->toDateString();
            $entry = $timeEntries->first(fn (TechnicianCompensableTimeEntry $e): bool => $e->work_date->toDateString() === $dateKey);
            $hours = $entry !== null ? (float) $entry->compensable_hours : null;
            $agreement = TechnicianCompensationAgreement::applicableAt((int) $technician->id, $cursor->copy()->startOfDay());
            $floorRateCents = $agreement?->floor_rate_cents;
            $dailyTime[] = [
                'date' => $dateKey,
                'weekday' => $cursor->format('D'),
                'compensable_hours' => $hours,
                'floor_rate_cents' => $floorRateCents,
                'floor_rate_dollars' => $floorRateCents !== null ? round($floorRateCents / 100, 2) : null,
                'floor_amount_cents' => ($hours !== null && $floorRateCents !== null)
                    ? (int) round($hours * $floorRateCents)
                    : null,
            ];
            $cursor->addDay();
        }

        $otWarning = self::overtimeWarning($dailyTime, $clockHours);

        if ($historyUnavailable) {
            return self::packResult($technician, $from, $to, [
                'history_unavailable' => true,
                'history_unavailable_reason' => 'Production history unavailable for this period — flag recognition began '.$adoption->toDateString().'.',
                'clock_hours' => $clockHours,
                'recognized_flag_hours' => null,
                'pending_flag_hours' => null,
                'recognized_efficiency_percent' => null,
                'production_in_view_percent' => null,
                'recognized_earnings_cents' => null,
                'floor_requirement_cents' => null,
                'floor_exposure_cents' => null,
                'base_compensation_assist_cents' => null,
                'pending_value_cents' => null,
                'overtime_review_required' => $otWarning['required'],
                'overtime_warning' => $otWarning,
                'daily_time' => $dailyTime,
                'recognized_lines' => [],
                'pending_lines' => [],
                'unassigned_pending_lines' => self::unassignedPendingLines(),
                'calculation' => null,
                'policy' => self::policyBlock(),
            ]);
        }

        $recognitions = TechnicianFlagRecognition::query()
            ->with(['lines', 'repairOrder.vehicle', 'concern'])
            ->where('technician_user_id', $technician->id)
            ->where('recognized_at', '>=', $recognitionFrom->copy()->startOfDay())
            ->where('recognized_at', '<=', $to->copy()->endOfDay())
            ->orderBy('recognized_at')
            ->get();

        $recognizedLines = [];
        $recognizedHours = 0.0;
        $recognizedEarningsCents = 0;

        foreach ($recognitions as $recognition) {
            $agreement = TechnicianCompensationAgreement::applicableAt(
                (int) $technician->id,
                Carbon::parse($recognition->recognized_at),
            );
            $flagRateCents = $agreement?->flag_rate_cents;
            $vehicle = $recognition->repairOrder?->vehicle;
            $vehicleLabel = $vehicle
                ? trim(($vehicle->year ? $vehicle->year.' ' : '').($vehicle->make ?? '').' '.($vehicle->model ?? ''))
                : 'Vehicle';

            foreach ($recognition->lines as $line) {
                $hours = (float) $line->flag_hours;
                $recognizedHours += $hours;
                $lineEarnings = $flagRateCents !== null ? (int) round($hours * $flagRateCents) : null;
                if ($lineEarnings !== null) {
                    $recognizedEarningsCents += $lineEarnings;
                }

                $recognizedLines[] = [
                    'recognition_id' => $recognition->id,
                    'recognized_at' => $recognition->recognized_at?->toDateTimeString(),
                    'recognized_date' => $recognition->recognized_at?->toDateString(),
                    'repair_order_id' => $recognition->repair_order_id,
                    'vehicle' => $vehicleLabel,
                    'concern' => $recognition->concern?->summary,
                    'labor_description' => $line->description,
                    'repair_order_line_id' => $line->repair_order_line_id,
                    'flag_hours' => $hours,
                    'flag_rate_cents' => $flagRateCents,
                    'flag_rate_dollars' => $flagRateCents !== null ? round($flagRateCents / 100, 2) : null,
                    'earnings_cents' => $lineEarnings,
                ];
            }
        }

        $recognizedHours = round($recognizedHours, 2);

        $pendingLines = self::pendingLinesForTechnician((int) $technician->id);
        $pendingHours = round((float) collect($pendingLines)->sum('flag_hours'), 2);

        $floorRequirementCents = 0;
        foreach ($dailyTime as $day) {
            if ($day['floor_amount_cents'] !== null) {
                $floorRequirementCents += $day['floor_amount_cents'];
            }
        }

        $floorExposureCents = max(0, $floorRequirementCents - $recognizedEarningsCents);
        $baseAssistCents = $recognizedEarningsCents + $floorExposureCents;

        $currentAgreement = TechnicianCompensationAgreement::currentFor((int) $technician->id);
        $pendingValueCents = null;
        if ($currentAgreement?->flag_rate_cents !== null && $pendingHours > 0) {
            $pendingValueCents = (int) round($pendingHours * $currentAgreement->flag_rate_cents);
        }

        $recognizedEfficiency = $clockHours > 0
            ? round(($recognizedHours / $clockHours) * 100, 1)
            : null;
        $productionInView = $clockHours > 0
            ? round((($recognizedHours + $pendingHours) / $clockHours) * 100, 1)
            : null;

        $partialAdoption = $from->lt($adoption);

        return self::packResult($technician, $from, $to, [
            'history_unavailable' => false,
            'history_partial' => $partialAdoption,
            'history_partial_note' => $partialAdoption
                ? 'Recognized flag only includes production on or after '.$adoption->toDateString().' (when recognition authority began). Earlier days are unknown — not zero.'
                : null,
            'clock_hours' => $clockHours,
            'recognized_flag_hours' => $recognizedHours,
            'pending_flag_hours' => $pendingHours,
            'recognized_efficiency_percent' => $recognizedEfficiency,
            'production_in_view_percent' => $productionInView,
            'production_in_view_label' => 'Recognized + pending vs clock',
            'recognized_earnings_cents' => $recognizedEarningsCents,
            'floor_requirement_cents' => $floorRequirementCents,
            'floor_exposure_cents' => $floorExposureCents,
            'base_compensation_assist_cents' => $baseAssistCents,
            'pending_value_cents' => $pendingValueCents,
            'overtime_review_required' => $otWarning['required'],
            'overtime_warning' => $otWarning,
            'daily_time' => $dailyTime,
            'recognized_lines' => $recognizedLines,
            'pending_lines' => $pendingLines,
            'unassigned_pending_lines' => self::unassignedPendingLines(),
            'wip_impact' => self::wipImpact($recognizedHours, $pendingHours, $floorExposureCents),
            'calculation' => [
                'recognized_flag_earnings_cents' => $recognizedEarningsCents,
                'floor_requirement_cents' => $floorRequirementCents,
                'floor_exposure_cents' => $floorExposureCents,
                'base_compensation_assist_cents' => $baseAssistCents,
                'pending_not_in_floor' => true,
            ],
            'policy' => self::policyBlock(),
            'attribution_note' => 'Pending production is attributed via the RO assigned technician (same as Phase 1A recognition). Unassigned approved labor is listed separately and is not counted against a technician.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function packResult(User $technician, Carbon $from, Carbon $to, array $payload): array
    {
        $summary = [
            'technician_id' => $technician->id,
            'technician_name' => $technician->name,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'clock_hours' => $payload['clock_hours'],
            'recognized_flag_hours' => $payload['recognized_flag_hours'],
            'pending_flag_hours' => $payload['pending_flag_hours'],
            'recognized_efficiency_percent' => $payload['recognized_efficiency_percent'],
            'production_in_view_percent' => $payload['production_in_view_percent'],
            'floor_exposure_cents' => $payload['floor_exposure_cents'],
            'base_compensation_assist_cents' => $payload['base_compensation_assist_cents'],
            'overtime_review_required' => $payload['overtime_review_required'],
            'history_unavailable' => $payload['history_unavailable'],
            'history_partial' => $payload['history_partial'] ?? false,
        ];

        return [
            'applies' => true,
            'summary' => $summary,
            'detail' => array_merge($summary, $payload, [
                'technician_id' => $technician->id,
                'technician_name' => $technician->name,
            ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function pendingLinesForTechnician(int $technicianId): array
    {
        $lines = RepairOrderLine::query()
            ->with(['concern', 'repairOrder.vehicle'])
            ->whereHas('repairOrder', fn ($q) => $q->where('assigned_technician_id', $technicianId))
            ->whereHas('concern', fn ($q) => $q->where('disposition', RepairOrderConcernDisposition::Approved->value))
            ->where('type', RepairOrderLineType::Labor->value)
            ->where('quantity', '>', 0)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('technician_flag_recognition_lines')
                    ->whereColumn('technician_flag_recognition_lines.repair_order_line_id', 'repair_order_lines.id');
            })
            ->orderBy('repair_order_id')
            ->get();

        return $lines->map(fn (RepairOrderLine $line): array => self::mapPendingLine($line))->all();
    }

    /**
     * Approved unrecognized labor with no RO technician — visible, not attributed.
     *
     * @return list<array<string, mixed>>
     */
    private static function unassignedPendingLines(): array
    {
        $lines = RepairOrderLine::query()
            ->with(['concern', 'repairOrder.vehicle'])
            ->whereHas('repairOrder', fn ($q) => $q->whereNull('assigned_technician_id'))
            ->whereHas('concern', fn ($q) => $q->where('disposition', RepairOrderConcernDisposition::Approved->value))
            ->where('type', RepairOrderLineType::Labor->value)
            ->where('quantity', '>', 0)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('technician_flag_recognition_lines')
                    ->whereColumn('technician_flag_recognition_lines.repair_order_line_id', 'repair_order_lines.id');
            })
            ->orderBy('repair_order_id')
            ->limit(50)
            ->get();

        return $lines->map(fn (RepairOrderLine $line): array => self::mapPendingLine($line, unassigned: true))->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function mapPendingLine(RepairOrderLine $line, bool $unassigned = false): array
    {
        $vehicle = $line->repairOrder?->vehicle;
        $vehicleLabel = $vehicle
            ? trim(($vehicle->year ? $vehicle->year.' ' : '').($vehicle->make ?? '').' '.($vehicle->model ?? ''))
            : 'Vehicle';

        $status = $line->concern?->productionStatus();

        return [
            'repair_order_id' => $line->repair_order_id,
            'repair_order_line_id' => $line->id,
            'vehicle' => $vehicleLabel,
            'concern' => $line->concern?->summary,
            'labor_description' => $line->description,
            'flag_hours' => round((float) $line->quantity, 2),
            'production_status' => $status?->value,
            'production_status_label' => $status?->label(),
            'attribution_source' => $unassigned
                ? 'unassigned'
                : FlagRecognitionPolicy::TECHNICIAN_ATTRIBUTION_RO_ASSIGNEE,
            'unassigned' => $unassigned,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $dailyTime
     * @return array{required: bool, reasons: list<string>, message: string}
     */
    private static function overtimeWarning(array $dailyTime, float $clockHours): array
    {
        $reasons = [];
        $weekly = self::weeklyOtThreshold();
        $daily = self::dailyOtThreshold();

        if ($clockHours > $weekly) {
            $reasons[] = 'Weekly compensable hours ('.$clockHours.') exceed '.$weekly.'.';
        }

        foreach ($dailyTime as $day) {
            if ($day['compensable_hours'] !== null && (float) $day['compensable_hours'] >= $daily) {
                $reasons[] = $day['date'].' has '.(float) $day['compensable_hours'].' compensable hours (daily review threshold '.$daily.').';
            }
        }

        return [
            'required' => $reasons !== [],
            'reasons' => $reasons,
            'message' => 'Overtime review required. This estimate does not include overtime adjustments.',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function wipImpact(float $recognizedHours, float $pendingHours, int $floorExposureCents): ?array
    {
        if ($pendingHours <= 0) {
            return null;
        }

        return [
            'pending_flag_hours' => $pendingHours,
            'message' => number_format($pendingHours, 1).' flag hours are still pending. They are not included in this period\'s recognized flag or floor calculation.',
            'floor_exposure_present' => $floorExposureCents > 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function policyBlock(): array
    {
        return [
            'recognition_policy' => FlagRecognitionPolicy::KEY,
            'recognition_policy_version' => FlagRecognitionPolicy::VERSION,
            'recognition_policy_label' => FlagRecognitionPolicy::label(),
            'explanation' => 'Recognized flag is immutable production recorded when an approved concern reaches Completed. Pending flag is approved labor still not recognized — sublets never count as flag hours. Actor who marks Completed is not the earning technician — RO assigned technician is snapshotted at recognition.',
            'recognition_authority_starts_at' => self::recognitionAuthorityStartsAt()->toDateString(),
        ];
    }
}
