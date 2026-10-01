<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Financial\RefreshLivingInvoiceSnapshotAction;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

trait RecordsRepairOrderEstimateMutation
{
    protected function recordRepairOrderEstimateMutation(RepairOrder $repairOrder, Authenticatable|null $actor = null): void
    {
        $repairOrder = app(RepairOrderEstimateVersion::class)->bump(
            $repairOrder,
            $actor instanceof User ? $actor : null,
        );

        app(EstimateDocumentService::class)->markDirtyForRepairOrder($repairOrder);
        app(RefreshLivingInvoiceSnapshotAction::class)->syncIfEligible(
            $repairOrder,
            $actor instanceof User ? $actor : null,
        );
    }
}
