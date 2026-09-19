<?php

namespace App\Ark\Operations\Workboard;

use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor;

final class JobBoardLaneCatalogDefaults
{
    public const ESTIMATES = 'estimates';

    public const WAITING_APPROVAL = 'waiting_approval';

    public const PARTS = 'parts';

    public const WORK_IN_PROGRESS = 'work_in_progress';

    public const COMPLETED = 'completed';

    /**
     * @return list<array{key: string, name: string, color: string, sort_order: int, active: bool, is_system: bool}>
     */
    public static function lanes(): array
    {
        return [
            [
                'key' => self::ESTIMATES,
                'name' => 'Estimates',
                'color' => RepairOrderStatusColor::SECONDARY,
                'sort_order' => 0,
                'active' => true,
                'is_system' => true,
            ],
            [
                'key' => self::WAITING_APPROVAL,
                'name' => 'Waiting Approval',
                'color' => RepairOrderStatusColor::WARNING,
                'sort_order' => 1,
                'active' => true,
                'is_system' => true,
            ],
            [
                'key' => self::PARTS,
                'name' => 'Waiting Parts',
                'color' => RepairOrderStatusColor::INFO,
                'sort_order' => 2,
                'active' => true,
                'is_system' => true,
            ],
            [
                'key' => self::WORK_IN_PROGRESS,
                'name' => 'Work in Progress',
                'color' => RepairOrderStatusColor::PRIMARY,
                'sort_order' => 3,
                'active' => true,
                'is_system' => true,
            ],
            [
                'key' => self::COMPLETED,
                'name' => 'Completed',
                'color' => RepairOrderStatusColor::SUCCESS,
                'sort_order' => 4,
                'active' => true,
                'is_system' => true,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(self::lanes(), 'key');
    }

    /**
     * Map a catalog / swimlane key onto the default five home lanes.
     */
    public static function homeLaneKey(?string $laneKey, ?string $statusSlug = null): ?string
    {
        if ($statusSlug !== null && in_array($statusSlug, [
            RepairOrderStatus::Draft->value,
            RepairOrderStatus::Estimate->value,
        ], true)) {
            return self::ESTIMATES;
        }

        if ($statusSlug !== null && in_array($statusSlug, [
            RepairOrderStatus::Closed->value,
        ], true)) {
            return null;
        }

        return match ($laneKey) {
            self::ESTIMATES,
            'needs_diagnosis',
            'building_estimate',
            'new_arrivals_intake' => self::ESTIMATES,
            self::WAITING_APPROVAL => self::WAITING_APPROVAL,
            self::PARTS,
            'waiting_parts' => self::PARTS,
            self::WORK_IN_PROGRESS,
            'shop_floor',
            'quality_check',
            'work_in_progress' => self::WORK_IN_PROGRESS,
            self::COMPLETED,
            'ready_pickup',
            'finalizing-and-pickup',
            'completed' => self::COMPLETED,
            default => $laneKey,
        };
    }

    public static function defaultTone(string $color): string
    {
        return RepairOrderStatusColor::chipTone($color);
    }

    public static function sync(): void
    {
        foreach (self::lanes() as $lane) {
            JobBoardLane::query()->updateOrCreate(
                ['key' => $lane['key']],
                $lane,
            );
        }
    }
}
