<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Derives scope production posture from part procurement truth on approved scopes.
 */
final class ScopeProductionStatusFromPartsSync
{
    public function __construct(
        private readonly OperationalEventRecorder $events,
    ) {}

    public function sync(RepairOrderConcern $concern, ?User $actor = null): bool
    {
        if (! $concern->tracksProduction()) {
            return false;
        }

        $concern->loadMissing('lines', 'repairOrder');
        $target = $this->targetStatus($concern);

        if ($target === null) {
            return false;
        }

        $current = $concern->productionStatus();

        if (! $this->shouldApply($current, $target)) {
            return false;
        }

        if ($current === $target) {
            return false;
        }

        $prior = $current->value;

        $concern->update([
            'production_status' => $target,
        ]);

        $this->events->record(
            OperationalEventName::ConcernProductionStatusChanged,
            $concern->repairOrder,
            actor: $actor,
            payload: [
                'concern_id' => $concern->id,
                'prior_production_status' => $prior,
                'new_production_status' => $target->value,
                'automated_from' => 'parts_procurement',
            ],
        );

        return true;
    }

    public function targetStatus(RepairOrderConcern $concern): ?ScopeProductionStatus
    {
        if (! $concern->tracksProduction()) {
            return null;
        }

        $partLines = $this->partLines($concern);

        if ($partLines->isEmpty()) {
            return null;
        }

        if ($partLines->contains(fn (RepairOrderLine $line): bool => $line->hasUnresolvedProcurement())) {
            return ScopeProductionStatus::WaitingParts;
        }

        return ScopeProductionStatus::Pending;
    }

    private function shouldApply(ScopeProductionStatus $current, ScopeProductionStatus $target): bool
    {
        if ($current === ScopeProductionStatus::Completed) {
            return false;
        }

        if ($target === ScopeProductionStatus::Pending) {
            return $current === ScopeProductionStatus::WaitingParts;
        }

        return true;
    }

    /**
     * @return Collection<int, RepairOrderLine>
     */
    private function partLines(RepairOrderConcern $concern): Collection
    {
        return $concern->lines
            ->filter(fn (RepairOrderLine $line): bool => $line->isPart())
            ->values();
    }
}
