<?php

namespace App\Ark\Dragon\ServiceAdvisor\Http;

use App\Ark\Dragon\Assist\DragonAssistRequest;
use App\Ark\Dragon\ServiceAdvisor\ApplyLineNoteRewriteAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class ApplyLineNoteRewriteController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderLine $line,
        string $assistRequest,
        ApplyLineNoteRewriteAction $action,
        RepairOrderConcurrency $concurrency,
    ): JsonResponse {
        abort_unless((int) $line->repair_order_id === (int) $repairOrder->id, 404);
        $concurrency->guard($request, $repairOrder);

        $assist = DragonAssistRequest::query()
            ->where('public_id', $assistRequest)
            ->where('repair_order_id', $repairOrder->id)
            ->with('result')
            ->firstOrFail();

        $data = $request->validate([
            'edited_proposal' => ['nullable', 'string', 'max:8000'],
            RepairOrderConcurrency::FIELD => ['nullable', 'integer'],
        ]);

        try {
            $application = $action->execute(
                $repairOrder,
                $line,
                $assist,
                $request->user(),
                $data['edited_proposal'] ?? null,
                isset($data[RepairOrderConcurrency::FIELD]) ? (int) $data[RepairOrderConcurrency::FIELD] : null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'application' => [
                'public_id' => $application->public_id,
                'field' => $application->field->value,
                'line_id' => $application->repair_order_line_id,
                'applied_text' => $application->applied_text,
                'applied_at' => $application->applied_at?->toIso8601String(),
                'can_revert' => $application->isApplied(),
            ],
            'status' => 'Applied Dragon rewrite.',
        ]);
    }
}
