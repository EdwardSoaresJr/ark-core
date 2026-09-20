<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\ShopMemory\Projections\LaborSuggestionProjection;
use App\Ark\ShopMemory\ShopMemoryFeatures;
use App\Ark\ShopMemory\ShopMemoryProviderCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shop Memory labor suggestions — work-language corpus only.
 * Does not write RepairOrderLine. Advisor acceptance is authorship.
 */
final class RepairOrderLaborMemorySuggestController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        LaborSuggestionProjection $projection,
    ): JsonResponse {
        abort_unless($request->user()?->can(ArkCapability::RepairOrdersManage->value), 403);

        if (! ShopMemoryFeatures::providerEnabled(ShopMemoryProviderCatalog::HISTORICAL_LABOR)) {
            return response()->json([
                'query' => (string) $request->query('q', ''),
                'suggestions' => [],
                'enabled' => false,
            ]);
        }

        $presented = $projection->present(
            query: (string) $request->query('q', ''),
            limit: 8,
            repairOrderId: $repairOrder->id,
        );

        return response()->json([
            'query' => (string) $request->query('q', ''),
            'enabled' => true,
            'suggestions' => array_map(
                static fn ($suggestion): array => [
                    'id' => $suggestion->id,
                    'text' => $suggestion->display,
                    'provider' => $suggestion->providerKey,
                ],
                $presented,
            ),
        ]);
    }
}
