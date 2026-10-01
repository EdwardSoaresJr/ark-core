<?php

namespace App\Ark\Operations\Workboard;

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

final class WorkboardTriageRepairOrderQuery
{
    public const RECENT_COMMUNICATION_EVENTS_PER_RO = 20;

    /**
     * @return Collection<int, RepairOrder>
     */
    public function forAdvisor(): Collection
    {
        $repairOrders = RepairOrder::query()
            ->with([
                'customer',
                'vehicle',
                'assignedTechnician:id,name',
                'lines.concern:id,disposition,production_status',
                'concerns.workGroups.ownerUser:id,name',
            ])
            ->whereIn('status', WorkboardSwimlaneCatalog::advisorTriageQueueSlugs())
            ->latest()
            ->get();

        $this->attachRecentCommunicationEvents($repairOrders);

        return $repairOrders;
    }

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     */
    private function attachRecentCommunicationEvents(Collection $repairOrders): void
    {
        if ($repairOrders->isEmpty()) {
            return;
        }

        $ids = $repairOrders->modelKeys();
        $limit = self::RECENT_COMMUNICATION_EVENTS_PER_RO;

        /** @var SupportCollection<int, SupportCollection<int, CommunicationEvent>> $grouped */
        $grouped = CommunicationEvent::query()
            ->whereIn('repair_order_id', $ids)
            ->where('occurred_at', '>=', now()->subDays(45))
            ->orderByDesc('occurred_at')
            ->get([
                'id',
                'repair_order_id',
                'event_type',
                'channel',
                'direction',
                'summary',
                'occurred_at',
                'created_at',
            ])
            ->groupBy('repair_order_id');

        foreach ($repairOrders as $repairOrder) {
            $events = ($grouped->get($repairOrder->id) ?? collect())->take($limit)->values();

            foreach ($events as $event) {
                $event->setRelation('repairOrder', $repairOrder);
            }

            $repairOrder->setRelation('communicationEvents', $events);
        }
    }
}
