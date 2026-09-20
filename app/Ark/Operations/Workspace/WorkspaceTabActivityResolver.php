<?php

namespace App\Ark\Operations\Workspace;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;

/**
 * Operational activity signals for workspace tabs (changed, attention, stale).
 */
final class WorkspaceTabActivityResolver
{
    /**
     * @param  array<string, mixed>|null  $seen
     * @return array<string, mixed>|null
     */
    public function resolve(string $entityType, string $entityId, ?array $seen = null): ?array
    {
        return match ($entityType) {
            'repair_order' => $this->repairOrderActivity($entityId, $seen ?? []),
            default => null,
        };
    }

    /**
     * @return list<array{key: string, activity: array<string, mixed>}>
     */
    public function resolveMany(string $activeKey, array $tabs): array
    {
        $patches = [];

        foreach ($tabs as $tab) {
            $key = (string) ($tab['key'] ?? '');
            if ($key === '' || $key === $activeKey) {
                continue;
            }

            if (! str_contains($key, ':')) {
                continue;
            }

            [$entityType, $entityId] = explode(':', $key, 2);
            if ($entityType === '' || $entityId === '') {
                continue;
            }

            $activity = $this->resolve($entityType, $entityId, is_array($tab['seen'] ?? null) ? $tab['seen'] : null);
            if ($activity === null) {
                continue;
            }

            $patches[] = [
                'key' => $key,
                'activity' => $activity,
            ];
        }

        return $patches;
    }

    /**
     * @return array<string, mixed>
     */
    public static function repairOrderSignals(RepairOrder $repairOrder): array
    {
        return [
            'estimateVersion' => (int) $repairOrder->estimate_version,
        ];
    }

    /**
     * @param  array<string, mixed>  $seen
     * @return array<string, mixed>|null
     */
    private function repairOrderActivity(string $shopNumber, array $seen): ?array
    {
        $repairOrder = RepairOrder::query()
            ->where('repair_order_id', (int) $shopNumber)
            ->first();

        if ($repairOrder === null) {
            return null;
        }

        $activity = [];
        $unread = 0;
        $seenVersion = (int) ($seen['estimateVersion'] ?? 0);
        $currentVersion = (int) $repairOrder->estimate_version;

        if ($seenVersion > 0 && $currentVersion > $seenVersion) {
            $unread += min(2, $currentVersion - $seenVersion);
        }

        if ($repairOrder->status->is(RepairOrderStatus::WaitingApproval)) {
            $activity['urgency'] = 'medium';
            if ($unread === 0) {
                $unread = 1;
            }
        }

        if ($unread > 0) {
            $activity['unread'] = min(9, $unread);
        }

        return $activity === [] ? null : $activity;
    }
}
