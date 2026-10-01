<?php

namespace App\Ark\Station\Http;

use App\Ark\Station\StationGlassRepairOrderProjection;
use Illuminate\Http\JsonResponse;

final class StationRepairOrderController
{
    public function __invoke(string $repairOrder, StationGlassRepairOrderProjection $projection): JsonResponse
    {
        $card = $projection->forShopNumber($repairOrder);
        if ($card === null) {
            return response()->json(['message' => 'Repair order not found.'], 404);
        }

        return response()->json(['repair_order' => $card]);
    }
}
