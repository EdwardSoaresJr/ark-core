<?php

namespace App\Ark\Customer;

use App\Ark\Operations\Leads\Public\CommonProblemRegistry;
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
            $route->named('public.home') => [],
            $route->named('public.common-problems.index') => $this->trail(
                $this->home(),
                ['label' => 'Common problems'],
            ),
            $route->named('public.common-problems.show') => $this->trail(
                $this->home(),
                ['label' => 'Common problems', 'href' => route('public.common-problems.index')],
                ['label' => $this->commonProblemTitle($route)],
            ),
            $route->named('public.financing') => $this->trail(
                $this->home(),
                ['label' => 'Financing'],
            ),
            $route->named('public.book') => [],
            $route->named('public.warranty') => $this->trail(
                $this->home(),
                ['label' => 'Warranty'],
            ),
            $route->named('public.contact') => $this->trail(
                $this->home(),
                ['label' => 'Contact'],
            ),
            $route->named('public.repairpal') => $this->trail(
                $this->home(),
                ['label' => 'RepairPal'],
            ),
            $route->named('public.repairpal.certified') => $this->trail(
                $this->home(),
                ['label' => 'RepairPal', 'href' => route('public.repairpal')],
                ['label' => 'Certified'],
            ),
            $route->named('public.repairpal.reviews') => $this->trail(
                $this->home(),
                ['label' => 'RepairPal', 'href' => route('public.repairpal')],
                ['label' => 'Reviews'],
            ),
            $route->named('public.repairpal.warranty') => $this->trail(
                $this->home(),
                ['label' => 'RepairPal', 'href' => route('public.repairpal')],
                ['label' => 'Warranty'],
            ),
            $route->named('public.privacy') => $this->trail(
                $this->home(),
                ['label' => 'Privacy'],
            ),
            $route->named('public.terms') => $this->trail(
                $this->home(),
                ['label' => 'Terms'],
            ),
            $route->named('public.leads.thanks') => $this->trail(
                $this->home(),
                ['label' => 'Request received'],
            ),
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
        return ['label' => 'Home', 'href' => CustomerSurfaceUrls::publicHome()];
    }

    private function commonProblemTitle(Route $route): string
    {
        $slug = (string) $route->parameter('slug', '');
        $problem = CommonProblemRegistry::find($slug);

        return is_array($problem) ? (string) ($problem['title'] ?? $slug) : $slug;
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
