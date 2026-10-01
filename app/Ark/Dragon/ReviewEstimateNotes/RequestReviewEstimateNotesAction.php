<?php

namespace App\Ark\Dragon\ReviewEstimateNotes;

use App\Ark\Dragon\Assist\DragonAssistRequest;
use App\Ark\Dragon\Assist\DragonAssistTaskType;
use App\Ark\Dragon\Assist\RequestDragonAssistAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Models\User;

final class RequestReviewEstimateNotesAction
{
    public function __construct(
        private readonly ReviewEstimateNotesContextBuilder $contextBuilder = new ReviewEstimateNotesContextBuilder,
        private readonly RequestDragonAssistAction $requestAssist = new RequestDragonAssistAction,
    ) {}

    public function execute(
        RepairOrder $repairOrder,
        ?User $actor = null,
        ?RepairOrderConcern $scopeConcern = null,
    ): DragonAssistRequest {
        $payload = $this->contextBuilder->build($repairOrder, $scopeConcern);

        return $this->requestAssist->execute(
            DragonAssistTaskType::ReviewEstimateNotes,
            $payload,
            repairOrderId: (int) $repairOrder->id,
            vehicleId: $repairOrder->vehicle_id,
            actor: $actor,
        );
    }
}
