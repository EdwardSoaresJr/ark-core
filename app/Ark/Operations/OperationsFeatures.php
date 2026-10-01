<?php

namespace App\Ark\Operations;

use App\Ark\Operations\Settings\ShopSettings;

final class OperationsFeatures
{
    public static function appointmentsEnabled(): bool
    {
        return ShopSettings::current()->appointmentsEnabled();
    }
}
