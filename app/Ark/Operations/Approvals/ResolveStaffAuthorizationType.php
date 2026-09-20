<?php

namespace App\Ark\Operations\Approvals;

use App\Ark\Operations\RepairOrders\RecommendationIntent;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use Illuminate\Support\Collection;

final class ResolveStaffAuthorizationType
{
    public function fromRepairOrder(RepairOrder $repairOrder): ApprovalType
    {
        $repairOrder->loadMissing('concerns');

        /** @var Collection<int, RepairOrderConcern> $scopes */
        $scopes = $repairOrder->concerns
            ->filter(fn (RepairOrderConcern $concern): bool => $concern->disposition->showsInScopeHeader())
            ->values();

        if ($scopes->isEmpty()) {
            return ApprovalType::Repair;
        }

        $approved = $scopes->where('disposition', RepairOrderConcernDisposition::Approved);
        $recommended = $scopes->where('disposition', RepairOrderConcernDisposition::Recommended);
        $deferred = $scopes->where('disposition', RepairOrderConcernDisposition::Deferred);
        $declined = $scopes->where('disposition', RepairOrderConcernDisposition::Declined);

        if ($approved->isEmpty()) {
            return ApprovalType::Partial;
        }

        if ($this->approvedWorkIsDiagnosticOnly($approved) && $recommended->isEmpty()) {
            return ApprovalType::Diagnostic;
        }

        if ($recommended->isEmpty() && $deferred->isEmpty() && $declined->isEmpty() && $approved->count() === $scopes->count()) {
            return ApprovalType::Repair;
        }

        return ApprovalType::Partial;
    }

    /**
     * @param  Collection<int, RepairOrderConcern>  $approved
     */
    private function approvedWorkIsDiagnosticOnly(Collection $approved): bool
    {
        return $approved->every(
            fn (RepairOrderConcern $concern): bool => $concern->recommendation_intent === RecommendationIntent::Diagnostic,
        );
    }
}
