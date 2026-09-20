<?php

namespace App\Ark\Dragon;

use App\Ark\Mobile\MobileRepairOrderStatusTone;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Illuminate\Support\Collection;

/**
 * Minimized shop-floor projection for Dragon — read-only, no customer PII.
 *
 * Summary counts are computed across ALL open repair orders.
 * Item cards may be capped (config shop.dragon_work_items_limit) with truncation flagged.
 *
 * Oldest active RO uses displayOpenedAt() = opened_at ?? created_at (not updated_at).
 */
final class DragonWorkProjection
{
    /**
     * @return array{
     *     summary: array<string, mixed>,
     *     items: list<array<string, mixed>>,
     *     items_truncated: bool,
     *     items_limit: int,
     *     items_returned: int,
     *     open_ro_total: int,
     * }
     */
    public function shopFloor(bool $includeCustomerLabel = false): array
    {
        $limit = max(1, (int) config('shop.dragon_work_items_limit', 500));

        $openOrders = $this->openRepairOrdersQuery()
            ->with($this->cardRelations($includeCustomerLabel))
            ->orderByRaw('COALESCE(opened_at, created_at) ASC')
            ->orderBy('id')
            ->get();

        $total = $openOrders->count();
        $truncated = $total > $limit;
        $cards = $openOrders
            ->take($limit)
            ->map(fn (RepairOrder $repairOrder): array => $this->presentCard($repairOrder, $includeCustomerLabel))
            ->values()
            ->all();

        return [
            'summary' => $this->buildSummary($openOrders),
            'items' => $cards,
            'items_truncated' => $truncated,
            'items_limit' => $limit,
            'items_returned' => count($cards),
            'open_ro_total' => $total,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summaryOnly(): array
    {
        $openOrders = $this->openRepairOrdersQuery()
            ->with([
                'vehicle:id,year,make,model,trim',
                'assignedTechnician:id,name',
                'concerns:id,repair_order_id,position,summary',
                'concerns.workGroups:id,repair_order_concern_id,owner_type,owner_user_id,title,status',
                'concerns.workGroups.ownerUser:id,name',
            ])
            ->orderByRaw('COALESCE(opened_at, created_at) ASC')
            ->orderBy('id')
            ->get();

        return $this->buildSummary($openOrders);
    }

    /**
     * Full open-RO card list. Used by summary and constrained query — never truncated.
     *
     * @return list<array<string, mixed>>
     */
    public function allOpenCards(): array
    {
        return $this->openRepairOrdersQuery()
            ->with([
                'vehicle:id,year,make,model,trim',
                'assignedTechnician:id,name',
                'concerns:id,repair_order_id,position,summary',
                'concerns.workGroups:id,repair_order_concern_id,owner_type,owner_user_id,title,status',
                'concerns.workGroups.ownerUser:id,name',
            ])
            ->orderByRaw('COALESCE(opened_at, created_at) ASC')
            ->orderBy('id')
            ->get()
            ->map(fn (RepairOrder $repairOrder): array => $this->presentCard($repairOrder))
            ->values()
            ->all();
    }

    /**
     * Same statuses as in_production_count on the Dragon summary.
     *
     * @return list<string>
     */
    public function productionStatusSlugs(): array
    {
        return [
            RepairOrderStatus::Approved->value,
            RepairOrderStatus::ReadyForWork->value,
            RepairOrderStatus::InProgress->value,
            RepairOrderStatus::WaitingParts->value,
            RepairOrderStatus::QualityCheck->value,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function repairOrder(string $repairOrderId, bool $includeCustomerLabel = false): ?array
    {
        /** @var RepairOrder|null $repairOrder */
        $repairOrder = RepairOrder::query()
            ->with($this->cardRelations($includeCustomerLabel))
            ->where('repair_order_id', $repairOrderId)
            ->first();

        if ($repairOrder === null || $repairOrder->status->isTerminal()) {
            return null;
        }

        return $this->presentCard($repairOrder, $includeCustomerLabel);
    }

    /**
     * @param  Collection<int, RepairOrder>  $openOrders
     * @return array<string, mixed>
     */
    private function buildSummary(Collection $openOrders): array
    {
        $byStatus = [];
        $byTech = [];
        $waiting = [];
        $inProduction = 0;
        $inProductionRows = [];
        $unassignedRows = [];

        $productionStatuses = $this->productionStatusSlugs();

        foreach ($openOrders as $repairOrder) {
            $status = $repairOrder->status->value;
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;

            $tech = $this->technicianLabel($repairOrder) ?? 'Unassigned';
            $byTech[$tech] = ($byTech[$tech] ?? 0) + 1;

            if ($status === RepairOrderStatus::WaitingApproval->value) {
                $waiting[] = [
                    'repair_order_id' => $repairOrder->repair_order_id,
                    'vehicle_label' => $repairOrder->vehicle?->display_name ?? 'Vehicle',
                    'status' => $status,
                    'status_label' => $repairOrder->status->label(),
                    'opened_at' => $repairOrder->displayOpenedAt()->toIso8601String(),
                ];
            }

            if (in_array($status, $productionStatuses, true)) {
                $inProduction++;
                $inProductionRows[] = [
                    'repair_order_id' => $repairOrder->repair_order_id,
                    'vehicle_label' => $repairOrder->vehicle?->display_name ?? 'Vehicle',
                    'status' => $status,
                    'status_label' => $repairOrder->status->label(),
                    'assigned_technician' => $this->technicianLabel($repairOrder),
                ];
            }

            if ($this->technicianLabel($repairOrder) === null) {
                $unassignedRows[] = [
                    'repair_order_id' => $repairOrder->repair_order_id,
                    'vehicle_label' => $repairOrder->vehicle?->display_name ?? 'Vehicle',
                    'status' => $status,
                    'status_label' => $repairOrder->status->label(),
                ];
            }
        }

        ksort($byStatus);
        arsort($byTech);

        $oldest = $openOrders->first();

        return [
            'open_ro_count' => $openOrders->count(),
            'waiting_for_approval_count' => count($waiting),
            'in_production_count' => $inProduction,
            'status_counts' => $byStatus,
            'technician_counts' => $byTech === [] ? new \stdClass : $byTech,
            'waiting_for_approval' => array_slice($waiting, 0, 25),
            'unassigned_repair_orders' => array_slice($unassignedRows, 0, 25),
            'in_production_repair_orders' => array_slice($inProductionRows, 0, 25),
            'oldest_active_ro' => $oldest === null ? null : [
                'repair_order_id' => $oldest->repair_order_id,
                'vehicle_label' => $oldest->vehicle?->display_name ?? 'Vehicle',
                'status' => $oldest->status->value,
                'status_label' => $oldest->status->label(),
                'assigned_technician' => $this->technicianLabel($oldest),
                'opened_at' => $oldest->displayOpenedAt()->toIso8601String(),
                'opened_at_field' => $oldest->opened_at !== null ? 'opened_at' : 'created_at',
                'updated_at' => $oldest->updated_at?->toIso8601String(),
            ],
            'oldest_active_ro_basis' => 'displayOpenedAt = opened_at ?? created_at',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentCard(RepairOrder $repairOrder, bool $includeCustomerLabel = false): array
    {
        $status = $repairOrder->status;
        $opened = $repairOrder->displayOpenedAt();

        $card = [
            'repair_order_id' => $repairOrder->repair_order_id,
            'vehicle_label' => $this->vehicleLabel($repairOrder),
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_tone' => MobileRepairOrderStatusTone::forStatus($status),
            'concern_summary' => $this->concernSummary($repairOrder),
            'assigned_technician' => $this->technicianLabel($repairOrder),
            'next_action' => $this->nextAction($repairOrder),
            'opened_at' => $opened->toIso8601String(),
            'opened_at_field' => $repairOrder->opened_at !== null ? 'opened_at' : 'created_at',
            'updated_at' => $repairOrder->updated_at?->toIso8601String(),
            'age_label' => $opened->diffForHumans(),
        ];

        if ($includeCustomerLabel) {
            $card['customer_label'] = $this->customerLabel($repairOrder);
        }

        return $card;
    }

    /**
     * @return list<string>
     */
    private function cardRelations(bool $includeCustomerLabel): array
    {
        $relations = [
            'vehicle:id,year,make,model,trim',
            'assignedTechnician:id,name',
            'concerns:id,repair_order_id,position,summary',
            'concerns.workGroups:id,repair_order_concern_id,owner_type,owner_user_id,title,status',
            'concerns.workGroups.ownerUser:id,name',
        ];

        if ($includeCustomerLabel) {
            $relations[] = 'customer:id,first_name,last_name';
        }

        return $relations;
    }

    private function customerLabel(RepairOrder $repairOrder): string
    {
        $name = trim((string) ($repairOrder->customer?->name ?? ''));

        return $name !== '' ? $name : 'Customer';
    }

    private function vehicleLabel(RepairOrder $repairOrder): string
    {
        $vehicle = $repairOrder->vehicle;
        if ($vehicle === null) {
            return 'Vehicle';
        }

        // Dragon answers want Year/Make/Model — trim packages read as noise on the floor.
        $label = trim(implode(' ', array_filter([
            $vehicle->year,
            $vehicle->make,
            $vehicle->model,
        ], static fn ($part): bool => filled($part))));

        return $label !== '' ? $label : (string) ($vehicle->display_name ?: 'Vehicle');
    }

    private function concernSummary(RepairOrder $repairOrder): ?string
    {
        $fromRo = $this->short($repairOrder->concern_summary, 160);
        if (filled($fromRo)) {
            return $fromRo;
        }

        $concern = $repairOrder->relationLoaded('concerns')
            ? $repairOrder->concerns->sortBy('position')->first()
            : null;
        if ($concern === null) {
            return null;
        }

        return $this->short($concern->summary ?? null, 160);
    }

    private function technicianLabel(RepairOrder $repairOrder): ?string
    {
        $fromActions = $repairOrder->repairActionOwnerSummary();
        if (filled($fromActions)) {
            return $fromActions;
        }

        $legacy = $repairOrder->assignedTechnician?->name;

        return filled($legacy) ? (string) $legacy : null;
    }

    private function nextAction(RepairOrder $repairOrder): string
    {
        $status = $repairOrder->status;

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
            RepairOrderStatus::InProgress->value => 'Continue production',
            RepairOrderStatus::QualityCheck->value => 'Verify work',
            RepairOrderStatus::Completed->value => 'Invoice or payment',
            RepairOrderStatus::Invoiced->value, RepairOrderStatus::ReadyPickup->value => 'Customer pickup',
            default => 'Open',
        };
    }

    private function openRepairOrdersQuery()
    {
        return RepairOrder::query()
            ->whereIn('status', $this->openStatusSlugs());
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

    private function short(?string $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim($value);
        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit - 1).'…';
    }
}
