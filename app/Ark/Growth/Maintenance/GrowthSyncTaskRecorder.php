<?php

namespace App\Ark\Growth\Maintenance;

use App\Ark\Growth\Models\GrowthSyncTask;

final class GrowthSyncTaskRecorder
{
    public function markRunning(GrowthSyncTaskKey $key): void
    {
        GrowthSyncTask::query()->updateOrCreate(
            ['task_key' => $key->value],
            [
                'status' => GrowthSyncTaskStatus::Running,
                'last_ran_at' => now(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function recordSuccess(GrowthSyncTaskKey $key, string $message, array $metadata = []): void
    {
        GrowthSyncTask::query()->updateOrCreate(
            ['task_key' => $key->value],
            [
                'status' => GrowthSyncTaskStatus::Success,
                'last_ran_at' => now(),
                'last_message' => $message,
                'last_metadata' => $metadata,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function recordSkipped(GrowthSyncTaskKey $key, string $message, array $metadata = []): void
    {
        GrowthSyncTask::query()->updateOrCreate(
            ['task_key' => $key->value],
            [
                'status' => GrowthSyncTaskStatus::Skipped,
                'last_ran_at' => now(),
                'last_message' => $message,
                'last_metadata' => $metadata,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function recordFailure(GrowthSyncTaskKey $key, string $message, array $metadata = []): void
    {
        GrowthSyncTask::query()->updateOrCreate(
            ['task_key' => $key->value],
            [
                'status' => GrowthSyncTaskStatus::Failed,
                'last_ran_at' => now(),
                'last_message' => $message,
                'last_metadata' => $metadata,
            ],
        );
    }

    public function lastRun(GrowthSyncTaskKey $key): ?GrowthSyncTask
    {
        return GrowthSyncTask::query()->find($key->value);
    }

    public function isStale(GrowthSyncTaskKey $key, int $maxAgeHours): bool
    {
        $task = $this->lastRun($key);

        if ($task === null || $task->last_ran_at === null) {
            return true;
        }

        return $task->last_ran_at->lt(now()->subHours($maxAgeHours));
    }
}
