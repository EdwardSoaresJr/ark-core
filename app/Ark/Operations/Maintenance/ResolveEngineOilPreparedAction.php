<?php

namespace App\Ark\Operations\Maintenance;

/**
 * Auto Detect A+: last current MaintenanceServiceEvent → else shop prep defaults.
 * Never invents filter SKU, viscosity, or capacity as vehicle truth.
 */
final class ResolveEngineOilPreparedAction
{
    /**
     * @return array{
     *     prepared_oil_brand: ?string,
     *     prepared_viscosity: ?string,
     *     prepared_quantity_qt: ?string,
     *     prepared_filter_part: ?string,
     *     prepared_washer: ?MaintenanceWasherState,
     *     source: 'prior_event'|'shop_defaults'
     * }
     */
    public function handle(int $vehicleId): array
    {
        $prior = MaintenanceServiceEvent::latestCurrentForVehicle(
            $vehicleId,
            MaintenanceServiceKind::EngineOil,
        );

        if ($prior !== null) {
            return [
                'prepared_oil_brand' => $prior->oil_brand,
                'prepared_viscosity' => $prior->viscosity,
                'prepared_quantity_qt' => $prior->quantity_qt !== null ? (string) $prior->quantity_qt : null,
                'prepared_filter_part' => $prior->filter_part,
                'prepared_washer' => $prior->washer === MaintenanceWasherState::Installed
                    ? MaintenanceWasherState::Include
                    : ($prior->washer ?? MaintenanceWasherState::Include),
                'source' => 'prior_event',
            ];
        }

        $defaults = EngineOilShopDefaults::fromShopSettings();

        return [
            'prepared_oil_brand' => $defaults['preferred_oil_brand'],
            'prepared_viscosity' => null,
            'prepared_quantity_qt' => null,
            'prepared_filter_part' => null,
            'prepared_washer' => $defaults['include_washer_by_default']
                ? MaintenanceWasherState::Include
                : null,
            'source' => 'shop_defaults',
        ];
    }
}
