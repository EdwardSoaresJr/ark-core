<?php

namespace App\Ark\Operations\Workboard;

use App\Ark\Operations\RepairOrders\RepairOrderStatus;

final class WorkboardQueueCatalog
{
    public const WORKSPACE_CARD_LIMIT = 25;

    public const CONCERN_HEADLINE_LIMIT = 48;

    public const COUNT_WARN_AGE_MINUTES = 24 * 60;

    public const COUNT_ALERT_AGE_MINUTES = 7 * 24 * 60;

    public const NEEDS_ATTENTION_QUEUE = 'needs_attention';

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function communicationQueues(): array
    {
        return [
            ['key' => 'customer_waiting', 'label' => 'Customer Waiting'],
            ['key' => 'waiting_approval', 'label' => 'Waiting Approval'],
            ['key' => 'unassigned', 'label' => 'Unassigned'],
            ['key' => 'overdue_pickup', 'label' => 'Overdue Pickup'],
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function incomingNavLanes(): array
    {
        return [
            ['key' => 'needs_diagnosis', 'label' => 'Needs Diagnosis'],
            ['key' => 'building_estimate', 'label' => 'Building Estimate'],
        ];
    }

    /**
     * @return list<array{key: string, label: string, section: string}>
     */
    public static function navQueueDefinitions(): array
    {
        return [
            ...array_map(
                fn (array $queue): array => [...$queue, 'section' => 'communication'],
                self::communicationQueues(),
            ),
            ...array_map(
                fn (array $lane): array => [...$lane, 'section' => 'incoming'],
                self::incomingNavLanes(),
            ),
            ...array_map(
                fn (array $lane): array => [...$lane, 'section' => 'active'],
                self::activeNavLanes(),
            ),
            ...array_map(
                fn (array $lane): array => [...$lane, 'section' => 'outgoing'],
                self::outgoingNavLanes(),
            ),
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function activeNavLanes(): array
    {
        return [
            ['key' => 'waiting_parts', 'label' => 'Waiting Parts'],
            ['key' => 'shop_floor', 'label' => 'Shop Floor'],
            ['key' => 'quality_check', 'label' => 'Quality Check'],
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function outgoingNavLanes(): array
    {
        return [
            ['key' => 'ready_pickup', 'label' => 'Ready Pickup'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function navSectionLabels(): array
    {
        return [
            'communication' => 'Communication',
            'incoming' => 'Incoming',
            'active' => 'Active',
            'outgoing' => 'Outgoing',
        ];
    }

    public static function resolveExplicitQueue(?string $queue, ?string $focus): ?string
    {
        if ($queue !== null && $queue !== '') {
            return self::isValidQueueKey($queue) ? $queue : null;
        }

        if ($focus === 'attention') {
            return self::NEEDS_ATTENTION_QUEUE;
        }

        return null;
    }

    /**
     * @param  array{
     *     lanes: array<string, int>,
     *     needs_attention: int,
     *     customer_waiting: int,
     *     unassigned: int,
     *     overdue_pickup: int,
     *     total: int
     * }  $navCounts
     */
    public static function defaultQueueFromCounts(array $navCounts): ?string
    {
        foreach (['customer_waiting', 'waiting_approval'] as $queueKey) {
            if (self::queueCountFromNavCounts($navCounts, $queueKey) > 0) {
                return $queueKey;
            }
        }

        foreach (self::navQueueDefinitions() as $queue) {
            if (self::queueCountFromNavCounts($navCounts, $queue['key']) > 0) {
                return $queue['key'];
            }
        }

        return null;
    }

    /**
     * @param  array{
     *     lanes: array<string, int>,
     *     needs_attention: int,
     *     customer_waiting: int,
     *     unassigned: int,
     *     overdue_pickup: int,
     *     oldest_age_by_queue?: array<string, int>,
     *     total: int
     * }  $navCounts
     */
    public static function needsAttentionRollupUrl(array $navCounts): ?string
    {
        if (($navCounts['needs_attention'] ?? 0) === 0) {
            return null;
        }

        return self::queueUrl(self::NEEDS_ATTENTION_QUEUE);
    }

    /**
     * @param  array{
     *     lanes: array<string, int>,
     *     needs_attention: int,
     *     customer_waiting: int,
     *     unassigned: int,
     *     overdue_pickup: int,
     *     total: int
     * }  $navCounts
     */
    public static function queueCountFromNavCounts(array $navCounts, string $queueKey): int
    {
        return match ($queueKey) {
            'customer_waiting' => $navCounts['customer_waiting'],
            'unassigned' => $navCounts['unassigned'],
            'overdue_pickup' => $navCounts['overdue_pickup'],
            default => $navCounts['lanes'][$queueKey] ?? 0,
        };
    }

    public static function isValidQueueKey(string $key): bool
    {
        if ($key === self::NEEDS_ATTENTION_QUEUE) {
            return true;
        }

        if (self::isCommunicationQueue($key)) {
            return true;
        }

        foreach (WorkboardSwimlaneCatalog::advisorSwimlanes() as $swimlane) {
            foreach ($swimlane['lanes'] as $lane) {
                if ($lane['key'] === $key) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function isNeedsAttentionQueue(string $key): bool
    {
        return $key === self::NEEDS_ATTENTION_QUEUE;
    }

    public static function isCommunicationQueue(string $key): bool
    {
        return in_array($key, ['customer_waiting', 'unassigned', 'overdue_pickup'], true);
    }

    public static function isLaneQueue(string $key): bool
    {
        return self::labelForQueue($key) !== null && ! self::isCommunicationQueue($key);
    }

    public static function labelForQueue(string $key): ?string
    {
        if ($key === self::NEEDS_ATTENTION_QUEUE) {
            return 'Needs Attention';
        }

        foreach (self::navQueueDefinitions() as $queue) {
            if ($queue['key'] === $key) {
                return $queue['label'];
            }
        }

        return null;
    }

    public static function queueUrl(string $key): string
    {
        return route('operations.index', ['queue' => $key]);
    }

    public static function inventoryUrlForNeedsAttentionQueue(): ?string
    {
        return WorkboardAttentionInventoryQuery::inventoryUrl('needs_attention');
    }

    public static function inventoryUrlForCommunicationQueue(string $key): ?string
    {
        return match ($key) {
            'customer_waiting' => WorkboardAttentionInventoryQuery::inventoryUrl('customer_waiting'),
            'unassigned' => route('operations.repair-orders.index', ['unassigned' => '1']),
            'overdue_pickup' => route('operations.repair-orders.index', [
                'status' => RepairOrderStatus::ReadyPickup->value,
                'pickup' => 'stale',
            ]),
            default => null,
        };
    }

    public static function countSeverityForQueue(string $queueKey, int $count, int $oldestAgeMinutes): string
    {
        if ($count === 0) {
            return 'idle';
        }

        return match ($queueKey) {
            'overdue_pickup' => $oldestAgeMinutes >= self::COUNT_ALERT_AGE_MINUTES ? 'alert' : (
                $oldestAgeMinutes >= self::COUNT_WARN_AGE_MINUTES ? 'warn' : 'neutral'
            ),
            'customer_waiting', 'waiting_approval', 'unassigned' => $oldestAgeMinutes >= self::COUNT_WARN_AGE_MINUTES ? 'warn' : 'neutral',
            default => 'neutral',
        };
    }

    /** @deprecated Use resolveExplicitQueue() */
    public static function resolveSelectedQueue(?string $queue, ?string $focus): ?string
    {
        return self::resolveExplicitQueue($queue, $focus);
    }

    /** @deprecated Use communicationQueues() */
    public static function attentionQueues(): array
    {
        return self::communicationQueues();
    }

    /** @deprecated Use isCommunicationQueue() */
    public static function isAttentionQueue(string $key): bool
    {
        return self::isCommunicationQueue($key);
    }

    /** @deprecated Use navSectionLabels() */
    public static function swimlaneSectionLabels(): array
    {
        return self::navSectionLabels();
    }

    /** @deprecated Use inventoryUrlForCommunicationQueue() */
    public static function inventoryUrlForAttentionQueue(string $key): ?string
    {
        return match ($key) {
            'needs_attention', 'customer_waiting' => WorkboardAttentionInventoryQuery::inventoryUrl(
                $key === 'needs_attention' ? 'needs_attention' : 'customer_waiting',
            ),
            default => self::inventoryUrlForCommunicationQueue($key),
        };
    }
}
