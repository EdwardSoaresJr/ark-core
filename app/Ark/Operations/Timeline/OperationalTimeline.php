<?php

namespace App\Ark\Operations\Timeline;

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunication;
use App\Ark\Operations\Events\EventContract;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use Illuminate\Support\Collection;

class OperationalTimeline
{
    /**
     * @return Collection<int, array{occurred_at: mixed, title: string, detail: string, actor: string, tone: string}>
     */
    public function forRepairOrder(RepairOrder $repairOrder, int $limit = 10): Collection
    {
        $repairOrder->loadMissing([
            'approvalEvents.revocation',
            'communications.creator',
            'communicationEvents.creator',
            'concerns',
            'lines',
        ]);

        $events = OperationalEvent::query()
            ->with('actor:id,name')
            ->where('aggregate_type', RepairOrder::class)
            ->where('aggregate_id', $repairOrder->id)
            ->latest('occurred_at')
            ->latest('id')
            ->limit(40)
            ->get()
            ->map(fn (OperationalEvent $event): ?array => $this->entryForOperationalEvent($event, $repairOrder))
            ->filter()
            ->toBase();

        $approvals = $repairOrder->approvalEvents
            ->flatMap(function (ApprovalEvent $approvalEvent): array {
                $entries = [[
                    'occurred_at' => $approvalEvent->approved_at,
                    'title' => ($approvalEvent->approved_amount_cents ?? 0) > 0
                        ? 'Customer authorized '.$approvalEvent->approval_type->label()
                        : 'Customer deferred recommended work',
                    'detail' => $approvalEvent->source->label().' approval for '.$this->money((int) $approvalEvent->approved_amount_cents),
                    'actor' => $approvalEvent->approved_by ?: 'Customer',
                    'tone' => 'approval',
                ]];

                if ($approvalEvent->relationLoaded('revocation') && $approvalEvent->revocation) {
                    $entries[] = [
                        'occurred_at' => $approvalEvent->revocation->revoked_at,
                        'title' => 'Authorization revoked',
                        'detail' => $approvalEvent->revocation->source->label().' revocation recorded by staff',
                        'actor' => $approvalEvent->revocation->revoked_by,
                        'tone' => 'warning',
                    ];
                }

                return $entries;
            });

        $communications = $repairOrder->communications
            ->map(fn (OperationalCommunication $communication): array => [
                'occurred_at' => $communication->occurred_at,
                'title' => $communication->communication_type->label(),
                'detail' => $communication->summary,
                'actor' => $communication->creator?->name ?: $communication->direction->label(),
                'tone' => 'communication',
            ]);

        $workflowEvents = $repairOrder->communicationEvents
            ->map(fn (CommunicationEvent $event): array => [
                'occurred_at' => $event->occurred_at,
                'title' => $event->event_type->label(),
                'detail' => $event->summary,
                'actor' => $event->creator?->name ?: $event->direction->label(),
                'tone' => 'communication',
            ]);

        return $events
            ->toBase()
            ->merge($approvals)
            ->merge($communications)
            ->merge($workflowEvents)
            ->filter(fn (array $entry): bool => $entry['occurred_at'] !== null)
            ->sortByDesc('occurred_at')
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, array{occurred_at: mixed, title: string, detail: string, actor: string, tone: string}>
     */
    public function forVehicleRepairOrders(Collection $repairOrders, int $limit = 8): Collection
    {
        return $repairOrders
            ->flatMap(fn (RepairOrder $repairOrder): Collection => $this->forRepairOrder($repairOrder, 4)
                ->map(function (array $entry) use ($repairOrder): array {
                    $entry['title'] = 'RO #'.$repairOrder->repair_order_id.' · '.$entry['title'];

                    return $entry;
                }))
            ->sortByDesc('occurred_at')
            ->take($limit)
            ->values();
    }

    /**
     * @return array{occurred_at: mixed, title: string, detail: string, actor: string, tone: string}|null
     */
    private function entryForOperationalEvent(OperationalEvent $event, RepairOrder $repairOrder): ?array
    {
        $payload = $event->payload_json ?? [];
        $name = OperationalEventName::tryFrom($event->event_name);

        return match ($name) {
            OperationalEventName::RepairOrderCreated => $this->entry($event, 'Repair order opened', $repairOrder->concern_summary, 'workflow'),
            OperationalEventName::RepairOrderLifecycleChanged => $this->entry($event, 'Vehicle moved to '.$this->statusLabel($payload['to_status'] ?? null), 'Previously '.$this->statusLabel($payload['from_status'] ?? null), 'workflow'),
            OperationalEventName::RepairOrderPaymentReceived => $this->entry($event, EventContract::PaymentReceived->label(), $this->money((int) ($payload['amount_cents'] ?? 0)).' collected before release', 'financial'),
            OperationalEventName::RepairOrderPosted => $this->entry($event, 'Repair order posted', 'Sales posted for operational reporting', 'financial'),
            OperationalEventName::RepairOrderTechnicianAssigned => $this->entry($event, ($payload['to_technician_name'] ?? null) ? 'Technician assigned' : 'Technician assignment cleared', $payload['to_technician_name'] ?? 'No technician assigned', 'production'),
            OperationalEventName::OperationalCommunicationLogged => $this->entry($event, 'Customer communication logged', $this->communicationDetail($payload), 'communication'),
            OperationalEventName::ConcernCreated => $this->entry($event, 'Concern added', 'Advisor added a concern for estimate review', 'estimate'),
            OperationalEventName::ConcernDeleted => $this->entry($event, 'Concern removed', 'Concern removed before authorization', 'estimate'),
            OperationalEventName::ConcernDispositionChanged => $this->entry($event, $this->concernDispositionTitle($payload, $repairOrder), 'Previously '.$this->dispositionLabel($payload['prior_disposition'] ?? null), 'approval'),
            OperationalEventName::EstimateLineAdded => $this->entry($event, 'Estimate line added', $this->lineDetail($payload), 'estimate'),
            OperationalEventName::EstimateLineUpdated => $this->entry($event, 'Estimate line updated', $this->lineDetail($payload), 'estimate'),
            OperationalEventName::EstimateLineDeleted => $this->entry($event, 'Estimate line removed', $this->lineDetail($payload), 'estimate'),
            OperationalEventName::EstimateDocumentGenerated => $this->entry($event, 'Estimate document generated', 'Snapshot #'.($payload['document_number'] ?? '1').' preserved for customer review', 'document'),
            OperationalEventName::EstimateDocumentRefreshed => $this->entry($event, 'Estimate document refreshed', 'Snapshot updated after operational changes', 'document'),
            OperationalEventName::PartSourcingStarted,
            OperationalEventName::PartOrdered,
            OperationalEventName::PartReceived,
            OperationalEventName::PartBackordered,
            OperationalEventName::PartInstalled,
            OperationalEventName::PartCanceled => $this->entry($event, $this->partEventTitle($name), $this->partEventDetail($payload), 'parts'),
            default => null,
        };
    }

    /**
     * @return array{occurred_at: mixed, title: string, detail: string, actor: string, tone: string}
     */
    private function entry(OperationalEvent $event, string $title, string $detail, string $tone): array
    {
        return [
            'occurred_at' => $event->occurred_at,
            'title' => $title,
            'detail' => $detail,
            'actor' => $event->actor?->name ?: 'System',
            'tone' => $tone,
        ];
    }

    private function concernDispositionTitle(array $payload, RepairOrder $repairOrder): string
    {
        $concern = $repairOrder->concerns->firstWhere('id', $payload['concern_id'] ?? null);
        $summary = $concern?->summary ?: 'Recommendation';

        return match ($payload['new_disposition'] ?? null) {
            'approved' => $summary.' approved',
            'deferred' => $summary.' deferred',
            'declined' => $summary.' declined',
            'recommended' => $summary.' reopened for recommendation',
            default => $summary.' disposition changed',
        };
    }

    private function partEventTitle(OperationalEventName $name): string
    {
        return match ($name) {
            OperationalEventName::PartSourcingStarted => 'Part sourcing started',
            OperationalEventName::PartOrdered => 'Part ordered',
            OperationalEventName::PartReceived => 'Part received',
            OperationalEventName::PartBackordered => 'Part backordered',
            OperationalEventName::PartInstalled => 'Part installed',
            OperationalEventName::PartCanceled => 'Part canceled',
            default => 'Part status changed',
        };
    }

    private function partEventDetail(array $payload): string
    {
        $state = PartProcurementState::tryFrom((string) ($payload['to_state'] ?? ''));
        $identity = collect([$payload['vendor_name'] ?? null, $payload['part_number'] ?? null])
            ->filter()
            ->join(' · ');

        return trim(($state?->label() ?? 'Part status updated').($identity !== '' ? ' · '.$identity : ''));
    }

    private function communicationDetail(array $payload): string
    {
        return collect([
            isset($payload['communication_type']) ? str((string) $payload['communication_type'])->replace('_', ' ')->title()->toString() : null,
            isset($payload['channel']) ? strtoupper((string) $payload['channel']) : null,
        ])->filter()->join(' · ') ?: 'Communication recorded';
    }

    private function lineDetail(array $payload): string
    {
        return collect([
            isset($payload['type']) ? str((string) $payload['type'])->title()->toString() : 'Line',
            isset($payload['total_cents']) ? $this->money((int) $payload['total_cents']) : null,
        ])->filter()->join(' · ');
    }

    private function statusLabel(?string $status): string
    {
        return app(RepairOrderStatusCatalog::class)->labelForSlug((string) $status);
    }

    private function dispositionLabel(?string $disposition): string
    {
        return str((string) ($disposition ?: 'unknown'))->replace('_', ' ')->title()->toString();
    }

    private function money(int $cents): string
    {
        return '$'.number_format($cents / 100, 2);
    }
}
