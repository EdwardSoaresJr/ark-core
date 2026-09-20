<?php

namespace App\Ark\Operations\WorkTemplates;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Vehicles\VehicleText;
use Illuminate\Support\Collection;

/**
 * Read-only Historical Work Recall resolver.
 * Prefer same WorkTemplate provenance → comparable vehicle config → sold labor median.
 */
final class ResolveHistoricalWorkRecall
{
    private const YEAR_NEARBY = 1;

    public function for(RepairOrder $repairOrder, WorkTemplate $template): HistoricalWorkRecallProjection
    {
        $template->loadMissing('lines');
        $templateDefault = $this->templateLaborHours($template);
        $vehicle = $repairOrder->vehicle;

        if ($vehicle === null) {
            return HistoricalWorkRecallProjection::none($templateDefault);
        }

        $candidates = $this->candidateLaborLines($template->id, (int) $repairOrder->id);

        if ($candidates->isEmpty()) {
            return HistoricalWorkRecallProjection::none($templateDefault);
        }

        $classified = $candidates
            ->map(fn (RepairOrderLine $line): array => $this->classifyCandidate($vehicle, $line))
            ->filter(fn (array $row): bool => $row['tier'] !== null);

        if ($classified->isEmpty()) {
            return HistoricalWorkRecallProjection::none($templateDefault);
        }

        $bestTier = [
            HistoricalMatchTier::Exact,
            HistoricalMatchTier::Likely,
            HistoricalMatchTier::Possible,
        ];

        foreach ($bestTier as $tier) {
            $bucket = $classified->filter(fn (array $row): bool => $row['tier'] === $tier);

            if ($bucket->isEmpty()) {
                continue;
            }

            return $this->buildProjection($tier, $bucket, $templateDefault);
        }

        return HistoricalWorkRecallProjection::none($templateDefault);
    }

    /**
     * @return Collection<int, RepairOrderLine>
     */
    private function candidateLaborLines(int $templateId, int $excludeRepairOrderId): Collection
    {
        return RepairOrderLine::query()
            ->select('repair_order_lines.*')
            ->join('repair_order_work_groups', 'repair_order_work_groups.id', '=', 'repair_order_lines.repair_order_work_group_id')
            ->join('repair_order_concerns', 'repair_order_concerns.id', '=', 'repair_order_lines.repair_order_concern_id')
            ->join('repair_orders', 'repair_orders.id', '=', 'repair_order_lines.repair_order_id')
            ->join('vehicles', 'vehicles.id', '=', 'repair_orders.vehicle_id')
            ->where('repair_order_work_groups.created_from_template_id', $templateId)
            ->where('repair_order_lines.type', RepairOrderLineType::Labor->value)
            ->where('repair_order_lines.quantity', '>', 0)
            ->where('repair_orders.id', '!=', $excludeRepairOrderId)
            ->whereNotNull('repair_orders.posted_at')
            ->where(function ($query): void {
                $query->whereNull('repair_orders.close_variant_key')
                    ->orWhere('repair_orders.close_variant_key', '!=', 'lost');
            })
            ->where('repair_order_concerns.disposition', RepairOrderConcernDisposition::Approved->value)
            ->with(['repairOrder.vehicle'])
            ->orderByDesc('repair_orders.posted_at')
            ->limit(100)
            ->get();
    }

    /**
     * @return array{tier: HistoricalMatchTier|null, hours: float, line: RepairOrderLine, reasons: list<string>, vehicle_summary: string}
     */
    private function classifyCandidate(Vehicle $current, RepairOrderLine $line): array
    {
        $historical = $line->repairOrder?->vehicle;
        $hours = (float) $line->quantity;

        if ($historical === null || $hours <= 0) {
            return ['tier' => null, 'hours' => 0.0, 'line' => $line, 'reasons' => [], 'vehicle_summary' => ''];
        }

        $reasons = [];
        $summary = $this->vehicleSummary($historical);

        if (! $this->sameMakeModel($current, $historical)) {
            return ['tier' => null, 'hours' => $hours, 'line' => $line, 'reasons' => [], 'vehicle_summary' => $summary];
        }

        $yearDelta = $this->yearDelta($current, $historical);
        $engineState = $this->engineComparison($current, $historical);
        $driveState = $this->drivetrainComparison($current, $historical);

        // Configuration differences that can alter procedure → Possible only.
        if ($engineState === 'different') {
            $reasons[] = 'Engine differs from historical repair.';

            return [
                'tier' => HistoricalMatchTier::Possible,
                'hours' => $hours,
                'line' => $line,
                'reasons' => $reasons,
                'vehicle_summary' => $summary,
            ];
        }

        if ($driveState === 'different') {
            $reasons[] = 'Drivetrain differs from historical repair ('.$this->drivetrainLabel($historical).' vs '.$this->drivetrainLabel($current).').';

            return [
                'tier' => HistoricalMatchTier::Possible,
                'hours' => $hours,
                'line' => $line,
                'reasons' => $reasons,
                'vehicle_summary' => $summary,
            ];
        }

        if ($yearDelta === null || $yearDelta > self::YEAR_NEARBY + 1) {
            $reasons[] = 'Model year / generation cannot be proven comparable.';

            return [
                'tier' => HistoricalMatchTier::Possible,
                'hours' => $hours,
                'line' => $line,
                'reasons' => $reasons,
                'vehicle_summary' => $summary,
            ];
        }

        $unknowns = [];
        if ($engineState === 'unknown') {
            $unknowns[] = 'engine';
            $reasons[] = 'Engine unknown on current or historical vehicle.';
        }
        if ($driveState === 'unknown') {
            $unknowns[] = 'drivetrain';
            $reasons[] = 'Drivetrain unknown on current or historical vehicle.';
        }
        if ($yearDelta === 1) {
            $reasons[] = 'Nearby model year (±1) — generation equivalence not proven.';
        }

        $exactCapable = $yearDelta === 0
            && $engineState === 'same'
            && $driveState === 'same';

        if ($exactCapable && $unknowns === []) {
            return [
                'tier' => HistoricalMatchTier::Exact,
                'hours' => $hours,
                'line' => $line,
                'reasons' => ['Same make, model, year, engine, and drivetrain.'],
                'vehicle_summary' => $summary,
            ];
        }

        // Nearby year with known matching engine+drive, or unknowns → Likely.
        if (($yearDelta <= self::YEAR_NEARBY) && ($engineState === 'same' || $engineState === 'unknown') && ($driveState === 'same' || $driveState === 'unknown')) {
            if ($reasons === []) {
                $reasons[] = 'Strong similarity with incomplete configuration.';
            }

            return [
                'tier' => HistoricalMatchTier::Likely,
                'hours' => $hours,
                'line' => $line,
                'reasons' => $reasons,
                'vehicle_summary' => $summary,
            ];
        }

        $reasons[] = 'Comparable model-level history only.';

        return [
            'tier' => HistoricalMatchTier::Possible,
            'hours' => $hours,
            'line' => $line,
            'reasons' => $reasons,
            'vehicle_summary' => $summary,
        ];
    }

    /**
     * @param  Collection<int, array{tier: HistoricalMatchTier, hours: float, line: RepairOrderLine, reasons: list<string>, vehicle_summary: string}>  $bucket
     */
    private function buildProjection(
        HistoricalMatchTier $tier,
        Collection $bucket,
        ?float $templateDefault,
    ): HistoricalWorkRecallProjection {
        $hours = $bucket->map(fn (array $row): float => $row['hours'])->sort()->values();
        $median = $this->median($hours->all());
        $min = $hours->min();
        $max = $hours->max();

        $samples = $bucket->map(function (array $row): array {
            $line = $row['line'];
            $posted = $line->repairOrder?->posted_at;

            return [
                'hours' => $row['hours'],
                'repair_order_id' => (int) $line->repair_order_id,
                'work_group_id' => (int) $line->repair_order_work_group_id,
                'vehicle_summary' => $row['vehicle_summary'],
                'posted_at' => $posted?->toDateString(),
            ];
        })->values()->all();

        $reasons = $bucket
            ->flatMap(fn (array $row): array => $row['reasons'])
            ->unique()
            ->values()
            ->all();

        $mostRecent = $bucket
            ->map(fn (array $row) => $row['line']->repairOrder?->posted_at)
            ->filter()
            ->sortDesc()
            ->first();

        $summary = $bucket->first()['vehicle_summary'] ?? null;

        return new HistoricalWorkRecallProjection(
            tier: $tier,
            sampleCount: $bucket->count(),
            medianHours: $median,
            minHours: is_numeric($min) ? (float) $min : null,
            maxHours: is_numeric($max) ? (float) $max : null,
            mostRecentAt: $mostRecent?->toDateString(),
            comparableVehicleSummary: $summary,
            reasons: $reasons,
            samples: $samples,
            templateDefaultHours: $templateDefault,
            preparesLabor: $tier->mayPrepareLabor(),
        );
    }

    /**
     * @param  list<float>  $sorted
     */
    private function median(array $sorted): ?float
    {
        $n = count($sorted);

        if ($n === 0) {
            return null;
        }

        $mid = intdiv($n, 2);

        if ($n % 2 === 1) {
            return round($sorted[$mid], 2);
        }

        return round(($sorted[$mid - 1] + $sorted[$mid]) / 2, 2);
    }

    private function templateLaborHours(WorkTemplate $template): ?float
    {
        $labor = $template->lines->first(
            fn (WorkTemplateLine $line): bool => $line->type->isLabor()
        );

        if ($labor === null) {
            return null;
        }

        $hours = (float) $labor->quantity;

        return $hours > 0 ? $hours : null;
    }

    private function sameMakeModel(Vehicle $a, Vehicle $b): bool
    {
        $makeA = $this->normText($a->make);
        $makeB = $this->normText($b->make);
        $modelA = $this->normText($a->model);
        $modelB = $this->normText($b->model);

        return $makeA !== null && $makeB !== null && $makeA === $makeB
            && $modelA !== null && $modelB !== null && $modelA === $modelB;
    }

    private function yearDelta(Vehicle $a, Vehicle $b): ?int
    {
        if ($a->year === null || $b->year === null) {
            return null;
        }

        return abs((int) $a->year - (int) $b->year);
    }

    /**
     * @return 'same'|'different'|'unknown'
     */
    private function engineComparison(Vehicle $a, Vehicle $b): string
    {
        $keyA = $this->engineKey($a);
        $keyB = $this->engineKey($b);

        if ($keyA === null || $keyB === null) {
            return 'unknown';
        }

        return $keyA === $keyB ? 'same' : 'different';
    }

    /**
     * @return 'same'|'different'|'unknown'
     */
    private function drivetrainComparison(Vehicle $a, Vehicle $b): string
    {
        return HistoricalDrivetrainKey::compare(
            HistoricalDrivetrainKey::fromVehicle($a),
            HistoricalDrivetrainKey::fromVehicle($b),
        );
    }

    private function engineKey(Vehicle $vehicle): ?string
    {
        if ($vehicle->displacement_liters !== null) {
            $liters = number_format((float) $vehicle->displacement_liters, 1, '.', '');
            $code = $this->normText($vehicle->engine_code);

            return $code !== null ? $liters.'|'.$code : $liters;
        }

        foreach ([$vehicle->engine_display, $vehicle->engine_code, $vehicle->engine] as $candidate) {
            $norm = $this->normText($candidate);
            if ($norm !== null) {
                return $norm;
            }
        }

        return null;
    }

    private function drivetrainLabel(Vehicle $vehicle): string
    {
        return HistoricalDrivetrainKey::label(HistoricalDrivetrainKey::fromVehicle($vehicle));
    }

    private function vehicleSummary(Vehicle $vehicle): string
    {
        $bits = array_filter([
            $vehicle->year,
            $vehicle->make,
            $vehicle->model,
            $vehicle->engine_display ?: $vehicle->engine,
            $this->drivetrainLabel($vehicle) !== 'Unknown' ? $this->drivetrainLabel($vehicle) : null,
        ], fn ($v) => filled($v));

        return implode(' ', $bits);
    }

    private function normText(?string $value): ?string
    {
        $clean = VehicleText::clean($value);

        return $clean !== null ? strtolower($clean) : null;
    }
}
