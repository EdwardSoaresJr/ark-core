<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use Illuminate\Support\Collection;

final class PortalEstimateAuthorization
{
    /**
     * @return Collection<int, RepairOrderConcern>
     */
    public function pendingRecommendedConcerns(RepairOrder $repairOrder): Collection
    {
        $repairOrder->loadMissing('concerns');

        return $repairOrder->concerns
            ->filter(fn (RepairOrderConcern $concern): bool => $concern->disposition === RepairOrderConcernDisposition::Recommended)
            ->sortBy('position')
            ->values();
    }

    public function authorizationMode(RepairOrder $repairOrder): PortalEstimateAuthorizationMode
    {
        if ($repairOrder->isTerminal()) {
            return PortalEstimateAuthorizationMode::None;
        }

        if ($this->pendingRecommendedConcerns($repairOrder)->isNotEmpty()) {
            return PortalEstimateAuthorizationMode::PerConcern;
        }

        return PortalEstimateAuthorizationMode::None;
    }

    public function canAuthorize(RepairOrder $repairOrder): bool
    {
        return $this->authorizationMode($repairOrder) !== PortalEstimateAuthorizationMode::None;
    }

    public function latestRecordedApproval(RepairOrder $repairOrder): ?ApprovalEvent
    {
        $repairOrder->loadMissing('approvalEvents.revocation');

        return $repairOrder->approvalEvents
            ->filter(fn (ApprovalEvent $event): bool => ! $event->isRevoked())
            ->sortByDesc('id')
            ->first();
    }

    public function presentedWorkIsFullyApproved(RepairOrder $repairOrder): bool
    {
        $presented = $this->presentedConcerns($repairOrder);

        if ($presented->isEmpty()) {
            return false;
        }

        return $presented->every(
            fn (RepairOrderConcern $concern): bool => $concern->disposition === RepairOrderConcernDisposition::Approved,
        );
    }

    /**
     * @return Collection<int, RepairOrderConcern>
     */
    public function presentedConcerns(RepairOrder $repairOrder): Collection
    {
        $repairOrder->loadMissing('concerns');

        return $repairOrder->concerns
            ->filter(fn (RepairOrderConcern $concern): bool => $concern->disposition->visibleToCustomer()
                && in_array($concern->disposition, [
                    RepairOrderConcernDisposition::Recommended,
                    RepairOrderConcernDisposition::Approved,
                ], true))
            ->sortBy('position')
            ->values();
    }

}
