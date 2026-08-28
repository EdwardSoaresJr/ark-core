<?php

namespace App\Ark\Operations\Maintenance;

/**
 * Customer estimate Includes from Prepared Service — never invents vehicle specs.
 */
final class EngineOilPreparedIncludes
{
    /**
     * @return list<string>
     */
    public static function bullets(MaintenanceService $service): array
    {
        $bullets = [];

        $brand = trim((string) ($service->prepared_oil_brand ?? ''));
        $viscosity = trim((string) ($service->prepared_viscosity ?? ''));
        $qty = $service->prepared_quantity_qt;
        $filter = trim((string) ($service->prepared_filter_part ?? ''));

        if ($brand !== '' || $viscosity !== '' || $qty !== null) {
            $oil = trim(implode(' ', array_filter([
                $brand !== '' ? $brand : 'Full synthetic oil',
                $viscosity,
                $qty !== null ? ((string) $qty).' qt' : null,
            ])));
            $bullets[] = $oil;
        } else {
            $bullets[] = 'Full synthetic oil';
        }

        $bullets[] = $filter !== '' ? $filter.' oil filter' : 'Oil filter';

        $washer = $service->prepared_washer;
        if ($washer === MaintenanceWasherState::Include
            || $washer === MaintenanceWasherState::Installed) {
            $bullets[] = 'Drain plug washer';
        }

        return $bullets;
    }

    /**
     * @return list<string>
     */
    public static function installedBullets(MaintenanceServiceEvent $event): array
    {
        $bullets = [];

        $oil = trim(implode(' ', array_filter([
            trim((string) ($event->oil_brand ?? '')),
            trim((string) ($event->viscosity ?? '')),
            $event->quantity_qt !== null ? ((string) $event->quantity_qt).' qt' : null,
        ])));

        if ($oil !== '') {
            $bullets[] = $oil;
        }

        $filter = trim((string) ($event->filter_part ?? ''));
        if ($filter !== '') {
            $bullets[] = $filter;
        }

        if ($event->washer === MaintenanceWasherState::Installed) {
            $bullets[] = 'Drain plug washer';
        }

        return $bullets;
    }
}
