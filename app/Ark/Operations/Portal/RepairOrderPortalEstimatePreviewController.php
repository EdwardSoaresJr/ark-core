<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Contracts\View\View;

class RepairOrderPortalEstimatePreviewController
{
    public function __invoke(
        RepairOrder $repairOrder,
        CreateOrReuseEstimateAccessTokenAction $tokens,
        PortalEstimatePage $page,
    ): View {
        abort_unless($repairOrder->lines()->exists(), 404);

        $accessToken = $tokens->execute($repairOrder, request()->user(), forStaffPreview: true);

        return $page->render(
            accessToken: $accessToken->token,
            plainToken: $accessToken->plainToken,
            recordCustomerView: false,
            staffPreview: true,
        );
    }
}
