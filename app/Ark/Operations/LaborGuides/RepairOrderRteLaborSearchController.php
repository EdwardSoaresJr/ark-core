<?php

namespace App\Ark\Operations\LaborGuides;

use App\Ark\Operations\LaborGuides\Rte\RepairTimeEngine;
use App\Ark\Operations\LaborGuides\Rte\RteLaborGuideContext;
use App\Ark\Operations\LaborGuides\Rte\RteLaborLookup;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RepairOrderRteLaborSearchController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RteLaborGuideContext $context,
        RteLaborLookup $lookup,
    ): JsonResponse {
        $repairOrder->ensureOpenForEditing();

        $guideContext = $context->forRepairOrder(
            $repairOrder,
            $request->integer('concern_id') ?: null,
        );

        if (! $guideContext['available']) {
            return response()->json([
                'available' => false,
                'message' => $guideContext['blocked_reason'],
            ], 422);
        }

        $validated = $request->validate([
            'car_id_code' => ['nullable', 'string', 'max:7'],
            'eng_id_code' => ['nullable', 'string', 'max:12'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $repairOrder->loadMissing('vehicle');

        $carIdCode = $validated['car_id_code'] ?? $guideContext['default_car_id_code'];

        if (! collect($guideContext['car_candidates'])->contains('car_id_code', $carIdCode)) {
            return response()->json([
                'available' => false,
                'message' => 'Selected '.RepairTimeEngine::NAME.' vehicle code is not valid for this repair order.',
            ], 422);
        }

        $selectedEngIdCode = filled($validated['eng_id_code'] ?? null)
            ? strtoupper(trim((string) $validated['eng_id_code']))
            : null;

        $results = $lookup->searchWithRecommendation(
            carIdCode: $carIdCode,
            modelYear: $guideContext['model_year'],
            term: $validated['q'] ?? null,
            vehicle: $repairOrder->vehicle,
            repairOrder: $repairOrder,
            concernId: $request->integer('concern_id') ?: null,
            selectedEngIdCode: $selectedEngIdCode,
        );

        if ($selectedEngIdCode !== null
            && ! collect($results['engine_options'] ?? [])->contains('eng_id_code', $selectedEngIdCode)) {
            return response()->json([
                'available' => false,
                'message' => 'Selected '.RepairTimeEngine::NAME.' engine is not valid for this vehicle configuration.',
            ], 422);
        }

        return response()->json([
            'available' => true,
            'vehicle_label' => $guideContext['vehicle_label'],
            'vehicle_engine_label' => $results['vehicle_engine_label'] ?? $guideContext['vehicle_engine_label'] ?? null,
            'model_year' => $guideContext['model_year'],
            'vehicle_age_multiplier' => $lookup->vehicleAgeMultiplier($guideContext['model_year']),
            'car_id_code' => $carIdCode,
            'car_candidates' => $guideContext['car_candidates'],
            'eng_id_code' => $results['eng_id_code'] ?? null,
            'engine_options' => $results['engine_options'] ?? [],
            'vehicle_match' => $results['vehicle_match'] ?? null,
            'recommended_job' => $results['recommended_job'],
            'suggested_labor' => $results['suggested_labor'],
            'jobs' => $results['jobs'],
        ]);
    }
}
