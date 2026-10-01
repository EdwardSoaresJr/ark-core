<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Production duration metrics derived from existing lifecycle events only.
 */
final class RepairOrderWorkDurationProjection
{
    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     duration_label: string|null,
     *     started_at: ?Carbon,
     *     ended_at: ?Carbon,
     *     status: 'complete'|'pending',
     * }>
     */
    public function for(RepairOrder $repairOrder): array
    {
        $events = $this->lifecycleEvents($repairOrder);

        $readyForWorkAt = $this->firstTransitionAt($events, RepairOrderStatus::ReadyForWork);
        $inProgressAt = $this->firstTransitionAt($events, RepairOrderStatus::InProgress);
        $readyPickupAt = $this->firstTransitionAt($events, RepairOrderStatus::ReadyPickup);

        return [
            $this->metric(
                key: 'dispatch_delay',
                label: 'Dispatch delay',
                startedAt: $readyForWorkAt,
                endedAt: $inProgressAt,
            ),
            $this->metric(
                key: 'repair_cycle',
                label: 'Repair cycle',
                startedAt: $inProgressAt,
                endedAt: $readyPickupAt,
            ),
        ];
    }

    /**
     * @return Collection<int, OperationalEvent>
     */
    private function lifecycleEvents(RepairOrder $repairOrder): Collection
    {
        return OperationalEvent::query()
            ->where('aggregate_type', RepairOrder::class)
            ->where('aggregate_id', $repairOrder->id)
            ->where('event_name', OperationalEventName::RepairOrderLifecycleChanged->value)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, OperationalEvent>  $events
     */
    private function firstTransitionAt(Collection $events, RepairOrderStatus $toStatus): ?Carbon
    {
        return $events->first(function (OperationalEvent $event) use ($toStatus): bool {
            return ($event->payload_json['to_status'] ?? null) === $toStatus->value;
        })?->occurred_at;
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     duration_label: string|null,
     *     started_at: ?Carbon,
     *     ended_at: ?Carbon,
     *     status: 'complete'|'pending',
     * }
     */
    private function metric(
        string $key,
        string $label,
        ?Carbon $startedAt,
        ?Carbon $endedAt,
    ): array {
        $durationLabel = null;
        $status = 'pending';

        if ($startedAt !== null && $endedAt !== null && $endedAt->greaterThanOrEqualTo($startedAt)) {
            $durationLabel = $this->formatDuration($startedAt, $endedAt);
            $status = 'complete';
        }

        return [
            'key' => $key,
            'label' => $label,
            'duration_label' => $durationLabel,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'status' => $status,
        ];
    }

    private function formatDuration(Carbon $startedAt, Carbon $endedAt): string
    {
        $minutes = max(0, (int) $startedAt->diffInMinutes($endedAt));

        if ($minutes < 60) {
            return $minutes.'m';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return $hours.'h';
        }

        return sprintf('%dh %dm', $hours, $remainingMinutes);
    }
}
