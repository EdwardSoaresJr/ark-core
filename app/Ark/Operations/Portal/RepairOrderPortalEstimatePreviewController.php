<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Contracts\View\View;

class RepairOrderPortalEstimatePreviewController
{
    public function __invoke(RepairOrder $repairOrder, PortalEstimatePage $page): View
    {
        abort_unless($repairOrder->lines()->exists(), 404);

        // No access token: a preview must not open a customer link, even briefly, or allow approving.
        return $page->render(
            accessToken: null,
            plainToken: null,
            recordCustomerView: false,
            staffPreview: true,
            previewRepairOrder: $repairOrder,
        );
    }
}
