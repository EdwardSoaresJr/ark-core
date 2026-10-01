<?php

namespace App\Ark\Dragon\ServiceAdvisor;

use App\Ark\Dragon\Assist\DragonAssistRequest;
use App\Ark\Dragon\Assist\DragonAssistTaskType;
use App\Ark\Dragon\Assist\RequestDragonAssistAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Models\User;

final class RequestServiceAdvisorRewriteAction
{
    public function __construct(
        private readonly ServiceAdvisorContextBuilder $contextBuilder = new ServiceAdvisorContextBuilder,
        private readonly RequestDragonAssistAction $requestAssist = new RequestDragonAssistAction,
    ) {}

    public function execute(
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        ServiceAdvisorField $field,
        ServiceAdvisorMode $mode,
        ?User $actor = null,
    ): DragonAssistRequest {
        $payload = $this->contextBuilder->build($repairOrder, $concern, $field, $mode);

        return $this->requestAssist->execute(
            DragonAssistTaskType::ServiceAdvisorRewrite,
            $payload,
            repairOrderId: (int) $repairOrder->id,
            vehicleId: $repairOrder->vehicle_id,
            actor: $actor,
        );
    }
}
