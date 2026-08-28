<?php

namespace App\Ark\ShopMemory\Rewrite;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\ShopMemory\ShopMemoryFeatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class RepairOrderAiRewriteController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        AiRewriteAction $rewrite,
    ): JsonResponse {
        abort_unless($request->user()?->can(ArkCapability::RepairOrdersManage->value), 403);

        if (! ShopMemoryFeatures::aiRewriteEnabled()) {
            return response()->json(['message' => 'AI Rewrite is disabled.'], 404);
        }

        $data = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $rewritten = $rewrite->rewrite($data['text']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'text' => $rewritten,
        ]);
    }
}
