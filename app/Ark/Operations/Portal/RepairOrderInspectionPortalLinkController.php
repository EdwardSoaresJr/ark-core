<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Inspections\InspectionFindingCardProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;

class RepairOrderInspectionPortalLinkController
{
    public function __invoke(
        RepairOrder $repairOrder,
        CreateOrReuseInspectionAccessTokenAction $tokens,
    ): JsonResponse {
        $repairOrder->loadMissing('customer');

        abort_unless($repairOrder->customer !== null, 422, 'Repair order does not have a customer.');
        abort_unless(
            InspectionFindingCardProjection::recordedCountForRepairOrder($repairOrder) > 0,
            422,
            'Record at least one inspection finding before sharing.',
        );

        $accessToken = $tokens->execute($repairOrder, request()->user());
        $url = route('portal.inspections.show', ['token' => $accessToken->plainToken]);

        return response()->json([
            'url' => $url,
            'token_reused' => $accessToken->reused,
        ]);
    }
}
