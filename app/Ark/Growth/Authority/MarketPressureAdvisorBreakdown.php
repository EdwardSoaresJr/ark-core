<?php

namespace App\Ark\Growth\Authority;

use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Advisor-level review request capture for Owner Today.
 */
final class MarketPressureAdvisorBreakdown
{
    /**
     * @return list<array{
     *     user_id: int|null,
     *     name: string,
     *     paid_closes: int,
     *     review_requests: int,
     *     missed: int,
     *     rate: float|null
     * }>
     */
    public function forPeriod(Carbon $from, Carbon $through): array
    {
        $repairOrders = RepairOrder::query()
            ->where('status', RepairOrderStatus::Closed)
            ->where('close_variant_key', 'paid')
            ->where('closed_at', '>=', $from)
            ->where('closed_at', '<=', $through)
            ->get(['id', 'review_request_sent', 'review_request_recorded_by']);

        if ($repairOrders->isEmpty()) {
            return [];
        }

        $closeActors = $this->closeActorsFor($repairOrders->pluck('id')->all());

        /** @var Collection<int, Collection<int, RepairOrder>> $byAdvisor */
        $byAdvisor = $repairOrders->groupBy(function (RepairOrder $repairOrder) use ($closeActors): int {
            $advisorId = $repairOrder->review_request_recorded_by
                ?? $closeActors[$repairOrder->id]
                ?? 0;

            return (int) $advisorId;
        });

        $users = User::query()
            ->whereIn('id', $byAdvisor->keys()->filter(fn (int $id): bool => $id > 0)->all())
            ->get()
            ->keyBy('id');

        return $byAdvisor
            ->map(function (Collection $rows, int $advisorId) use ($users): array {
                $paidCloses = $rows->count();
                $requests = $rows->where('review_request_sent', true)->count();
                $missed = $rows->filter(fn (RepairOrder $repairOrder): bool => $repairOrder->review_request_sent !== true)->count();

                $name = $advisorId > 0
                    ? (string) ($users->get($advisorId)?->name ?? 'Advisor')
                    : 'Unassigned';

                return [
                    'user_id' => $advisorId > 0 ? $advisorId : null,
                    'name' => $name,
                    'paid_closes' => $paidCloses,
                    'review_requests' => $requests,
                    'missed' => $missed,
                    'rate' => $paidCloses > 0 ? $requests / $paidCloses : null,
                ];
            })
            ->sortByDesc('paid_closes')
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $repairOrderIds
     * @return array<int, int>
     */
    private function closeActorsFor(array $repairOrderIds): array
    {
        if ($repairOrderIds === []) {
            return [];
        }

        $actors = [];

        OperationalEvent::query()
            ->where('event_name', OperationalEventName::RepairOrderLifecycleChanged->value)
            ->where('aggregate_type', RepairOrder::class)
            ->whereIn('aggregate_id', $repairOrderIds)
            ->orderByDesc('occurred_at')
            ->get(['aggregate_id', 'actor_user_id', 'payload_json'])
            ->each(function (OperationalEvent $event) use (&$actors): void {
                if (isset($actors[$event->aggregate_id])) {
                    return;
                }

                if (($event->payload_json['to_status'] ?? null) !== RepairOrderStatus::Closed->value) {
                    return;
                }

                if ($event->actor_user_id === null) {
                    return;
                }

                $actors[$event->aggregate_id] = $event->actor_user_id;
            });

        return $actors;
    }
}
