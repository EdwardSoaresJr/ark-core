<?php

namespace App\Ark\Station;

use App\Ark\Dragon\DragonWorkProjection;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Work\AdvisorTask;

final class StationGlassRepairOrderProjection
{
    public function __construct(
        private readonly DragonWorkProjection $work,
        private readonly EstimateTotalsCalculator $totals,
        private readonly StationAttentionProjection $attention,
        private readonly StationGlassTasksProjection $tasks,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function forShopNumber(string $shopNumber): ?array
    {
        $card = $this->work->repairOrder($shopNumber, includeCustomerLabel: true);
        if ($card === null) {
            return null;
        }

        /** @var RepairOrder|null $repairOrder */
        $repairOrder = RepairOrder::query()
            ->with(['vehicle', 'customer', 'assignedTechnician'])
            ->where('repair_order_id', $shopNumber)
            ->first();

        if ($repairOrder === null) {
            return $this->clean($card);
        }

        $totals = $this->totals->totalsFor($repairOrder);
        $attentionPayload = $this->attention->payload();
        $attentionRow = collect($attentionPayload['rows'] ?? [])
            ->firstWhere('repair_order_id', $repairOrder->repair_order_id);

        $visitReason = trim((string) ($repairOrder->visit_reason ?? ''));
        $concern = $card['concern_summary'] ?? null;

        $todos = AdvisorTask::query()
            ->whereNull('completed_at')
            ->where('repair_order_id', $repairOrder->id)
            ->with(['assignedUser'])
            ->orderBy('id')
            ->get()
            ->map(fn (AdvisorTask $task): array => $this->tasks->present($task))
            ->all();

        $ageDays = $repairOrder->displayOpenedAt()->diffInDays(now());

        return $this->clean([
            ...$card,
            'visit_reason' => $visitReason !== '' ? $visitReason : null,
            'concern_summary' => filled($concern) ? $concern : null,
            'estimate_total_label' => '$'.number_format($totals->totalCents() / 100, 2),
            'approval_state' => $repairOrder->status->is(RepairOrderStatus::WaitingApproval) ? 'Waiting approval' : $repairOrder->status->label(),
            'waiting_approval_amount_label' => is_array($attentionRow) ? ($attentionRow['waiting_approval_amount_label'] ?? null) : null,
            'attention_reason' => is_array($attentionRow) ? ($attentionRow['attention_reason'] ?? null) : null,
            'appointment_summary' => is_array($attentionRow) ? ($attentionRow['appointment_summary'] ?? null) : null,
            'next_action' => $card['next_action'] ?? (is_array($attentionRow) ? ($attentionRow['next_action'] ?? null) : null),
            'age_days' => $ageDays,
            'related_todos' => $todos === [] ? null : $todos,
        ]);
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    private function clean(array $card): array
    {
        unset($card['opened_at_field'], $card['open_in_ark_url']);

        foreach ($card as $key => $value) {
            if ($value === null || $value === '' || $value === 'null') {
                unset($card[$key]);
            }
        }

        return $card;
    }
}
