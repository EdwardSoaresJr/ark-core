<?php

namespace App\Ark\Dragon\ServiceAdvisor\Http;

use App\Ark\Dragon\Assist\DragonAssistProjection;
use App\Ark\Dragon\ServiceAdvisor\RequestServiceAdvisorRewriteAction;
use App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorField;
use App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorMode;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class RequestServiceAdvisorRewriteController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        RequestServiceAdvisorRewriteAction $action,
    ): JsonResponse {
        abort_unless($concern->repair_order_id === $repairOrder->id, 404);
        $repairOrder->ensureOpenForEditing();

        $data = $request->validate([
            'field' => ['required', 'string', Rule::in(ServiceAdvisorField::concernNarrativeValues())],
            'mode' => ['nullable', 'string', Rule::in(ServiceAdvisorMode::values())],
        ]);

        $field = ServiceAdvisorField::from($data['field']);
        $mode = ServiceAdvisorMode::from($data['mode'] ?? ServiceAdvisorMode::ServiceAdvisorRewrite->value);

        try {
            $assist = $action->execute(
                $repairOrder,
                $concern,
                $field,
                $mode,
                $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $assist->load('result');

        return response()->json([
            'assist' => DragonAssistProjection::fromRequest($assist),
            'provenance' => 'Dragon Service Advisor · Based on current RO/estimate context',
        ], 201);
    }
}
