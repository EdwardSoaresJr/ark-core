<?php

namespace App\Ark\Dragon\ServiceAdvisor\Http;

use App\Ark\Dragon\Assist\DragonAssistProjection;
use App\Ark\Dragon\ServiceAdvisor\RequestLineNoteRewriteAction;
use App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorMode;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class RequestLineNoteRewriteController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderLine $line,
        RequestLineNoteRewriteAction $action,
    ): JsonResponse {
        abort_unless((int) $line->repair_order_id === (int) $repairOrder->id, 404);
        $repairOrder->ensureOpenForEditing();

        $data = $request->validate([
            'mode' => ['nullable', 'string', Rule::in(ServiceAdvisorMode::values())],
        ]);

        $mode = ServiceAdvisorMode::from($data['mode'] ?? ServiceAdvisorMode::ServiceAdvisorRewrite->value);

        try {
            $assist = $action->execute($repairOrder, $line, $mode, $request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $assist->load('result');

        return response()->json([
            'assist' => DragonAssistProjection::fromRequest($assist),
            'provenance' => 'Dragon Service Advisor · Line note',
        ], 201);
    }
}
