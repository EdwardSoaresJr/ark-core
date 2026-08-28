<?php

namespace App\Ark\Operations\RepairOrders\Status;

/**
 * Catalog color tokens for RO statuses — shop-configurable in Workflow Defaults.
 * Maps to operational chip / card tones used on boards and indexes.
 */
final class RepairOrderStatusColor
{
    public const DARK = 'dark';

    public const SECONDARY = 'secondary';

    public const WARNING = 'warning';

    public const INFO = 'info';

    public const PRIMARY = 'primary';

    public const SUCCESS = 'success';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            self::DARK,
            self::SECONDARY,
            self::WARNING,
            self::INFO,
            self::PRIMARY,
            self::SUCCESS,
        ];
    }

    /**
     * @return list<array{key: string, label: string, swatch: string, chip_tone: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (string $key): array => [
                'key' => $key,
                'label' => self::label($key),
                'swatch' => self::swatch($key),
                'chip_tone' => self::chipTone($key),
            ],
            self::keys(),
        );
    }

    public static function normalize(?string $color): string
    {
        $color = strtolower(trim((string) $color));

        return in_array($color, self::keys(), true) ? $color : self::SECONDARY;
    }

    public static function label(?string $color): string
    {
        return match (self::normalize($color)) {
            self::DARK => 'Slate',
            self::SECONDARY => 'Neutral',
            self::WARNING => 'Waiting',
            self::INFO => 'Attention',
            self::PRIMARY => 'In motion',
            self::SUCCESS => 'Ready',
        };
    }

    /** CSS background for settings swatch preview. */
    public static function swatch(?string $color): string
    {
        return match (self::normalize($color)) {
            self::DARK => '#334155',
            self::SECONDARY => '#94a3b8',
            self::WARNING => '#f97316',
            self::INFO => '#f59e0b',
            self::PRIMARY => '#0ea5e9',
            self::SUCCESS => '#10b981',
        };
    }

    /** ops-status-chip / ops-ro-card tone. */
    public static function chipTone(?string $color): string
    {
        return match (self::normalize($color)) {
            self::WARNING => 'approval',
            self::INFO => 'blocked',
            self::PRIMARY => 'motion',
            self::SUCCESS => 'ready',
            self::DARK => 'closed',
            self::SECONDARY => 'move',
        };
    }

    /** Job-board / lifecycle control tone. */
    public static function boardTone(?string $color): string
    {
        return match (self::normalize($color)) {
            self::WARNING => 'warn',
            self::INFO => 'parts',
            self::PRIMARY => 'progress',
            self::SUCCESS => 'ready',
            default => 'neutral',
        };
    }
}
