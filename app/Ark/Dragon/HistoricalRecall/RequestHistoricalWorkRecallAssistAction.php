<?php

namespace App\Ark\Dragon\HistoricalRecall;

use App\Ark\Dragon\Assist\DragonAssistRequest;
use App\Ark\Dragon\Assist\DragonAssistTaskType;
use App\Ark\Dragon\Assist\RequestDragonAssistAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\WorkTemplates\HistoricalWorkRecallProjection;
use App\Ark\Operations\WorkTemplates\WorkTemplate;
use App\Models\User;

/**
 * Builds a privacy-minimized assist payload from deterministic Historical Work Recall.
 * Does not change Exact/Likely/Possible — Dragon only reviews.
 */
final class RequestHistoricalWorkRecallAssistAction
{
    public function __construct(
        private readonly RequestDragonAssistAction $requestAssist = new RequestDragonAssistAction,
    ) {}

    public function execute(
        RepairOrder $repairOrder,
        WorkTemplate $template,
        HistoricalWorkRecallProjection $recall,
        ?User $actor = null,
    ): ?DragonAssistRequest {
        if ($recall->sampleCount <= 0) {
            return null;
        }

        $vehicle = $repairOrder->vehicle;
        $payload = [
            'repair_order_id' => (int) $repairOrder->id,
            'vehicle' => [
                'year' => $vehicle?->year,
                'make' => $vehicle?->make,
                'model' => $vehicle?->model,
                'engine' => $vehicle?->engine_display ?: $vehicle?->engine,
                'drivetrain' => $vehicle?->drivetrain ?: $vehicle?->drive,
            ],
            'saved_work' => [
                'template_id' => (int) $template->id,
                'title' => $template->title,
            ],
            'deterministic_recall' => [
                'tier' => $recall->tier->value,
                'median_hours' => $recall->medianHours,
                'min_hours' => $recall->minHours,
                'max_hours' => $recall->maxHours,
                'sample_count' => $recall->sampleCount,
                'historical_work_group_ids' => array_values(array_map(
                    static fn (array $sample): int => (int) $sample['work_group_id'],
                    $recall->samples,
                )),
                'historical_repair_order_ids' => array_values(array_map(
                    static fn (array $sample): int => (int) $sample['repair_order_id'],
                    $recall->samples,
                )),
                'reasons' => $recall->reasons,
            ],
        ];

        return $this->requestAssist->execute(
            DragonAssistTaskType::HistoricalWorkRecallReview,
            $payload,
            repairOrderId: (int) $repairOrder->id,
            vehicleId: $vehicle?->id,
            actor: $actor,
        );
    }
}
