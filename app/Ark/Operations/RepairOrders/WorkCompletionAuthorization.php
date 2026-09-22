<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use Illuminate\Support\Carbon;

/**
 * One check for installing a part, completing labor, and moving a repair order
 * into a finished status. Shop, phone, and later clients call this.
 */
final class WorkCompletionAuthorization
{
    public const INSTALL_BLOCKED = 'This part is outside the approved work and has no documented exception.';

    public const COMPLETION_BLOCKED = 'Completed labor on this concern includes work outside the approved scope and has no documented exception.';

    public const LIFECYCLE_BLOCKED = 'Installed parts or completed labor on this repair order are outside the approved scope and have no documented exception.';

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

        if ($concern->disposition !== RepairOrderConcernDisposition::Approved) {
            return false;
        }

        $scope = ApprovedWorkScope::query()
            ->where('repair_order_concern_id', $concern->id)
            ->latest('id')
            ->first();

        if ($scope instanceof ApprovedWorkScope) {
            return in_array((int) $line->id, $scope->lineIds(), true);
        }

        $approvedAt = $this->legacyApprovalAt($concern);

        if ($approvedAt === null) {
            return true;
        }

        return $line->created_at !== null && $line->created_at->lessThanOrEqualTo($approvedAt);
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

    private function legacyApprovalAt(RepairOrderConcern $concern): ?Carbon
    {
        $event = OperationalEvent::query()
            ->where('aggregate_type', RepairOrder::class)
            ->where('aggregate_id', $concern->repair_order_id)
            ->where('event_name', OperationalEventName::ConcernDispositionChanged->value)
            ->orderByDesc('id')
            ->get(['occurred_at', 'payload_json'])
            ->first(function (OperationalEvent $event) use ($concern): bool {
                $payload = $event->payload_json;

                return (int) ($payload['concern_id'] ?? 0) === (int) $concern->id
                    && ($payload['new_disposition'] ?? null) === RepairOrderConcernDisposition::Approved->value;
            });

        return $event?->occurred_at;
    }
}
