<?php

namespace App\Ark\Operations\Workstations;

use App\Ark\Operations\Telephony\TelephonyExtension;

final class UpdateWorkstationAction
{
    public function execute(
        Workstation $workstation,
        string $name,
        ?string $locationLabel = null,
        ?bool $acceptsScheduledWork = null,
    ): Workstation {
        $label = trim($name);

        $attributes = [
            'name' => $label,
            'location_label' => filled($locationLabel) ? trim((string) $locationLabel) : null,
        ];

        if ($acceptsScheduledWork !== null) {
            $attributes['accepts_scheduled_work'] = $acceptsScheduledWork;
        }

        $workstation->update($attributes);

        $extension = TelephonyExtension::primaryForWorkstation((int) $workstation->id);

        if ($extension instanceof TelephonyExtension && $extension->display_name !== $label) {
            $extension->update(['display_name' => $label]);
        }

        return $workstation->fresh();
    }
}
