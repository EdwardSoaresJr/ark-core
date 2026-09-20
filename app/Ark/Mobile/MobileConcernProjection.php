<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Inspections\InspectionFindingIntent;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\ScopeProductionStatus;
use App\Models\User;
use Illuminate\Support\Collection;

final class MobileConcernProjection
{
    /**
     * @param  Collection<int, InspectionItem>  $recordedItems
     * @return array<string, mixed>
     */
    public static function card(RepairOrderConcern $concern, Collection $recordedItems): array
    {
        $counts = self::counts($concern, $recordedItems);
        $customerNarrative = trim((string) ($concern->customer_states ?: $concern->notes));

        return [
            'id' => $concern->id,
            'title' => $concern->summary,
            'customer_narrative' => $customerNarrative !== '' ? $customerNarrative : null,
            'dtcs_summary' => filled(trim((string) $concern->dtcs_summary))
                ? trim((string) $concern->dtcs_summary)
                : null,
            'disposition' => $concern->disposition->value,
            'disposition_label' => $concern->disposition->label(),
            'production_status' => $concern->productionStatus()->value,
            'production_status_label' => $concern->productionStatus()->label(),
            'status_label' => self::statusLabel($concern),
            'findings_count' => $counts['findings_count'],
            'photo_count' => $counts['photo_count'],
            'notes_count' => $counts['notes_count'],
            'recommendation_count' => $counts['recommendation_count'],
            'created_at' => $concern->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, InspectionItem>  $recordedItems
     * @return array<string, mixed>
     */
    public static function detail(
        RepairOrderConcern $concern,
        RepairOrder $repairOrder,
        Collection $recordedItems,
        ?User $viewer = null,
        ?MobileStaffAccess $access = null,
    ): array {
        $card = self::card($concern, $recordedItems);
        $canRecord = $viewer !== null
            && $access !== null
            && $access->canRecordFinding($viewer, $repairOrder);

        return [
            ...$card,
            'customer_concern' => $card['customer_narrative'],
            'verified_findings' => filled(trim((string) $concern->verified_findings))
                ? trim((string) $concern->verified_findings)
                : null,
            'recommendation' => filled(trim((string) $concern->recommendation))
                ? trim((string) $concern->recommendation)
                : null,
            'findings' => MobileFindingProjection::forConcern($concern->id, $recordedItems, $repairOrder),
            'recommendations' => $concern->lines->map(fn ($line): array => [
                'id' => $line->id,
                'type' => $line->type->value ?? (string) $line->type,
                'type_label' => $line->type->staffLabel(),
                'is_note' => $line->type->isNote(),
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price_label' => $line->type->isNote() ? null : '$'.number_format((int) ($line->unit_price_cents ?? 0) / 100, 2),
                'total_label' => $line->type->isNote() ? null : '$'.number_format((int) ($line->total_cents ?? 0) / 100, 2),
                'is_private' => $line->isPrivateNote(),
                'visible_to_advisor' => $line->type->isNote() ? $line->isVisibleToAdvisor() : false,
                'visible_to_technician' => $line->type->isNote() ? $line->isVisibleToTechnician() : false,
                'visible_to_customer' => $line->type->isNote() ? $line->isVisibleToCustomer() : false,
            ])->all(),
            'production' => self::production($concern, $repairOrder, $viewer, $access),
            'disposition_control' => self::dispositionControl($concern, $repairOrder, $viewer, $access),
            'scope_management' => self::scopeManagement($concern, $repairOrder, $viewer, $access),
            'quick_actions' => [
                'add_finding' => $canRecord,
                'add_photo' => $canRecord,
                'add_note' => $canRecord,
                'update_inspection' => $canRecord,
                'complete_scope' => $concern->tracksProduction()
                    && $concern->productionStatus() !== ScopeProductionStatus::Completed,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function production(
        RepairOrderConcern $concern,
        RepairOrder $repairOrder,
        ?User $viewer,
        ?MobileStaffAccess $access,
    ): array {
        $canUpdate = $viewer !== null
            && $access !== null
            && $concern->tracksProduction()
            && $access->canUpdateConcernProductionStatus($viewer, $repairOrder);

        return [
            'tracks' => $concern->tracksProduction(),
            'status' => $concern->productionStatus()->value,
            'label' => $concern->productionStatus()->label(),
            'can_update' => $canUpdate,
            'options' => $concern->tracksProduction()
                ? array_map(
                    fn (ScopeProductionStatus $status): array => [
                        'value' => $status->value,
                        'label' => $status->label(),
                        'help' => $status->helpText(),
                    ],
                    ScopeProductionStatus::cases(),
                )
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function dispositionControl(
        RepairOrderConcern $concern,
        RepairOrder $repairOrder,
        ?User $viewer,
        ?MobileStaffAccess $access,
    ): array {
        $canUpdate = $viewer !== null
            && $access !== null
            && ! $repairOrder->isTerminal()
            && $access->canSetConcernDisposition($viewer, $repairOrder);

        return [
            'current' => $concern->disposition->value,
            'label' => $concern->disposition->label(),
            'can_update' => $canUpdate,
            'options' => $canUpdate
                ? array_map(
                    fn (RepairOrderConcernDisposition $disposition): array => [
                        'value' => $disposition->value,
                        'label' => $disposition->label(),
                        'help' => $disposition->helpText(),
                    ],
                    RepairOrderConcernDisposition::cases(),
                )
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function scopeManagement(
        RepairOrderConcern $concern,
        RepairOrder $repairOrder,
        ?User $viewer,
        ?MobileStaffAccess $access,
    ): array {
        $canManage = $viewer !== null
            && $access !== null
            && ! $repairOrder->isTerminal()
            && $access->canSetConcernDisposition($viewer, $repairOrder);

        $hasLines = $concern->lines->isNotEmpty();

        return [
            'can_delete' => $canManage && ! $hasLines,
            'blocked_reason' => $hasLines ? 'Remove estimate lines before deleting this concern.' : null,
        ];
    }

    public static function statusLabel(RepairOrderConcern $concern): string
    {
        if ($concern->disposition === RepairOrderConcernDisposition::Approved) {
            return $concern->productionStatus()->label();
        }

        return $concern->disposition->label();
    }

    /**
     * @param  Collection<int, InspectionItem>  $recordedItems
     * @return array{
     *     findings_count: int,
     *     photo_count: int,
     *     notes_count: int,
     *     recommendation_count: int,
     * }
     */
    public static function counts(RepairOrderConcern $concern, Collection $recordedItems): array
    {
        $items = $recordedItems->filter(
            fn (InspectionItem $item): bool => (int) $item->repair_order_concern_id === (int) $concern->id,
        );

        $notesCount = $items->filter(
            fn (InspectionItem $item): bool => filled(InspectionFindingIntent::stripNotesPrefix($item->notes)),
        )->count();

        if (filled(trim((string) $concern->notes))) {
            $notesCount++;
        }

        return [
            'findings_count' => $items->count(),
            'photo_count' => $items->sum(fn (InspectionItem $item): int => $item->photos->count()),
            'notes_count' => $notesCount,
            'recommendation_count' => $concern->lines->count(),
        ];
    }
}
