<?php

namespace App\Ark\Growth\Maintenance;

use App\Ark\Growth\Models\GrowthSyncTask;

final class GrowthSyncStatusProjection
{
    public function __construct(
        private readonly GrowthSyncTaskRecorder $recorder,
    ) {}

    /**
     * @return array{
     *     tasks: list<array<string, mixed>>,
     *     has_failures: bool,
     *     last_pipeline_at: string|null
     * }
     */
    public function resolve(): array
    {
        $keys = [
            GrowthSyncTaskKey::SearchConsole,
            GrowthSyncTaskKey::GoogleBusinessProfile,
            GrowthSyncTaskKey::OpportunityQueue,
            GrowthSyncTaskKey::SeoAudit,
            GrowthSyncTaskKey::Briefing,
        ];

        $tasks = [];
        $hasFailures = false;
        $latest = null;

        foreach ($keys as $key) {
            $task = $this->recorder->lastRun($key);
            $presented = $this->present($key, $task);
            $tasks[] = $presented;

            if ($presented['status'] === GrowthSyncTaskStatus::Failed->value) {
                $hasFailures = true;
            }

            if ($task?->last_ran_at !== null && ($latest === null || $task->last_ran_at->gt($latest))) {
                $latest = $task->last_ran_at;
            }
        }

        return [
            'tasks' => $tasks,
            'has_failures' => $hasFailures,
            'last_pipeline_at' => $latest?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(GrowthSyncTaskKey $key, ?GrowthSyncTask $task): array
    {
        if ($task === null) {
            return [
                'key' => $key->value,
                'label' => $key->label(),
                'status' => 'pending',
                'status_label' => 'Pending',
                'healthy' => false,
                'last_ran_at' => null,
                'last_ran_label' => 'Not yet synchronized',
                'message' => 'ARK has not run this task yet. Nightly maintenance will synchronize automatically.',
            ];
        }

        $statusLabel = match ($task->status) {
            GrowthSyncTaskStatus::Success => 'Synchronized',
            GrowthSyncTaskStatus::Skipped => 'Skipped',
            GrowthSyncTaskStatus::Failed => 'Failed',
            GrowthSyncTaskStatus::Running => 'Running',
        };

        return [
            'key' => $key->value,
            'label' => $key->label(),
            'status' => $task->status->value,
            'status_label' => $statusLabel,
            'healthy' => $task->isHealthy(),
            'last_ran_at' => $task->last_ran_at?->toIso8601String(),
            'last_ran_label' => $task->last_ran_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? 'Never',
            'message' => $task->last_message,
        ];
    }
}
