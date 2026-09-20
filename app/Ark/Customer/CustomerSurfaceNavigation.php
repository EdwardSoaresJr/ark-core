<?php

namespace App\Ark\Customer;

use Illuminate\Support\Facades\Route;

final class CustomerSurfaceNavigation
{
    /**
     * @return list<array{label: string, href: string, active: bool}>
     */
    public function items(): array
    {
        $items = [];

        if (auth('portal')->check()) {
            $items[] = [
                'label' => 'My Vehicles',
                'href' => CustomerSurfaceUrls::portalHome(),
                'active' => request()->routeIs('portal.home', 'portal.vehicles.*'),
            ];

            return $items;
        }

        if (Route::has('portal.access')) {
            $items[] = [
                'label' => 'Sign In',
                'href' => CustomerSurfaceUrls::portalAccess(),
                'active' => request()->routeIs('portal.access', 'portal.access.*', 'portal.index'),
            ];
        }

        return $items;
    }
}
