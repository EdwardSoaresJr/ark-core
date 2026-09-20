<?php

namespace App\Ark\Desk;

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionQueue;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Work\AdvisorTask;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Platform\ShopBaseUrl;
use App\Ark\Station\StationAttentionProjection;
use App\Models\User;

final class DeskWorkProjection
{
    public function __construct(
        private readonly CallSessionQueue $queue,
        private readonly StationAttentionProjection $attention,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $workstation = Workstation::query()
            ->where('current_operator_user_id', $user->id)
            ->first();

        $tasks = AdvisorTask::query()
            ->whereNull('completed_at')
            ->where('assigned_user_id', $user->id)
            ->with(['repairOrder.vehicle', 'customer', 'callSession'])
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->orderBy('id')
            ->get()
            ->map(fn (AdvisorTask $task): array => $this->taskCard($task))
            ->all();

        $waiting = $this->queue->waitingSessions();
        $mine = [];
        $unclaimed = [];
        $interruptions = [];

        foreach ($waiting as $session) {
            $card = $this->callCard($session);
            $mineOwned = (int) $session->owned_by_user_id === (int) $user->id;
            $unowned = $session->owned_by_user_id === null;
            if ($mineOwned) {
                $mine[] = $card;
            } elseif ($unowned) {
                $unclaimed[] = $card;
            }
            if ($mineOwned || ($unowned && $session->status === CallSessionStatus::Ringing)) {
                $interruptions[] = $card;
            }
        }

        $attention = $this->attention->payload();
        $shop = $attention['shop_summary'];
        $comingIn = $attention['coming_in'] ?? [];
        $next = [];
        foreach (array_slice(is_array($comingIn) ? $comingIn : [], 0, 6) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $next[] = [
                'kind' => 'appointment',
                'title' => $row['customer_label'] ?? 'Coming in',
                'when' => $row['time_label'] ?? null,
                'vehicle_label' => $row['vehicle_label'] ?? null,
            ];
        }

        return [
            'surface' => 'ark_desk',
            'shop' => $this->shopContext(),
            'advisor' => [
                'id' => $user->id,
                'name' => $user->name,
                'accent' => $user->accentHexResolved(),
                'display_mode' => $user->displayTheme()->value,
            ],
            'workstation' => $workstation === null ? null : [
                'id' => $workstation->id,
                'name' => $workstation->displayLocation(),
            ],
            'stations' => $this->stations($user),
            'my_work' => array_values([...$mine, ...$tasks]),
            'unclaimed' => $unclaimed,
            'interruptions' => $interruptions,
            'next' => $next,
            'shop_pulse' => [
                'waiting_approval_amount_label' => $shop['waiting_approval_amount_label'] ?? $shop['waiting_approval_amount'] ?? null,
                'waiting_approval_count' => $shop['waiting_approval'] ?? 0,
                'unassigned_count' => $shop['unassigned'] ?? 0,
                'coming_in_count' => $shop['coming_in'] ?? 0,
                'open_count' => $shop['open'] ?? 0,
            ],
            'health' => [
                'ark' => 'ok',
                'dragon' => config('dragon.provider') === 'fake' ? 'ok' : 'ok',
            ],
        ];
    }

    /**
     * @return array{name: string, host: string, origin: string}
     */
    public function shopContext(): array
    {
        $name = trim((string) (ShopSettings::current()->shop_name ?? ''));

        return [
            'name' => $name !== '' ? $name : ShopBaseUrl::host(),
            'host' => ShopBaseUrl::host(),
            'origin' => ShopBaseUrl::origin(),
        ];
    }

    /**
     * @return list<array{id: int, name: string, occupied_by_user_id: int|null, mine: bool}>
     */
    public function stations(User $user): array
    {
        return Workstation::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Workstation $station): array => [
                'id' => $station->id,
                'name' => $station->displayLocation(),
                'occupied_by_user_id' => $station->current_operator_user_id,
                'mine' => (int) $station->current_operator_user_id === (int) $user->id,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function taskCard(AdvisorTask $task): array
    {
        return [
            'kind' => 'task',
            'id' => $task->id,
            'title' => $task->notes,
            'due_at' => $task->due_at?->toIso8601String(),
            'customer_name' => $task->customer?->name,
            'repair_order_id' => $task->repairOrder?->repair_order_id,
            'vehicle_label' => $task->repairOrder?->vehicle?->display_name,
            'call_session_id' => $task->call_session_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function callCard(CallSession $session): array
    {
        return [
            'kind' => 'call',
            'id' => $session->id,
            'call_session_id' => $session->id,
            'status' => $session->status->value,
            'status_label' => $session->status->operationalLabel(),
            'owned_by_user_id' => $session->owned_by_user_id,
            'from' => $session->from_number,
            'customer_name' => $session->customer?->name,
            'started_at' => $session->started_at?->toIso8601String(),
            'age_label' => $session->started_at?->diffForHumans(),
        ];
    }
}
