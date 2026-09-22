<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One check for installing a part, completing labor, and moving a repair order
 * into a finished status. Shop, phone, and later clients call this.
 */
final class WorkCompletionAuthorization
{
    public const INSTALL_BLOCKED = 'This part is outside the approved work and has no documented exception.';

    public const COMPLETION_BLOCKED = 'Completed labor on this concern includes work outside the approved scope and has no documented exception.';

    public const LIFECYCLE_BLOCKED = 'Installed parts or completed labor on this repair order are outside the approved scope and have no documented exception.';

    public const CONCERN_DELETE_BLOCKED = 'This concern has an approval or exception on file and cannot be deleted.';

    /** @var list<string> */
    private const FINISHED_STATUSES = [
        'quality_check',
        'completed',
        'invoiced',
        'ready_pickup',
    ];

    public function installBlockedReason(RepairOrderLine $line): ?string
    {
        if (! $line->isPart()) {
            return null;
        }

        return $this->lineCovered($line) ? null : self::INSTALL_BLOCKED;
    }

    public function completionBlockedReason(RepairOrderConcern $concern): ?string
    {
        $concern->loadMissing('lines');

        foreach ($concern->lines as $line) {
            if (! $this->requiresCoverage($line)) {
                continue;
            }

            if (! $this->lineCovered($line)) {
                return self::COMPLETION_BLOCKED;
            }
        }

        return null;
    }

    public function lifecycleBlockedReason(RepairOrder $repairOrder, string $toStatusSlug): ?string
    {
        if (! in_array($toStatusSlug, self::FINISHED_STATUSES, true)) {
            return null;
        }

        $repairOrder->loadMissing(['lines.concern', 'concerns.lines']);

        foreach ($repairOrder->lines as $line) {
            if ($line->isPart()
                && $line->procurementState() === PartProcurementState::Installed
                && ! $this->lineCovered($line)
            ) {
                return self::LIFECYCLE_BLOCKED;
            }
        }

        foreach ($repairOrder->concerns as $concern) {
            if ($concern->productionStatus() !== ScopeProductionStatus::Completed) {
                continue;
            }

            foreach ($concern->lines as $line) {
                if ($this->requiresCoverage($line) && ! $this->lineCovered($line)) {
                    return self::LIFECYCLE_BLOCKED;
                }
            }
        }

        return null;
    }

    public function lineCovered(RepairOrderLine $line): bool
    {
        if (! $this->requiresCoverage($line)) {
            return true;
        }

        $line->loadMissing('concern');
        $concern = $line->concern;

        if (! $concern instanceof RepairOrderConcern) {
            return false;
        }

        if ($this->exceptionCovers($concern, (int) $line->id)) {
            return true;
        }

        return $this->customerApprovalCovers($line);
    }

    public function customerApprovalCovers(RepairOrderLine $line): bool
    {
        $line->loadMissing('concern');
        $concern = $line->concern;

        if (! $concern instanceof RepairOrderConcern) {
            return false;
        }

        $scope = ApprovedWorkScope::query()
            ->where('repair_order_concern_id', $concern->id)
            ->latest('id')
            ->first();

        return $this->lineIsInCustomerApproval(
            $line,
            $concern,
            $scope,
            $scope instanceof ApprovedWorkScope ? null : $this->legacyApprovalAt($concern),
        );
    }

    /**
     * Labor and part lines on an Approved concern that the customer approval does not cover.
     * An exception does not remove a line from this list.
     *
     * @return Collection<int, RepairOrderLine>
     */
    public function linesRequiringAuthorization(RepairOrder $repairOrder): Collection
    {
        $repairOrder->loadMissing(['lines.concern']);
        $covered = $this->customerApprovedLaborAndPartIdSet($repairOrder);

        return $repairOrder->lines
            ->filter(function (RepairOrderLine $line) use ($covered): bool {
                if (! $this->requiresCoverage($line)) {
                    return false;
                }

                if ($line->concern?->disposition !== RepairOrderConcernDisposition::Approved) {
                    return false;
                }

                return ! isset($covered[(int) $line->id]);
            })
            ->values();
    }

    /**
     * @return array<int, true>
     */
    public function customerApprovedLaborAndPartIdSet(RepairOrder $repairOrder): array
    {
        $repairOrder->loadMissing(['lines.concern']);

        $concerns = $repairOrder->lines
            ->map(fn (RepairOrderLine $line): ?RepairOrderConcern => $line->concern)
            ->filter(fn (?RepairOrderConcern $concern): bool => $concern instanceof RepairOrderConcern
                && $concern->disposition === RepairOrderConcernDisposition::Approved)
            ->unique('id')
            ->values();

        if ($concerns->isEmpty()) {
            return [];
        }

        $scopes = ApprovedWorkScope::query()
            ->whereIn('repair_order_concern_id', $concerns->pluck('id')->all())
            ->orderBy('id')
            ->get()
            ->groupBy('repair_order_concern_id')
            ->map(fn (Collection $rows): ApprovedWorkScope => $rows->last());

        $events = $this->legacyApprovalEvents((int) $repairOrder->id);
        $covered = [];

        foreach ($repairOrder->lines as $line) {
            if (! $line->isPart() && ! $line->type->isLabor()) {
                continue;
            }

            $concern = $line->concern;

            if (! $concern instanceof RepairOrderConcern) {
                continue;
            }

            $scope = $scopes->get($concern->id);

            if ($this->lineIsInCustomerApproval(
                $line,
                $concern,
                $scope instanceof ApprovedWorkScope ? $scope : null,
                $scope instanceof ApprovedWorkScope ? null : $this->approvedAtFromEvents($events, (int) $concern->id),
            )) {
                $covered[(int) $line->id] = true;
            }
        }

        return $covered;
    }

    public function concernDeletionBlockedReason(RepairOrderConcern $concern): ?string
    {
        $hasHistory = ApprovedWorkScope::query()
            ->where('repair_order_concern_id', $concern->id)
            ->exists()
            || AuthorizationException::query()
                ->where('repair_order_concern_id', $concern->id)
                ->exists();

        return $hasHistory ? self::CONCERN_DELETE_BLOCKED : null;
    }

    private function requiresCoverage(RepairOrderLine $line): bool
    {
        return $line->isPart() || $line->type->isLabor();
    }

    private function exceptionCovers(RepairOrderConcern $concern, int $lineId): bool
    {
        $exceptions = AuthorizationException::query()
            ->where('repair_order_concern_id', $concern->id)
            ->get(['line_ids']);

        foreach ($exceptions as $exception) {
            if (in_array($lineId, $exception->lineIds(), true)) {
                return true;
            }
        }

        return false;
    }

    private function lineIsInCustomerApproval(
        RepairOrderLine $line,
        RepairOrderConcern $concern,
        ?ApprovedWorkScope $scope,
        ?Carbon $approvedAt,
    ): bool {
        if ($concern->disposition !== RepairOrderConcernDisposition::Approved) {
            return false;
        }

        if ($scope instanceof ApprovedWorkScope) {
            return in_array((int) $line->id, $scope->lineIds(), true);
        }

        if ($approvedAt === null) {
            return true;
        }

        return $line->created_at !== null && $line->created_at->lessThanOrEqualTo($approvedAt);
    }

    private function legacyApprovalAt(RepairOrderConcern $concern): ?Carbon
    {
        return $this->approvedAtFromEvents(
            $this->legacyApprovalEvents((int) $concern->repair_order_id),
            (int) $concern->id,
        );
    }

    /**
     * @return Collection<int, OperationalEvent>
     */
    private function legacyApprovalEvents(int $repairOrderId): Collection
    {
        return OperationalEvent::query()
            ->where('aggregate_type', RepairOrder::class)
            ->where('aggregate_id', $repairOrderId)
            ->where('event_name', OperationalEventName::ConcernDispositionChanged->value)
            ->orderByDesc('id')
            ->get(['occurred_at', 'payload_json']);
    }

    /**
     * @param  Collection<int, OperationalEvent>  $events
     */
    private function approvedAtFromEvents(Collection $events, int $concernId): ?Carbon
    {
        $event = $events->first(function (OperationalEvent $event) use ($concernId): bool {
            $payload = $event->payload_json;

            return (int) ($payload['concern_id'] ?? 0) === $concernId
                && ($payload['new_disposition'] ?? null) === RepairOrderConcernDisposition::Approved->value;
        });

        return $event?->occurred_at;
    }
}
