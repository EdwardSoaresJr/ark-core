<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationLink;
use App\Ark\Operations\Inspections\InspectionFindingCardProjection;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\Timeline\UnifiedOperationalTimeline;
use App\Models\User;
use Illuminate\Support\Collection;

final class MobileRepairOrderProjection
{
    public function __construct(
        private readonly UnifiedOperationalTimeline $timeline,
        private readonly MobileEstimateProjection $estimate,
        private readonly MobileStaffAccess $access,
        private readonly MobileUserPresenter $userPresenter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forRepairOrder(RepairOrder $repairOrder, User $viewer): array
    {
        $repairOrder->loadMissing([
            'customer',
            'vehicle',
            'assignedTechnician:id,name',
            'concerns.lines',
            'inspection.items.measurements',
            'inspection.items.photos',
            'inspection.items.concern',
        ]);

        $status = $repairOrder->status;
        $vehicle = $repairOrder->vehicle;
        $recordedItems = $this->recordedItems($repairOrder);
        // Technicians do not own financials (technician-scope doctrine), so the
        // money payload is omitted entirely — not just hidden in the UI.
        $showMoney = $this->userPresenter->repairOrderWorkspaceProfile($viewer) !== 'technician';

        return [
            'id' => $repairOrder->repair_order_id,
            'repair_order_id' => $repairOrder->repair_order_id,
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_tone' => MobileRepairOrderStatusTone::forStatus($status),
            'concern_summary' => $repairOrder->concern_summary,
            'customer' => [
                'id' => $repairOrder->customer_id,
                'name' => $repairOrder->customer?->name,
            ],
            'vehicle' => [
                'id' => $repairOrder->vehicle_id,
                'label' => $vehicle?->display_name ?? 'Vehicle',
                'vin' => $vehicle?->vin,
                'plate' => $vehicle?->plate,
            ],
            'assigned_technician' => $repairOrder->assignedTechnician?->name,
            'assigned_technician_id' => $repairOrder->assigned_technician_id,
            'concerns' => $repairOrder->concerns
                ->map(function (RepairOrderConcern $concern) use ($recordedItems, $showMoney, $repairOrder): array {
                    $card = MobileConcernProjection::card($concern, $recordedItems);

                    if ($showMoney) {
                        $subtotalLabel = $this->estimate->concernSubtotalLabel($repairOrder, $concern->id);
                        if ($subtotalLabel !== null) {
                            $card['subtotal_label'] = $subtotalLabel;
                        }
                    }

                    return $card;
                })
                ->all(),
            'approved_work' => $this->approvedWork($repairOrder),
            'estimate' => $showMoney ? $this->estimate->detail($repairOrder, $viewer, $this->access) : null,
            'recent_findings' => $this->recentFindings($recordedItems, $repairOrder),
            'communications' => $this->communicationsPreview($repairOrder),
            'quick_actions' => [
                'add_finding' => true,
                'open_inspection' => $repairOrder->inspection !== null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forConcern(RepairOrder $repairOrder, RepairOrderConcern $concern, User $viewer, MobileStaffAccess $access): array
    {
        $repairOrder->loadMissing([
            'inspection.items.measurements',
            'inspection.items.photos',
            'inspection.items.concern',
        ]);

        $concern->loadMissing(['lines']);

        abort_unless((int) $concern->repair_order_id === (int) $repairOrder->id, 404);

        return MobileConcernProjection::detail(
            $concern,
            $repairOrder,
            $this->recordedItems($repairOrder),
            $viewer,
            $access,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function forFinding(RepairOrder $repairOrder, InspectionItem $item): array
    {
        $item->loadMissing(['inspection', 'measurements', 'photos.uploadedBy', 'concern']);

        abort_unless(
            $item->inspection !== null && (int) $item->inspection->repair_order_id === (int) $repairOrder->id,
            404,
        );

        abort_unless(InspectionFindingCardProjection::isRecorded($item), 404);

        return MobileFindingProjection::detail($item, $repairOrder);
    }

    /**
     * @return Collection<int, InspectionItem>
     */
    private function recordedItems(RepairOrder $repairOrder): Collection
    {
        $inspection = $repairOrder->inspection;

        if ($inspection === null) {
            return collect();
        }

        return $inspection->items
            ->filter(fn (InspectionItem $item): bool => InspectionFindingCardProjection::isRecorded($item));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function approvedWork(RepairOrder $repairOrder): array
    {
        return $repairOrder->concerns
            ->filter(fn (RepairOrderConcern $concern): bool => $concern->disposition === RepairOrderConcernDisposition::Approved)
            ->flatMap(fn (RepairOrderConcern $concern) => $concern->lines->map(fn ($line): array => [
                'id' => $line->id,
                'concern_id' => $concern->id,
                'type' => $line->type->value ?? (string) $line->type,
                'description' => $line->description,
                'quantity' => $line->quantity,
            ]))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, InspectionItem>  $recordedItems
     * @return list<array<string, mixed>>
     */
    private function recentFindings(Collection $recordedItems, RepairOrder $repairOrder): array
    {
        return $recordedItems
            ->sortByDesc(fn (InspectionItem $item) => $item->updated_at?->timestamp ?? 0)
            ->take(25)
            ->map(fn (InspectionItem $item): array => MobileFindingProjection::summary($item, $repairOrder))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function communicationsPreview(RepairOrder $repairOrder): array
    {
        $conversationId = ConversationLink::query()
            ->where('linkable_type', $repairOrder->getMorphClass())
            ->where('linkable_id', $repairOrder->id)
            ->value('conversation_id');

        if ($conversationId === null) {
            return [];
        }

        $conversation = Conversation::query()->find($conversationId);

        if ($conversation === null) {
            return [];
        }

        return $this->timeline
            ->forConversationRelationship($conversation, 12)
            ->reverse()
            ->values()
            ->map(fn ($entry): array => [
                ...$entry->toArray(),
                'occurred_at' => $entry->occurredAt->toIso8601String(),
            ])
            ->all();
    }
}
