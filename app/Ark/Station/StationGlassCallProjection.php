<?php

namespace App\Ark\Station;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\Projections\CallSessionCallerContextProjection;
use App\Ark\Operations\Work\AdvisorTask;

final class StationGlassCallProjection
{
    public function __construct(
        private readonly StationCallsProjection $calls,
        private readonly CallSessionCallerContextProjection $caller,
        private readonly StationGlassTasksProjection $tasks,
        private readonly EstimateTotalsCalculator $totals,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function forId(int $id): ?array
    {
        $session = CallSession::query()
            ->with(['customer', 'owner', 'repairOrder.vehicle', 'repairOrder.concerns'])
            ->find($id);
        if ($session === null) {
            return null;
        }

        $row = $this->calls->present($session);
        $context = $this->caller->forSession($session);
        unset($context['provider'], $context['provider_call_sid'], $context['incoming_call_context']);

        $todos = AdvisorTask::query()
            ->whereNull('completed_at')
            ->where(function ($query) use ($session): void {
                $query->where('call_session_id', $session->id);
                if ($session->repair_order_id !== null) {
                    $query->orWhere('repair_order_id', $session->repair_order_id);
                }
            })
            ->with(['assignedUser', 'repairOrder.vehicle'])
            ->limit(8)
            ->get()
            ->map(fn (AdvisorTask $task): array => $this->tasks->present($task))
            ->all();

        $history = CallSession::query()
            ->with(['customer', 'repairOrder.vehicle'])
            ->excludingFeatureCodeDials()
            ->when(
                $session->customer_id !== null,
                fn ($query) => $query->where('customer_id', $session->customer_id),
                fn ($query) => $query->where('normalized_from', $session->normalized_from),
            )
            ->where('id', '!=', $session->id)
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (CallSession $other): array => $this->calls->present($other))
            ->all();

        $vehicles = [];
        foreach ($context['open_repair_orders'] ?? [] as $open) {
            if (! is_array($open)) {
                continue;
            }
            $label = $open['vehicle']['display_name'] ?? $open['vehicle_label'] ?? null;
            if (is_string($label) && $label !== '' && ! in_array($label, $vehicles, true)) {
                $vehicles[] = $label;
            }
        }
        if ($row['vehicle_label'] !== null && ! in_array($row['vehicle_label'], $vehicles, true)) {
            $vehicles[] = $row['vehicle_label'];
        }

        $repairOrder = $session->repairOrder;
        $concern = $repairOrder?->concerns?->sortBy('position')->first()?->summary;
        $estimate = null;
        $approval = null;
        if ($repairOrder !== null) {
            $estimate = '$'.number_format($this->totals->totalsFor($repairOrder)->totalCents() / 100, 2);
            $approval = $repairOrder->status->is(RepairOrderStatus::WaitingApproval)
                ? 'Waiting approval'
                : $repairOrder->status->label();
        }

        return [
            ...$row,
            'context' => $context,
            'vehicles' => $vehicles,
            'todos' => $todos,
            'history' => $history,
            'concern_summary' => filled($concern) ? trim((string) $concern) : null,
            'estimate_total_label' => $estimate,
            'approval_state' => $approval,
        ];
    }
}
