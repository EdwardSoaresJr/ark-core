<?php

namespace App\Ark\Dragon\Agent\Tools;

use App\Ark\Dragon\Agent\DragonAgentTool;
use App\Ark\Operations\Inspections\InspectionFindingCardProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;

final class InspectionsGetTool implements DragonAgentTool
{
    public function name(): string
    {
        return 'inspections.get';
    }

    public function description(): string
    {
        return 'Read recorded inspection findings. Pass repair_order_id for one RO. Omit repair_order_id to discover open ROs that already have recorded inspection findings (use this for “what inspections need attention?”). Do not invent measurements. No customer identity, no photo binaries.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'repair_order_id' => ['type' => 'string'],
            ],
        ];
    }

    public function invoke(array $arguments): array
    {
        $id = trim((string) ($arguments['repair_order_id'] ?? ''));
        if ($id === '') {
            return $this->shopRecordedFindings();
        }

        $repairOrder = RepairOrder::query()
            ->with(['inspection.items.measurements', 'inspection.items.photos', 'vehicle:id,year,make,model,trim'])
            ->where('repair_order_id', $id)
            ->first();

        if ($repairOrder === null) {
            return ['ok' => false, 'error' => 'Repair order not found.'];
        }

        return $this->forRepairOrder($repairOrder);
    }

    /**
     * @return array<string, mixed>
     */
    private function shopRecordedFindings(): array
    {
        $closed = array_map(
            fn (RepairOrderStatus $status): string => $status->value,
            array_values(array_filter(
                RepairOrderStatus::cases(),
                fn (RepairOrderStatus $status): bool => $status->isTerminal(),
            )),
        );

        $orders = RepairOrder::query()
            ->with(['inspection.items.measurements', 'inspection.items.photos', 'vehicle:id,year,make,model,trim'])
            ->whereNotIn('status', $closed)
            ->whereHas('inspection.items')
            ->orderByRaw('COALESCE(opened_at, created_at) ASC')
            ->limit(40)
            ->get();

        $hits = [];
        foreach ($orders as $repairOrder) {
            $payload = $this->forRepairOrder($repairOrder);
            if (($payload['findings'] ?? []) === []) {
                continue;
            }
            $hits[] = [
                'repair_order_id' => $repairOrder->repair_order_id,
                'vehicle_label' => $repairOrder->vehicle?->display_name ?? 'Vehicle',
                'status' => $repairOrder->status->value,
                'finding_count' => count($payload['findings']),
                'findings' => array_slice($payload['findings'], 0, 8),
            ];
            if (count($hits) >= 12) {
                break;
            }
        }

        return [
            'ok' => true,
            'read_only' => true,
            'scope' => 'open_repair_orders_with_recorded_findings',
            'repair_orders' => $hits,
            'note' => $hits === []
                ? 'No open repair orders currently have recorded inspection findings.'
                : 'Recorded findings on open repair orders. Name vehicles/ROs; do not conclude “none” from an empty single-RO lookup.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function forRepairOrder(RepairOrder $repairOrder): array
    {
        if ($repairOrder->inspection === null) {
            return [
                'ok' => true,
                'read_only' => true,
                'repair_order_id' => $repairOrder->repair_order_id,
                'findings' => [],
                'note' => 'No inspection on this repair order.',
            ];
        }

        $findings = [];
        foreach ($repairOrder->inspection->items as $item) {
            if (! InspectionFindingCardProjection::isRecorded($item)) {
                continue;
            }
            $card = InspectionFindingCardProjection::forItem($item, $repairOrder);
            unset($card['first_photo_url'], $card['first_video_url']);
            $findings[] = $card;
        }

        return [
            'ok' => true,
            'read_only' => true,
            'repair_order_id' => $repairOrder->repair_order_id,
            'findings' => $findings,
        ];
    }
}
