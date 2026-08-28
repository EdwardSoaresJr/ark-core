<?php

namespace App\Ark\Operations\Briefing;

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Telephony\CallSession;
use Illuminate\Support\Carbon;

/**
 * Resolves evidence references into display rows — never duplicates authority.
 */
final class BriefingEvidenceResolver
{
    /**
     * @param  list<array{type: string, id: int|null, summary?: string, detail?: string, occurred_at?: string}>  $references
     * @return list<BriefingEvidenceItem>
     */
    public function resolve(array $references): array
    {
        $items = [];

        foreach ($references as $reference) {
            $item = $this->resolveOne($reference);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param  array{type: string, id?: int|null, summary?: string, detail?: string, occurred_at?: string}  $reference
     */
    private function resolveOne(array $reference): ?BriefingEvidenceItem
    {
        $type = (string) ($reference['type'] ?? '');
        $id = isset($reference['id']) ? (int) $reference['id'] : null;

        if ($type === 'communication_event' && $id !== null) {
            $event = CommunicationEvent::query()->find($id);

            if ($event === null) {
                return null;
            }

            return new BriefingEvidenceItem(
                sourceType: 'communication_event',
                summary: $event->summary ?? 'Communication event',
                occurredAt: $event->occurred_at ?? $event->created_at,
                detail: $event->event_type?->label(),
                sourceId: $event->id,
                sourceLabel: 'Communication event',
            );
        }

        if ($type === 'call_session' && $id !== null) {
            $call = CallSession::query()->find($id);

            if ($call === null) {
                return null;
            }

            return new BriefingEvidenceItem(
                sourceType: 'call_session',
                summary: 'Inbound call',
                occurredAt: $call->started_at ?? now(),
                detail: $call->from_number,
                sourceId: $call->id,
                sourceLabel: 'Call',
            );
        }

        if ($type === 'repair_order' && $id !== null) {
            $repairOrder = RepairOrder::query()->whereKey($id)->first();

            if ($repairOrder === null) {
                return null;
            }

            return new BriefingEvidenceItem(
                sourceType: 'repair_order',
                summary: 'RO #'.$repairOrder->repair_order_id,
                occurredAt: $repairOrder->updated_at ?? now(),
                detail: $repairOrder->concern_summary,
                sourceId: $repairOrder->repair_order_id,
                sourceLabel: 'Repair order',
            );
        }

        if ($type === 'inline' && isset($reference['summary'])) {
            return new BriefingEvidenceItem(
                sourceType: 'inline',
                summary: (string) $reference['summary'],
                occurredAt: isset($reference['occurred_at'])
                    ? Carbon::parse((string) $reference['occurred_at'])
                    : now(),
                detail: isset($reference['detail']) ? (string) $reference['detail'] : null,
                sourceLabel: 'Evidence',
            );
        }

        return null;
    }

    public function forCommunicationEvents(iterable $events): array
    {
        $items = [];

        foreach ($events as $event) {
            if (! $event instanceof CommunicationEvent) {
                continue;
            }

            $items[] = new BriefingEvidenceItem(
                sourceType: 'communication_event',
                summary: $event->summary ?? $event->event_type?->label() ?? 'Communication event',
                occurredAt: $event->occurred_at ?? $event->created_at,
                detail: $event->event_type?->label(),
                sourceId: $event->id,
                sourceLabel: 'Communication event',
            );
        }

        return $items;
    }
}
