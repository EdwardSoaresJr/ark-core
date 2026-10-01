<?php

namespace App\Ark\Operations\Approvals;

use App\Ark\Operations\RepairOrders\RecommendationIntent;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use Illuminate\Support\Collection;

final class ResolveStaffAuthorizationType
{
    /**
     * @param  array<int, string>  $concernDispositions  concern_id => disposition value
     */
    public function assumingDispositions(RepairOrder $repairOrder, array $concernDispositions): ApprovalType
    {
        $repairOrder->loadMissing('concerns');

        $concerns = $repairOrder->concerns->map(function (RepairOrderConcern $concern) use ($concernDispositions): RepairOrderConcern {
            if (! array_key_exists($concern->id, $concernDispositions)) {
                return $concern;
            }

            $next = RepairOrderConcernDisposition::tryFrom((string) $concernDispositions[$concern->id]);

            if (! $next instanceof RepairOrderConcernDisposition || $concern->disposition === $next) {
                return $concern;
            }

            $copy = clone $concern;
            $copy->disposition = $next;

            return $copy;
        });

        return $this->fromConcerns($concerns);
    }

    public function fromRepairOrder(RepairOrder $repairOrder): ApprovalType
    {
        $repairOrder->loadMissing('concerns');

        return $this->fromConcerns($repairOrder->concerns);
    }

    /**
     * @param  Collection<int, RepairOrderConcern>  $concerns
     */
    public function fromConcerns(Collection $concerns): ApprovalType
    {
        /** @var Collection<int, RepairOrderConcern> $scopes */
        $scopes = $concerns
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
