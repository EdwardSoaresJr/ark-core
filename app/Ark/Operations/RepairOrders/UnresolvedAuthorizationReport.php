<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Support\Collection;

final class UnresolvedAuthorizationReport
{
    public function __construct(
        private readonly WorkCompletionAuthorization $authorization,
    ) {}

    /**
     * Installed parts and completed labor that lack an approval scope, or that are
     * covered but still carry an unapproved price/qty increase. Read-only.
     *
     * @return Collection<int, array{repair_order_id: int, repair_order_number: int, concern_id: int, line_id: int, kind: string, pending_approval_cents: int}>
     */
    public function rows(): Collection
    {
        $rows = collect();

        $installed = RepairOrderLine::query()
            ->with(['concern', 'repairOrder.lines.concern', 'repairOrder.concerns'])
            ->where('type', RepairOrderLineType::Part->value)
            ->where('procurement_state', PartProcurementState::Installed->value)
            ->get()
            ->groupBy('repair_order_id');

        foreach ($installed as $lines) {
            $repairOrder = $lines->first()?->repairOrder;
            if (! $repairOrder instanceof RepairOrder) {
                continue;
            }

            $increased = $this->authorization->approvedSubtotalsBeforeIncrease($repairOrder);

            foreach ($lines as $line) {
                $covered = $this->authorization->lineCovered($line);
                $hasIncrease = isset($increased[(int) $line->id]);

                if ($covered && ! $hasIncrease) {
                    continue;
                }

                $rows->push($this->row(
                    $line,
                    'installed_part',
                    $this->authorization->pendingApprovalCents($repairOrder, $line),
                ));
            }
        }

        $completedConcerns = RepairOrderConcern::query()
            ->with(['lines', 'repairOrder.lines.concern', 'repairOrder.concerns'])
            ->where('production_status', ScopeProductionStatus::Completed->value)
            ->get()
            ->groupBy('repair_order_id');

        foreach ($completedConcerns as $concerns) {
            $repairOrder = $concerns->first()?->repairOrder;
            if (! $repairOrder instanceof RepairOrder) {
                continue;
            }

            $increased = $this->authorization->approvedSubtotalsBeforeIncrease($repairOrder);

            foreach ($concerns as $concern) {
                foreach ($concern->lines as $line) {
                    if (! $line->type->isLabor()) {
                        continue;
                    }

                    $covered = $this->authorization->lineCovered($line);
                    $hasIncrease = isset($increased[(int) $line->id]);

                    if ($covered && ! $hasIncrease) {
                        continue;
                    }

                    $rows->push($this->row(
                        $line,
                        'completed_labor',
                        $this->authorization->pendingApprovalCents($repairOrder, $line),
                    ));
                }
            }
        }

        return $rows->values();
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
            'pending_approval_cents' => max(0, $pendingApprovalCents),
        ];
    }
}
