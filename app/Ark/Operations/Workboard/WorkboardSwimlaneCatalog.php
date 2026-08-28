<?php

namespace App\Ark\Operations\Workboard;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;

final class WorkboardSwimlaneCatalog
{
    public const VISIBLE_CARD_LIMIT = 3;

    public const PICKUP_RECENT_DAYS = 3;

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     lanes: list<array{
     *         key: string,
     *         label: string,
     *         tone: string,
     *         slugs: list<string>
     *     }>
     * }>
     */
    public static function advisorSwimlanes(): array
    {
        return [
            [
                'key' => 'incoming',
                'label' => 'Incoming Work',
                'lanes' => [
                    [
                        'key' => 'needs_diagnosis',
                        'label' => 'Needs Diagnosis',
                        'tone' => 'move',
                        'slugs' => [RepairOrderStatus::Draft->value],
                    ],
                    [
                        'key' => 'building_estimate',
                        'label' => 'Building Estimate',
                        'tone' => 'motion',
                        'slugs' => [RepairOrderStatus::Estimate->value],
                    ],
                    [
                        'key' => 'waiting_approval',
                        'label' => 'Waiting Approval',
                        'tone' => 'approval',
                        'slugs' => [RepairOrderStatus::WaitingApproval->value],
                    ],
                ],
            ],
            [
                'key' => 'active',
                'label' => 'Active Work',
                'lanes' => [
                    [
                        'key' => 'waiting_parts',
                        'label' => 'Waiting Parts',
                        'tone' => 'blocked',
                        'slugs' => [RepairOrderStatus::WaitingParts->value],
                    ],
                    [
                        'key' => 'shop_floor',
                        'label' => 'Shop Floor',
                        'tone' => 'motion',
                        'slugs' => [
                            RepairOrderStatus::Approved->value,
                            RepairOrderStatus::ReadyForWork->value,
                            RepairOrderStatus::InProgress->value,
                        ],
                    ],
                    [
                        'key' => 'quality_check',
                        'label' => 'Quality Check',
                        'tone' => 'ready',
                        'slugs' => [RepairOrderStatus::QualityCheck->value],
                    ],
                ],
            ],
            [
                'key' => 'outgoing',
                'label' => 'Outgoing Work',
                'lanes' => [
                    [
                        'key' => 'ready_pickup',
                        'label' => 'Ready Pickup',
                        'tone' => 'ready',
                        'slugs' => [
                            RepairOrderStatus::Completed->value,
                            RepairOrderStatus::Invoiced->value,
                            RepairOrderStatus::ReadyPickup->value,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function advisorTriageQueueSlugs(): array
    {
        return collect(self::advisorSwimlanes())
            ->flatMap(fn (array $swimlane): array => collect($swimlane['lanes'])
                ->flatMap(fn (array $lane): array => $lane['slugs'])
                ->all())
            ->unique()
            ->values()
            ->all();
    }

    public static function laneKeyForRepairOrder(RepairOrder $repairOrder): ?string
    {
        $slug = $repairOrder->workboardLaneStatus()->value;
        $laneKey = self::laneKeyMap()[$slug] ?? null;

        if ($laneKey !== null) {
            return $laneKey;
        }

        return match ($slug) {
            RepairOrderStatus::Draft->value => 'needs_diagnosis',
            RepairOrderStatus::Estimate->value => 'building_estimate',
            RepairOrderStatus::WaitingApproval->value => 'waiting_approval',
            RepairOrderStatus::WaitingParts->value => 'waiting_parts',
            RepairOrderStatus::Approved->value,
            RepairOrderStatus::ReadyForWork->value,
            RepairOrderStatus::InProgress->value => 'shop_floor',
            RepairOrderStatus::QualityCheck->value => 'quality_check',
            RepairOrderStatus::Completed->value,
            RepairOrderStatus::Invoiced->value,
            RepairOrderStatus::ReadyPickup->value => 'ready_pickup',
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    private static function laneKeyMap(): array
    {
        static $map = null;

        if ($map !== null) {
            return $map;
        }

        $map = [];

        foreach (self::advisorSwimlanes() as $swimlane) {
            foreach ($swimlane['lanes'] as $lane) {
                foreach ($lane['slugs'] as $slug) {
                    $map[$slug] = $lane['key'];
                }
            }
        }

        $catalog = app(RepairOrderStatusCatalog::class);

        if ($catalog->isBooted()) {
            foreach (self::advisorTriageQueueSlugs() as $slug) {
                $definition = $catalog->definitionForSlug($slug);

                if ($definition === null) {
                    continue;
                }

                $laneKey = $definition->advisor_lane_key ?? $slug;

                foreach (self::advisorSwimlanes() as $swimlane) {
                    foreach ($swimlane['lanes'] as $lane) {
                        if ($lane['key'] === $laneKey || in_array($slug, $lane['slugs'], true)) {
                            $map[$slug] = $lane['key'];

                            continue 3;
                        }
                    }
                }
            }
        }

        return $map;
    }

    public static function isOutgoingPickupSlug(string $slug): bool
    {
        return in_array($slug, [
            RepairOrderStatus::Completed->value,
            RepairOrderStatus::Invoiced->value,
            RepairOrderStatus::ReadyPickup->value,
        ], true);
    }

    /**
     * @return list<string>
     */
    public static function shopFloorSlugs(): array
    {
        return [
            RepairOrderStatus::Approved->value,
            RepairOrderStatus::ReadyForWork->value,
            RepairOrderStatus::InProgress->value,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function inventoryQueryForLane(string $laneKey): array
    {
        return match ($laneKey) {
            'needs_diagnosis' => ['status' => RepairOrderStatus::Draft->value],
            'building_estimate' => ['status' => RepairOrderStatus::Estimate->value],
            'waiting_approval' => ['status' => RepairOrderStatus::WaitingApproval->value],
            'waiting_parts' => ['status' => RepairOrderStatus::WaitingParts->value],
            'shop_floor' => ['lane' => 'shop_floor'],
            'quality_check' => ['status' => RepairOrderStatus::QualityCheck->value],
            'ready_pickup' => [
                'status' => RepairOrderStatus::ReadyPickup->value,
                'pickup' => 'all',
            ],
            default => [],
        };
    }

    public static function inventoryUrlForLane(string $laneKey): ?string
    {
        $query = self::inventoryQueryForLane($laneKey);

        if ($query === []) {
            return null;
        }

        return route('operations.repair-orders.index', $query);
    }

    public static function inventoryLabelForLane(string $laneKey): ?string
    {
        return match ($laneKey) {
            'needs_diagnosis' => 'Needs diagnosis queue',
            'building_estimate' => 'Building estimate queue',
            'waiting_approval' => 'Waiting approval queue',
            'waiting_parts' => 'Waiting parts queue',
            'shop_floor' => 'Shop floor queue',
            'quality_check' => 'Quality check queue',
            'ready_pickup' => 'Awaiting pickup queue',
            default => null,
        };
    }

    /**
     * Advisor home (/app) — five visible shop columns.
     *
     * @return list<array{key: string, label: string, tone: string}>
     */
    public static function advisorHomeBoardColumns(): array
    {
        return [
            ['key' => 'estimates', 'label' => 'Estimates', 'tone' => 'motion'],
            ['key' => 'waiting_approval', 'label' => 'Waiting Approval', 'tone' => 'approval'],
            ['key' => 'parts', 'label' => 'Waiting Parts', 'tone' => 'blocked'],
            ['key' => 'work_in_progress', 'label' => 'Work in Progress', 'tone' => 'motion'],
            ['key' => 'completed', 'label' => 'Completed', 'tone' => 'ready'],
        ];
    }

    public static function homeBoardColumnKeyForRepairOrder(RepairOrder $repairOrder): ?string
    {
        $laneKey = self::laneKeyForRepairOrder($repairOrder);

        return match ($laneKey) {
            'needs_diagnosis', 'building_estimate' => 'estimates',
            'waiting_approval' => 'waiting_approval',
            'waiting_parts' => 'parts',
            'shop_floor', 'quality_check' => 'work_in_progress',
            'ready_pickup' => 'completed',
            default => null,
        };
    }

    public static function inventoryUrlForHomeColumn(string $columnKey): ?string
    {
        return match ($columnKey) {
            'estimates' => route('operations.repair-orders.index', [
                'status' => RepairOrderStatus::Estimate->value,
            ]),
            'waiting_approval' => route('operations.repair-orders.index', [
                'status' => RepairOrderStatus::WaitingApproval->value,
            ]),
            'parts' => route('operations.repair-orders.index', [
                'status' => RepairOrderStatus::WaitingParts->value,
            ]),
            'work_in_progress' => route('operations.repair-orders.index', [
                'lane' => 'shop_floor',
            ]),
            'completed' => route('operations.repair-orders.index', [
                'status' => RepairOrderStatus::ReadyPickup->value,
                'pickup' => 'all',
            ]),
            default => null,
        };
    }
}
