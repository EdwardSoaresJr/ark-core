<?php

namespace App\Ark\Operations\Vehicles;

use App\Ark\Operations\Labor\LaborEngineMatchSource;
use App\Ark\Operations\LaborGuides\Rte\RteLaborVehicleEngineProfile;

/**
 * Read-only vehicle match visibility for labor, parts, and future estimate surfaces.
 *
 * Observation only — does not change matching, doctrine, or authority.
 */
final class VehicleMatchProjection
{
    /** @var list<string> */
    private const FIELD_ORDER = [
        'year',
        'make',
        'model',
        'drive_type',
        'engine',
        'vin',
    ];

    /**
     * @return array{
     *     confidence: string,
     *     confidence_label: string,
     *     matched: list<string>,
     *     missing: list<string>,
     *     vehicle_label: string|null,
     *     application_label: string|null,
     *     explanation: list<string>,
     *     engine_selection_required: bool,
     *     engine_assumption: array{label: string, source: string}|null,
     *     engine_source: string,
     * }
     */
    public function build(
        ?Vehicle $vehicle,
        RteLaborVehicleEngineProfile $engineProfile,
        ?string $applicationLabel,
        bool $engineSelectionRequired,
    ): array {
        $vinDecoded = $vehicle?->hasDecodedIdentity() ?? false;
        $engineKnown = $this->engineKnownForMatching($engineProfile, $engineSelectionRequired);
        $confidence = $this->confidenceLevel($vinDecoded, $engineKnown);

        $matched = [];
        $missing = [];

        foreach (self::FIELD_ORDER as $field) {
            if ($this->fieldMatched($field, $vehicle, $engineKnown, $vinDecoded)) {
                $matched[] = $field;

                continue;
            }

            $missing[] = $field;
        }

        return [
            'confidence' => $confidence->value,
            'confidence_label' => $confidence->label(),
            'matched' => $matched,
            'missing' => $missing,
            'vehicle_label' => $this->vehicleLabel($vehicle),
            'application_label' => $applicationLabel,
            'explanation' => $this->explanation($confidence, $vinDecoded, $engineKnown, $vehicle),
            'engine_selection_required' => $engineSelectionRequired,
            'engine_assumption' => $this->engineAssumption($vehicle, $engineProfile, $engineKnown),
            'engine_source' => $engineProfile->matchSource()->value,
        ];
    }

    private function confidenceLevel(bool $vinDecoded, bool $engineKnown): VehicleMatchConfidenceLevel
    {
        if ($vinDecoded && $engineKnown) {
            return VehicleMatchConfidenceLevel::High;
        }

        if ($engineKnown) {
            return VehicleMatchConfidenceLevel::Medium;
        }

        return VehicleMatchConfidenceLevel::Low;
    }

    private function engineKnownForMatching(
        RteLaborVehicleEngineProfile $engineProfile,
        bool $engineSelectionRequired,
    ): bool {
        if ($engineSelectionRequired) {
            return false;
        }

        return match ($engineProfile->matchSource()) {
            LaborEngineMatchSource::AdvisorSelected,
            LaborEngineMatchSource::VehicleRecord => true,
            LaborEngineMatchSource::AssumedDefault => count($engineProfile->engineOptions()) <= 1,
        };
    }

    private function fieldMatched(
        string $field,
        ?Vehicle $vehicle,
        bool $engineKnown,
        bool $vinDecoded,
    ): bool {
        return match ($field) {
            'year' => $vehicle?->year !== null,
            'make' => filled($vehicle?->make),
            'model' => filled($vehicle?->model),
            'drive_type' => $vehicle?->hasDriveOnRecord() ?? false,
            'engine' => $engineKnown,
            'vin' => $vinDecoded,
            default => false,
        };
    }

    /**
     * @return list<string>
     */
    private function explanation(
        VehicleMatchConfidenceLevel $confidence,
        bool $vinDecoded,
        bool $engineKnown,
        ?Vehicle $vehicle,
    ): array {
        $lines = [];

        if ($vinDecoded) {
            $lines[] = 'VIN decoded';
        } elseif ($vehicle?->hasVin() ?? false) {
            $lines[] = 'VIN present but not decoded';
        } else {
            $lines[] = 'VIN unavailable';
        }

        if ($engineKnown) {
            $lines[] = 'Engine known';
        } else {
            $lines[] = 'Engine unknown';
        }

        if ($confidence === VehicleMatchConfidenceLevel::Low) {
            $lines[] = 'Using broad application group';
        }

        return $lines;
    }

    /**
     * @return array{label: string, source: string}|null
     */
    private function engineAssumption(
        ?Vehicle $vehicle,
        RteLaborVehicleEngineProfile $engineProfile,
        bool $engineKnown,
    ): ?array {
        if ($engineKnown
            || $engineProfile->matchSource() !== LaborEngineMatchSource::AssumedDefault
            || ! filled($engineProfile->primaryEngineLabel())) {
            return null;
        }

        return [
            'label' => $engineProfile->primaryEngineLabel(),
            'source' => $vehicle === null || ! $vehicle->hasEngineOnRecord()
                ? 'Vehicle record incomplete'
                : 'Could not match vehicle engine to labor guide',
        ];
    }

    private function vehicleLabel(?Vehicle $vehicle): ?string
    {
        if ($vehicle === null) {
            return null;
        }

        $label = trim(implode(' ', array_filter([
            $vehicle->year,
            $vehicle->make,
            $vehicle->model,
        ])));

        return $label !== '' ? $label : null;
    }
}
