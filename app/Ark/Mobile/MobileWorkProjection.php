<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Inspections\InspectionObservedState;
use App\Ark\Operations\RepairOrders\RepairActionOwnerType;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Support\Collection;

final class MobileWorkProjection
{
    public function __construct(
        private readonly MobileStaffAccess $access,
    ) {}

    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     count: int,
     * }
     */
    public function forUser(User $user): array
    {
        $items = $this->repairOrdersForUser($user)
            ->map(fn (RepairOrder $repairOrder): array => $this->presentCard($repairOrder, $user))
            ->values()
            ->all();

        return [
            'items' => $items,
            'count' => count($items),
        ];
    }

    /**
     * @return Collection<int, RepairOrder>
     */
    private function repairOrdersForUser(User $user): Collection
    {
        $query = RepairOrder::query()
            ->with([
                'customer:id,first_name,last_name',
                'vehicle:id,year,make,model,trim',
                'concerns:id,repair_order_id,position',
                'concerns.workGroups:id,repair_order_concern_id,owner_type,owner_user_id,title,status,latest_update',
                'concerns.workGroups.ownerUser:id,name',
                'inspection.items' => fn ($query) => $query
                    ->whereNotNull('inspection_template_item_id')
                    ->orderBy('position')
                    ->orderBy('id')
                    ->select('id', 'inspection_id', 'label', 'observed_state', 'position'),
            ])
            ->whereIn('status', $this->openStatusSlugs())
            ->orderByDesc('updated_at');

        if ($user->hasRole(ArkRole::Technician->value) && ! $user->can(ArkCapability::OperationsAccess->value)) {
            $query->whereHas('concerns.workGroups', function ($workGroups) use ($user): void {
                $workGroups
                    ->where('owner_type', RepairActionOwnerType::Technician->value)
                    ->where('owner_user_id', $user->id);
            });
        }

        return $query->limit(50)->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentCard(RepairOrder $repairOrder, User $user): array
    {
        $vehicle = $repairOrder->vehicle;
        $status = $repairOrder->status;

        $primaryConcern = $repairOrder->concerns->first();
        $isProductionTechnician = $user->hasRole(ArkRole::Technician->value)
            && ! $user->can(ArkCapability::OperationsAccess->value);

        return [
            'id' => $repairOrder->repair_order_id,
            'repair_order_id' => $repairOrder->repair_order_id,
            'customer_name' => $repairOrder->customer?->name ?? 'Unknown',
            'vehicle_label' => $vehicle?->display_name ?? 'Vehicle',
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_tone' => MobileRepairOrderStatusTone::forStatus($status),
            'concern_summary' => $repairOrder->concern_summary,
            'primary_concern_id' => $primaryConcern?->id,
            'assigned_technician' => $repairOrder->repairActionOwnerSummary(),
            'attention_reason' => $this->attentionReason($repairOrder),
            'next_action' => $this->nextAction($repairOrder, $isProductionTechnician),
            'entry_section' => $isProductionTechnician ? 'inspection' : null,
            'age_label' => $repairOrder->updated_at?->diffForHumans(),
            'updated_at' => $repairOrder->updated_at?->toIso8601String(),
        ];
    }

    private function nextAction(RepairOrder $repairOrder, bool $productionTechnician): string
    {
        $status = $repairOrder->status;

        if ($productionTechnician) {
            $inspectionNext = $this->inspectionNextLabel($repairOrder);
            if ($inspectionNext !== null) {
                return $inspectionNext;
            }
        }

        if (! $repairOrder->hasRepairActionOwner() && $status->isOneOf([
            RepairOrderStatus::Approved,
            RepairOrderStatus::ReadyForWork,
        ])) {
            return 'Assign Repair Action owners';
        }

        return match ($status->value) {
            RepairOrderStatus::Draft->value, RepairOrderStatus::Estimate->value => 'Review estimate',
            RepairOrderStatus::WaitingApproval->value => 'Follow up with customer',
            RepairOrderStatus::Approved->value, RepairOrderStatus::ReadyForWork->value => 'Start inspection',
            RepairOrderStatus::WaitingParts->value => 'Check parts status',
            RepairOrderStatus::InProgress->value => $productionTechnician ? 'Continue inspection' : 'Record findings',
            RepairOrderStatus::QualityCheck->value => 'Verify work',
            RepairOrderStatus::Completed->value => 'Invoice or payment',
            RepairOrderStatus::Invoiced->value, RepairOrderStatus::ReadyPickup->value => 'Customer pickup',
            default => 'Open',
        };
    }

    private function attentionReason(RepairOrder $repairOrder): ?string
    {
        if (! $repairOrder->hasRepairActionOwner() && $repairOrder->status->isOneOf([
            RepairOrderStatus::Approved,
            RepairOrderStatus::ReadyForWork,
        ])) {
            return 'Needs Repair Action owner';
        }

        return null;
    }

    private function inspectionNextLabel(RepairOrder $repairOrder): ?string
    {
        $inspection = $repairOrder->inspection;
        if ($inspection === null) {
            return null;
        }

        $items = $inspection->items;
        if ($items->isEmpty()) {
            return 'Start inspection';
        }

        $next = $items->first(
            fn ($item): bool => $item->observed_state === InspectionObservedState::NotChecked,
        );
        if ($next === null) {
            return 'Inspection complete — review production';
        }

        $label = trim((string) ($next->label ?? ''));
        if ($label === '') {
            return 'Continue inspection';
        }

        return 'Inspect: '.$label;
    }

    /**
     * @return list<string>
     */
    private function openStatusSlugs(): array
    {
        return array_values(array_map(
            fn (RepairOrderStatus $status): string => $status->value,
            array_filter(
                RepairOrderStatus::cases(),
                fn (RepairOrderStatus $status): bool => ! $status->isTerminal(),
            ),
        ));
    }
}
