<?php

namespace App\Ark\Operations\LaborGuides\Rte;

use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\Vehicles\VehicleMatchProjection;
use App\Models\User;

/**
 * Append-only RTE adoption facts — observation only, no learning or doctrine changes.
 */
final class RteLaborObservationRecorder
{
    public function __construct(
        private readonly OperationalEventRecorder $events,
        private readonly RteLaborLookup $lookup,
        private readonly VehicleMatchProjection $vehicleMatch = new VehicleMatchProjection,
        private readonly RteLaborLineProvenance $provenance = new RteLaborLineProvenance,
    ) {}

    /**
     * @param  array{
     *     car_id_code: string,
     *     eng_id_code?: string|null,
     *     hours_basis?: string,
     *     apply_vehicle_age_padding?: bool,
     *     include_add_ons?: bool,
     *     apply_suggested?: bool,
     *     lab_id: string,
     * }  $context
     * @param  array<string, mixed>|null  $primaryLaborRow
     */
    public function recordRecommendationApplied(
        RepairOrder $repairOrder,
        User $user,
        array $context,
        RteLaborApplyResult $result,
        ?array $primaryLaborRow,
        bool $packageApplied,
    ): void {
        $repairOrder->loadMissing('vehicle');

        $basis = RteLaborHoursBasis::tryFrom($context['hours_basis'] ?? '') ?? RteLaborHoursBasis::default();
        $applyAgePadding = filter_var($context['apply_vehicle_age_padding'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $modelYear = $repairOrder->vehicle?->year !== null ? (int) $repairOrder->vehicle->year : null;
        $carIdCode = (string) $context['car_id_code'];
        $engIdCode = filled($context['eng_id_code'] ?? null) ? (string) $context['eng_id_code'] : null;

        $engineProfile = RteLaborVehicleEngineProfile::forVehicle(
            $repairOrder->vehicle,
            $carIdCode,
            $modelYear,
            $engIdCode,
        );

        $applicationLabel = $this->lookup->applicationLabelForCar($carIdCode);
        $engineSelectionRequired = $engineProfile->engineSelectionRequired($repairOrder->vehicle, $engIdCode);
        $vehicleMatch = $this->vehicleMatch->build(
            vehicle: $repairOrder->vehicle,
            engineProfile: $engineProfile,
            applicationLabel: $applicationLabel,
            engineSelectionRequired: $engineSelectionRequired,
        );

        $primaryLine = $result->primaryLine();
        $guideHours = $this->guideHours($primaryLaborRow, $basis);
        $finalHours = (float) ($primaryLine->labor_entered_hours ?? $primaryLine->quantity);
        $relatedCount = max(0, count($result->lines()) - 1);

        $this->events->record(
            OperationalEventName::RteRecommendationApplied,
            $repairOrder,
            actor: $user,
            payload: [
                'repair_order_id' => $repairOrder->id,
                'vehicle_match_confidence' => $vehicleMatch['confidence'],
                'vin_present' => $repairOrder->vehicle?->hasVin() ?? false,
                'engine_known' => in_array('engine', $vehicleMatch['matched'], true),
                'application_label' => $applicationLabel,
                'car_id_code' => $carIdCode,
                'eng_id_code' => $engineProfile->selectedEngIdCode(),
                'labor_row' => $primaryLine->description,
                'lab_id' => (string) $context['lab_id'],
                'guide_hours' => $guideHours,
                'final_hours' => round($finalHours, 2),
                'tier' => $basis->value,
                'age_adjustment_applied' => $applyAgePadding,
                'related_operations_count' => $relatedCount,
                'package_applied' => $packageApplied,
                'optional_diagnostic_lab_ids' => array_values(array_filter(
                    $context['optional_diagnostic_lab_ids'] ?? [],
                    fn (mixed $labId): bool => filled($labId),
                )),
                'optional_diagnostic_count' => count(array_filter(
                    $context['optional_diagnostic_lab_ids'] ?? [],
                    fn (mixed $labId): bool => filled($labId),
                )),
                'line_ids' => collect($result->lines())->pluck('id')->values()->all(),
                'concern_id' => $primaryLine->repair_order_concern_id,
            ],
        );
    }

    public function recordRecommendationOverridden(
        RepairOrder $repairOrder,
        User $user,
        RepairOrderLine $line,
        float $originalHours,
        float $overriddenHours,
    ): void {
        if (! $this->provenance->isRteLaborLine((int) $line->id)) {
            return;
        }

        $applied = $this->provenance->recommendationAppliedPayload($repairOrder, (int) $line->id)
            ?? $this->fallbackAppliedPayload($line);

        $this->events->record(
            OperationalEventName::RteRecommendationOverridden,
            $repairOrder,
            actor: $user,
            payload: [
                'repair_order_id' => $repairOrder->id,
                'line_id' => $line->id,
                'vehicle_match_confidence' => $applied['vehicle_match_confidence'] ?? null,
                'application_label' => $applied['application_label'] ?? null,
                'lab_id' => $applied['lab_id'] ?? $this->provenance->rteLineAddedPayload((int) $line->id)['lab_id'] ?? null,
                'labor_row' => $line->description,
                'original_hours' => round($originalHours, 2),
                'overridden_hours' => round($overriddenHours, 2),
                'delta_hours' => round($overriddenHours - $originalHours, 2),
            ],
        );
    }

    /**
     * @param  array<string, mixed>|null  $row
     */
    private function guideHours(?array $row, RteLaborHoursBasis $basis): ?float
    {
        if ($row === null) {
            return null;
        }

        $bookKey = 'book_'.$basis->value.'_hr';
        $value = $row[$bookKey] ?? $row[$basis->value.'_hr'] ?? null;

        return $value !== null && $value !== '' ? round((float) $value, 2) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackAppliedPayload(RepairOrderLine $line): array
    {
        $added = $this->provenance->rteLineAddedPayload((int) $line->id) ?? [];

        return [
            'vehicle_match_confidence' => null,
            'application_label' => null,
            'lab_id' => $added['lab_id'] ?? null,
        ];
    }
}
