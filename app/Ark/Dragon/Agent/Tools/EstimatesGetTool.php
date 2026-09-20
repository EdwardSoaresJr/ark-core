<?php

namespace App\Ark\Dragon\Agent\Tools;

use App\Ark\Dragon\Agent\DragonAgentTool;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderEstimate;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\EstimateCompanionCompletenessProjection;

final class EstimatesGetTool implements DragonAgentTool
{
    public function __construct(private readonly RepairOrderEstimate $estimates) {}

    public function name(): string
    {
        return 'estimates.get';
    }

    public function description(): string
    {
        return 'Read estimate structure for a repair order: line types, descriptions, hours, totals, approval/status, and check_before_sending (shop companion catalog — what usually rides with this job). Use before critiquing or sending. No customer identity, no payment records, no writes.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'repair_order_id' => ['type' => 'string'],
            ],
            'required' => ['repair_order_id'],
        ];
    }

    public function invoke(array $arguments): array
    {
        $id = trim((string) ($arguments['repair_order_id'] ?? ''));
        $repairOrder = RepairOrder::query()
            ->with(['vehicle:id,year,make,model,trim', 'lines', 'concerns'])
            ->where('repair_order_id', $id)
            ->first();

        if ($repairOrder === null) {
            return ['ok' => false, 'error' => 'Repair order not found.'];
        }

        $totals = $this->estimates->totalsFor($repairOrder);

        $lines = $repairOrder->lines->map(function (RepairOrderLine $line) use ($totals): array {
            return [
                'type' => $line->type->value,
                'description' => $line->description,
                'customer_description' => $line->customer_description,
                'quantity' => $line->quantity,
                'labor_entered_hours' => $line->labor_entered_hours,
                'labor_billed_hours' => $line->labor_billed_hours,
                'line_total' => $totals->format((int) $line->total_cents),
            ];
        })->values()->all();

        $concerns = $repairOrder->concerns->map(fn ($concern): array => [
            'summary' => $concern->summary,
            'customer_states' => $concern->customer_states,
            'verified_findings' => $concern->verified_findings,
            'recommendation' => $concern->recommendation,
            'dtcs_summary' => $concern->dtcs_summary,
        ])->values()->all();

        $vehicle = $repairOrder->vehicle;

        return [
            'ok' => true,
            'read_only' => true,
            'repair_order_id' => $repairOrder->repair_order_id,
            'status' => $repairOrder->status->value,
            'status_label' => $repairOrder->status->label(),
            'vehicle' => trim(implode(' ', array_filter([
                $vehicle?->year,
                $vehicle?->make,
                $vehicle?->model,
            ], fn ($part): bool => filled($part)))),
            'concerns' => $concerns,
            'lines' => $lines,
            'totals' => [
                'labor' => $totals->format($totals->laborCents()),
                'parts' => $totals->format($totals->partsCents()),
                'fees' => $totals->format($totals->feesCents()),
                'tax' => $totals->format($totals->taxCents()),
                'total' => $totals->format($totals->totalCents()),
            ],
            'check_before_sending' => (new EstimateCompanionCompletenessProjection)->for($repairOrder),
            'note' => 'Proposal-only. Dragon must not apply estimate writes. If check_before_sending.needs_attention is true, say so in Check before sending. Companions come from this shop’s catalog (what usually rides with this job), not a hardcoded job list.',
        ];
    }
}
