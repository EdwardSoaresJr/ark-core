<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;

class RepairOrderEstimatePortalLinkController
{
    public function __invoke(
        RepairOrder $repairOrder,
        CreateOrReuseEstimateAccessTokenAction $tokens,
    ): JsonResponse {
        $repairOrder->loadMissing('customer');

        abort_unless($repairOrder->customer !== null, 422, 'Repair order does not have a customer.');
        abort_unless($repairOrder->lines()->exists(), 422, 'Add estimate lines before sharing a portal link.');
        abort_unless(
            ! $repairOrder->outgoingEstimateIsDraftOnly(),
            422,
            RepairOrder::ESTIMATE_SEND_DRAFT_ONLY_MESSAGE,
        );

        $accessToken = $tokens->execute($repairOrder, request()->user());
        $url = route('portal.estimates.show', ['token' => $accessToken->plainToken]);

        return response()->json([
            'url' => $url,
            'token_reused' => $accessToken->reused,
        ]);
    }
}
