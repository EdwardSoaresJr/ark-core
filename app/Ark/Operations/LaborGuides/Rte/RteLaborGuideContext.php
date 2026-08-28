<?php

namespace App\Ark\Operations\LaborGuides\Rte;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Support\Str;

final class RteLaborGuideContext
{
    public function __construct(
        private readonly RteLaborGuideAvailability $availability,
        private readonly RteVehicleResolver $vehicles,
    ) {}

    /**
     * @return array{
     *     available: bool,
     *     blocked_reason: string|null,
     *     concern_id: int|null,
     *     vehicle_label: string|null,
     *     model_year: int|null,
     *     car_candidates: list<array{car_id_code: string, car_desc: string, lo_yr: string, hi_yr: string}>,
     *     default_car_id_code: string|null,
     * }
     */
    public function forRepairOrder(RepairOrder $repairOrder, ?int $concernId = null): array
    {
        if (! $this->availability->available()) {
            return $this->blocked(RepairTimeEngine::NAME.' data is not imported on this environment.');
        }

        $repairOrder->loadMissing('vehicle');

        $vehicle = $repairOrder->vehicle;
        $year = $vehicle?->year !== null ? (int) $vehicle->year : null;
        $make = filled($vehicle?->make) ? (string) $vehicle->make : null;
        $model = filled($vehicle?->model) ? (string) $vehicle->model : null;

        if ($year === null || ! filled($model)) {
            return $this->blocked('Add vehicle year, make, and model before using '.RepairTimeEngine::NAME.'.');
        }

        $candidates = $this->vehicles->candidates($year, $make, $model)
            ->map(fn ($row): array => [
                'car_id_code' => (string) $row->car_id_code,
                'car_desc' => (string) $row->car_desc,
                'lo_yr' => (string) $row->lo_yr,
                'hi_yr' => (string) $row->hi_yr,
            ])
            ->values()
            ->all();

        if ($candidates === []) {
            return $this->blocked('No '.RepairTimeEngine::NAME.' vehicle match for '.$this->vehicleLabel($year, $make, $model).'.');
        }

        $engineProfile = RteLaborVehicleEngineProfile::forVehicle(
            $vehicle,
            $candidates[0]['car_id_code'],
            $year,
        );

        return [
            'available' => true,
            'blocked_reason' => null,
            'concern_id' => $concernId,
            'vehicle_label' => $this->vehicleLabel($year, $make, $model),
            'vehicle_engine_label' => $engineProfile->primaryEngineLabel(),
            'model_year' => $year,
            'car_candidates' => $candidates,
            'default_car_id_code' => $candidates[0]['car_id_code'],
        ];
    }

    /**
     * @return array{
     *     available: bool,
     *     blocked_reason: string|null,
     *     concern_id: int|null,
     *     vehicle_label: string|null,
     *     model_year: int|null,
     *     car_candidates: list<array{car_id_code: string, car_desc: string, lo_yr: string, hi_yr: string}>,
     *     default_car_id_code: string|null,
     * }
     */
    private function blocked(string $reason): array
    {
        return [
            'available' => false,
            'blocked_reason' => $reason,
            'concern_id' => null,
            'vehicle_label' => null,
            'vehicle_engine_label' => null,
            'model_year' => null,
            'car_candidates' => [],
            'default_car_id_code' => null,
        ];
    }

    private function vehicleLabel(int $year, ?string $make, ?string $model): string
    {
        return trim(implode(' ', array_filter([
            (string) $year,
            $make,
            $model,
        ])));
    }
}
