<?php

namespace App\Ark\Operations\Workstations;

use App\Ark\Operations\Settings\ShopSettings;

final class StoreWorkstationAction
{
    public function execute(
        string $name,
        ?string $locationLabel = null,
        bool $acceptsScheduledWork = false,
    ): Workstation {
        return Workstation::query()->create([
            'shop_settings_id' => ShopSettings::reloadCurrent()->id,
            'name' => trim($name),
            'location_label' => filled($locationLabel) ? trim((string) $locationLabel) : null,
            'is_active' => true,
            'accepts_scheduled_work' => $acceptsScheduledWork,
        ]);
    }
}
