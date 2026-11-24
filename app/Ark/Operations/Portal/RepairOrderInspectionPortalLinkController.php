<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Inspections\GuardInspectionCustomerPublication;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;

class RepairOrderInspectionPortalLinkController
{
    public function __invoke(
        RepairOrder $repairOrder,
        CreateOrReuseInspectionAccessTokenAction $tokens,
        GuardInspectionCustomerPublication $publication,
    ): JsonResponse {
        $repairOrder->loadMissing('customer');

        abort_unless($repairOrder->customer !== null, 422, 'Repair order does not have a customer.');
        $publication->abortUnlessReady($repairOrder);

        $accessToken = $tokens->execute($repairOrder, request()->user());
        $url = route('portal.inspections.show', ['token' => $accessToken->plainToken]);

        return response()->json([
            'url' => $url,
            'token_reused' => $accessToken->reused,
        ]);
    }
}
