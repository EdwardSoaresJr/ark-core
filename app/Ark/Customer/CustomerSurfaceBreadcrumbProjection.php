<?php

namespace App\Ark\Customer;

use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Routing\Route;

final class CustomerSurfaceBreadcrumbProjection
{
    /**
     * @return list<array{label: string, href?: string|null}>
     */
    public function forCurrentRequest(): array
    {
        $route = request()->route();

        if ($route === null) {
            return [];
        }

        return match (true) {
            $route->named('portal.index') => $this->trail(
                $this->home(),
                ['label' => 'Sign In'],
            ),
            $route->named('portal.access') => $this->trail(
                $this->home(),
                ['label' => 'Sign In'],
            ),
            $route->named('portal.access.verify') => $this->trail(
                $this->home(),
                ['label' => 'Sign In', 'href' => CustomerSurfaceUrls::portalAccess()],
                ['label' => 'Verify code'],
            ),
            $route->named('portal.home') => $this->trail(
                $this->home(),
                ['label' => 'My Vehicles'],
            ),
            $route->named('portal.vehicles.show') => $this->trail(
                $this->home(),
                ['label' => 'My Vehicles', 'href' => CustomerSurfaceUrls::portalHome()],
                ['label' => $this->vehicleLabel($route)],
            ),
            $route->named('portal.estimates.show') => $this->trail(
                $this->home(),
                ['label' => 'Your estimate'],
            ),
            $route->named('portal.invoice-pay.show') => $this->trail(
                $this->home(),
                ['label' => 'Pay invoice'],
            ),
            $route->named('portal.inspections.show') => $this->trail(
                $this->home(),
                ['label' => 'Inspection'],
            ),
            default => [],
        };
    }

    /**
     * @param  list<array{label: string, href?: string|null}>  $items
     * @return list<array{label: string, href?: string|null}>
     */
    private function trail(array ...$items): array
    {
        return $items;
    }

    /**
     * @return array{label: string, href: string}
     */
    private function home(): array
    {
        return ['label' => 'Home', 'href' => CustomerSurfaceUrls::portalAccess()];
    }

    private function vehicleLabel(Route $route): string
    {
        $vehicle = $route->parameter('vehicle');

        if ($vehicle instanceof Vehicle) {
            return $vehicle->display_name;
        }

        return 'Vehicle';
    }
}
