<?php

namespace App\Ark\Operations\Communications;

use Illuminate\Support\Facades\Schema;

/**
 * Voice / endpoint architecture tables required by Shop · Communications.
 */
final class ShopCommunicationsSchema
{
    public static function isReady(): bool
    {
        return self::missingRequirements() === [];
    }

    /**
     * @return list<string>
     */
    public static function missingRequirements(): array
    {
        $missing = [];

        if (! Schema::hasTable('workstations')) {
            $missing[] = 'workstations table';
        }

        if (! Schema::hasTable('communication_device_models')) {
            $missing[] = 'communication_device_models table';
        }

        if (! Schema::hasTable('endpoint_configuration_projections')) {
            $missing[] = 'endpoint_configuration_projections table';
        }

        if (Schema::hasTable('communication_devices')) {
            if (! Schema::hasColumn('communication_devices', 'workstation_id')) {
                $missing[] = 'communication_devices.workstation_id';
            }

            if (! Schema::hasColumn('communication_devices', 'mac_address')) {
                $missing[] = 'communication_devices endpoint identity columns';
            }
        } else {
            $missing[] = 'communication_devices table';
        }

        if (Schema::hasTable('telephony_extensions')) {
            if (! Schema::hasColumn('telephony_extensions', 'workstation_id')) {
                $missing[] = 'telephony_extensions.workstation_id';
            }
        } else {
            $missing[] = 'telephony_extensions table';
        }

        return $missing;
    }
}
