<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Support\Collection;

final class UnresolvedAuthorizationReport
{
    public function __construct(
        private readonly WorkCompletionAuthorization $authorization,
    ) {}

    /**
     * Installed parts and completed labor that lack an approval scope or an
     * exception. Read-only. Does not change the repair order.
     *
     * @return Collection<int, array{repair_order_id: int, repair_order_number: int, concern_id: int, line_id: int, kind: string}>
     */
    public function rows(): Collection
    {
        $rows = collect();

        $installed = RepairOrderLine::query()
            ->with('concern')
            ->where('type', RepairOrderLineType::Part->value)
            ->where('procurement_state', PartProcurementState::Installed->value)
            ->get();

        foreach ($installed as $line) {
            if ($this->authorization->lineCovered($line)) {
                continue;
            }

            $rows->push($this->row($line, 'installed_part'));
        }

        $completedConcerns = RepairOrderConcern::query()
            ->with(['lines', 'repairOrder'])
            ->where('production_status', ScopeProductionStatus::Completed->value)
            ->get();

        foreach ($completedConcerns as $concern) {
            foreach ($concern->lines as $line) {
                if (! $line->type->isLabor() || $this->authorization->lineCovered($line)) {
                    continue;
                }

                $rows->push($this->row($line, 'completed_labor'));
            }
        }

        return $rows->values();
    }

    /**
     * @return array{repair_order_id: int, repair_order_number: int, concern_id: int, line_id: int, kind: string}
     */
    private function row(RepairOrderLine $line, string $kind): array
    {
        $line->loadMissing('repairOrder');

        return [
            'repair_order_id' => (int) $line->repair_order_id,
            'repair_order_number' => (int) ($line->repairOrder?->repair_order_id ?? 0),
            'concern_id' => (int) $line->repair_order_concern_id,
            'line_id' => (int) $line->id,
            'kind' => $kind,
        ];
    }
}
