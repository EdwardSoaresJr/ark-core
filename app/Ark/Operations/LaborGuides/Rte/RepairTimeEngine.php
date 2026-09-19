<?php

namespace App\Ark\Operations\LaborGuides\Rte;

final class RepairTimeEngine
{
    public const NAME = 'Repair Time Engine';

    public static function buttonTooltip(): string
    {
        return 'Search labor times for this vehicle';
    }

    public static function panelTitle(): string
    {
        return 'Labor times';
    }
}
