<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\ShopMemory\Projections\ConcernSuggestionProjection;
use App\Ark\ShopMemory\ShopMemoryFeatures;
use App\Ark\ShopMemory\ShopMemoryProviderCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Problem-language Shop Memory suggest. Empty when Historical Concern disabled.
 */
final class RepairOrderConcernMemorySuggestController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        ConcernSuggestionProjection $projection,
    ): JsonResponse {
        abort_unless($request->user()?->can(ArkCapability::RepairOrdersManage->value), 403);

        if (! ShopMemoryFeatures::providerEnabled(ShopMemoryProviderCatalog::HISTORICAL_CONCERN)
            && ! ShopMemoryFeatures::providerEnabled(ShopMemoryProviderCatalog::TECHNICIAN_OBSERVATION)
            && ! ShopMemoryFeatures::providerEnabled(ShopMemoryProviderCatalog::INSPECTION_FINDING)
            && ! ShopMemoryFeatures::providerEnabled(ShopMemoryProviderCatalog::CUSTOMER_INTAKE)) {
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
