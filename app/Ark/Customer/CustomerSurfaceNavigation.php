<?php

namespace App\Ark\Customer;

use Illuminate\Support\Facades\Route;

final class CustomerSurfaceNavigation
{
    /**
     * Shop pages. Sign-in stays on utilityLink() so it does not sit in this row.
     *
     * @return list<array{label: string, href: string, active: bool, appointment?: bool}>
     */
    public function shopLinks(): array
    {
        $links = [];

        foreach ([
            'public.services' => 'Services',
            'public.common-problems.index' => 'Problems',
            'public.warranty' => 'Warranty',
            'public.financing' => 'Financing',
            'public.about' => 'About',
            'public.contact' => 'Contact',
        ] as $name => $label) {
            if (! Route::has($name)) {
                continue;
            }

            $active = $name === 'public.common-problems.index'
                ? request()->routeIs('public.common-problems.index', 'public.common-problems.show')
                : request()->routeIs($name);

            $links[] = [
                'label' => $label,
                'href' => route($name),
                'active' => $active,
            ];
        }

        if (Route::has('public.book')) {
            $links[] = [
                'label' => 'Request an appointment',
                'href' => route('public.book'),
                'active' => request()->routeIs('public.book'),
                'appointment' => true,
            ];
        }

        return $links;
    }

    /**
     * @return array{label: string, href: string, active: bool, utility: true}|null
     */
    public function utilityLink(): ?array
    {
        if (auth('portal')->check() && Route::has('portal.home')) {
            return [
                'label' => 'My Account',
                'href' => CustomerSurfaceUrls::portalHome(),
                'active' => request()->routeIs('portal.home', 'portal.vehicles.*'),
                'utility' => true,
            ];
        }

        if (Route::has('portal.access')) {
            return [
                'label' => 'Sign In',
                'href' => CustomerSurfaceUrls::portalAccess(),
                'active' => request()->routeIs('portal.access', 'portal.access.*', 'portal.index'),
                'utility' => true,
            ];
        }

        return null;
    }

    /**
     * @return list<array{label: string, href: string, active: bool, utility?: bool, appointment?: bool}>
     */
    public function items(): array
    {
        if (request()->routeIs('portal.*') && auth('portal')->check()) {
            $items = [];

            if (Route::has('portal.home')) {
                $items[] = [
                    'label' => 'My Vehicles',
                    'href' => CustomerSurfaceUrls::portalHome(),
                    'active' => request()->routeIs('portal.home', 'portal.vehicles.*'),
                ];
            }

            return $items;
        }

        $items = $this->shopLinks();
        $utility = $this->utilityLink();
        if ($utility !== null) {
            $items[] = $utility;
        }

        return $items;
    }
}
