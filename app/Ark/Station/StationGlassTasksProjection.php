<?php

namespace App\Ark\Station;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Work\AdvisorTask;
use App\Models\User;
use Illuminate\Support\Carbon;

final class StationGlassTasksProjection
{
    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function payload(array $config): array
    {
        $mode = $config['advisor_mode'] ?? 'two';
        $primaryId = $config['primary_advisor_user_id'] ?? null;
        $secondaryId = $config['secondary_advisor_user_id'] ?? null;

        $open = AdvisorTask::query()
            ->whereNull('completed_at')
            ->with(['assignedUser', 'repairOrder.vehicle', 'customer', 'callSession'])
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->orderBy('id')
            ->get();

        $present = fn (AdvisorTask $task): array => $this->present($task);

        $lanes = [];
        if (is_int($primaryId)) {
            $lanes[] = $this->lane($primaryId, $config['eligible_advisors'] ?? [], $open->where('assigned_user_id', $primaryId)->values()->map($present)->all());
        }
        if ($mode === 'two' && is_int($secondaryId)) {
            $lanes[] = $this->lane($secondaryId, $config['eligible_advisors'] ?? [], $open->where('assigned_user_id', $secondaryId)->values()->map($present)->all());
        }

        $shared = $open->whereNull('assigned_user_id')->values()->map($present)->all();

        return [
            'mode' => $mode,
            'configured' => $primaryId !== null,
            'lanes' => $lanes,
            'shared' => $shared,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $eligible
     * @param  list<array<string, mixed>>  $tasks
     * @return array<string, mixed>
     */
    private function lane(int $userId, array $eligible, array $tasks): array
    {
        $advisor = collect($eligible)->firstWhere('id', $userId) ?? [
            'id' => $userId,
            'name' => User::query()->find($userId)?->name ?? 'Advisor',
            'accent' => '#0099cc',
        ];

        return [
            'user_id' => $advisor['id'],
            'name' => $advisor['name'],
            'accent' => $advisor['accent'] ?? '#0099cc',
            'tasks' => $tasks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function present(AdvisorTask $task): array
    {
        $repairOrder = $task->repairOrder;
        $due = $task->due_at instanceof Carbon ? $task->due_at : null;
        $overdue = $due !== null && $due->lt(now());

        return [
            'id' => $task->id,
            'title' => $task->notes,
            'assigned_user_id' => $task->assigned_user_id,
            'repair_order_id' => $repairOrder?->repair_order_id,
            'call_session_id' => $task->call_session_id,
            'due_at' => $due?->toIso8601String(),
            'overdue' => $overdue,
            'vehicle_label' => $repairOrder?->vehicle?->display_name,
        ];
    }

    public function complete(AdvisorTask $task): AdvisorTask
    {
        if ($task->completed_at === null) {
            $task->forceFill([
                'completed_at' => now(),
                'completed_by_user_id' => $task->assigned_user_id,
            ])->save();
        }

        return $task->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function claimOrCreate(array $data, ?int $createdByUserId): AdvisorTask
    {
        $callId = isset($data['call_session_id']) ? (int) $data['call_session_id'] : 0;
        if ($callId > 0) {
            $existing = AdvisorTask::query()
                ->whereNull('completed_at')
                ->where('call_session_id', $callId)
                ->first();
            if ($existing !== null) {
                $existing->forceFill([
                    'assigned_user_id' => $data['assigned_user_id'] ?? $existing->assigned_user_id,
                    'notes' => $data['title'] ?? $existing->notes,
                ])->save();

                return $existing->fresh(['repairOrder.vehicle', 'assignedUser']) ?? $existing;
            }
        }

        return $this->create($data, $createdByUserId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $createdByUserId): AdvisorTask
    {
        $repairOrderId = null;
        if (isset($data['repair_order_id'])) {
            $repairOrderId = RepairOrder::query()
                ->where('repair_order_id', $data['repair_order_id'])
                ->value('id');
        }

        return AdvisorTask::query()->create([
            'notes' => $data['title'],
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'created_by_user_id' => $createdByUserId ?? $data['assigned_user_id'] ?? $this->fallbackCreatorId(),
            'repair_order_id' => $repairOrderId,
            'call_session_id' => $data['call_session_id'] ?? null,
            'due_at' => isset($data['due_at']) ? Carbon::parse($data['due_at']) : now()->addDay(),
        ]);
    }

    private function fallbackCreatorId(): int
    {
        $id = User::query()->role(['admin', 'advisor'])->orderBy('id')->value('id');

        if (! is_int($id)) {
            $id = (int) User::query()->orderBy('id')->value('id');
        }

        return $id;
    }
}
