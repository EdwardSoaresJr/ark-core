<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Inspections\InspectionFindingCardProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Contracts\View\View;

class RepairOrderPortalInspectionPreviewController
{
    public function __invoke(
        RepairOrder $repairOrder,
        CreateOrReuseInspectionAccessTokenAction $tokens,
        PortalInspectionPage $page,
    ): View {
        abort_unless(
            InspectionFindingCardProjection::recordedCountForRepairOrder($repairOrder) > 0,
            404,
        );

        $accessToken = $tokens->execute($repairOrder, request()->user(), forStaffPreview: true);

        return $page->render(
            accessToken: $accessToken->token,
            plainToken: $accessToken->plainToken,
            recordCustomerView: false,
            staffPreview: true,
        );
    }
}
