<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Support\Collection;

final class UnresolvedAuthorizationReport
{
    public function __construct(
        private readonly WorkCompletionAuthorization $authorization,
    ) {}

    /**
     * Installed parts and completed labor that still need authorization: uncovered lines,
     * and covered lines with an outstanding unapproved price/quantity increase.
     * Read-only. Does not change the repair order or work-completion gates.
     *
     * @return Collection<int, array{repair_order_id: int, repair_order_number: int, concern_id: int, line_id: int, kind: string, pending_approval_cents: int}>
     */
    public function rows(): Collection
    {
        $rows = collect();
        /** @var array<int, RepairOrder> $repairOrders */
        $repairOrders = [];

        $installed = RepairOrderLine::query()
            ->with(['concern', 'repairOrder'])
            ->where('type', RepairOrderLineType::Part->value)
            ->where('procurement_state', PartProcurementState::Installed->value)
            ->get();

        foreach ($installed as $line) {
            $pending = $this->pendingCentsForPerformedLine($line, $repairOrders);

            if ($pending === null) {
                continue;
            }

            $rows->push($this->row($line, 'installed_part', $pending));
        }

        $completedConcerns = RepairOrderConcern::query()
            ->with(['lines', 'repairOrder'])
            ->where('production_status', ScopeProductionStatus::Completed->value)
            ->get();

        foreach ($completedConcerns as $concern) {
            foreach ($concern->lines as $line) {
                if (! $line->type->isLabor()) {
                    continue;
                }

                $line->setRelation('concern', $concern);
                if ($concern->relationLoaded('repairOrder')) {
                    $line->setRelation('repairOrder', $concern->repairOrder);
                }

                $pending = $this->pendingCentsForPerformedLine($line, $repairOrders);

                if ($pending === null) {
                    continue;
                }

                $rows->push($this->row($line, 'completed_labor', $pending));
            }
        }

        return $rows->values();
    }

    /**
     * @param  array<int, RepairOrder>  $repairOrders
     */
    private function pendingCentsForPerformedLine(RepairOrderLine $line, array &$repairOrders): ?int
    {
        $repairOrder = $this->repairOrderFor($line, $repairOrders);

        if (! $repairOrder instanceof RepairOrder) {
            return $this->authorization->lineCovered($line) ? null : (int) $line->total_cents;
        }

        $covered = $this->authorization->lineCovered($line);
        $increased = $this->authorization->approvedSubtotalsBeforeIncrease($repairOrder);

        if (! $covered) {
            return (int) $line->total_cents;
        }

        if (! isset($increased[(int) $line->id])) {
            return null;
        }

        return $this->authorization->pendingApprovalCents($repairOrder, $line);
    }

    /**
     * @param  array<int, RepairOrder>  $repairOrders
     */
    private function repairOrderFor(RepairOrderLine $line, array &$repairOrders): ?RepairOrder
    {
        $id = (int) $line->repair_order_id;

        if (! isset($repairOrders[$id])) {
            $repairOrder = $line->relationLoaded('repairOrder')
                ? $line->repairOrder
                : null;

            if (! $repairOrder instanceof RepairOrder) {
                $repairOrder = RepairOrder::query()->find($id);
            }

            if ($repairOrder instanceof RepairOrder) {
                $repairOrder->loadMissing(['lines.concern', 'concerns']);
            }

            $repairOrders[$id] = $repairOrder;
        }

        return $repairOrders[$id];
    }

    /**
     * @return array{repair_order_id: int, repair_order_number: int, concern_id: int, line_id: int, kind: string, pending_approval_cents: int}
     */
    private function row(RepairOrderLine $line, string $kind, int $pendingApprovalCents): array
    {
        $line->loadMissing('repairOrder');

        return [
            'repair_order_id' => (int) $line->repair_order_id,
            'repair_order_number' => (int) ($line->repairOrder?->repair_order_id ?? 0),
            'concern_id' => (int) $line->repair_order_concern_id,
            'line_id' => (int) $line->id,
            'kind' => $kind,
            'pending_approval_cents' => $pendingApprovalCents,
        ];
    }
}
