<?php

namespace App\Ark\Customer;

use App\Ark\Operations\Leads\Public\PublicSurfaceSettings;
use Illuminate\Support\Facades\Route;

final class CustomerSurfaceNavigation
{
    /**
     * @return list<array{label: string, href: string, active: bool}>
     */
    public function items(): array
    {
        $items = [
            [
                'label' => 'Home',
                'href' => CustomerSurfaceUrls::publicHome(),
                'active' => request()->routeIs('public.home'),
            ],
        ];

        if (Route::has('public.common-problems.index')) {
            $items[] = [
                'label' => 'Common Problems',
                'href' => CustomerSurfaceUrls::commonProblems(),
                'active' => request()->routeIs('public.common-problems.*'),
            ];
        }

        // Book is a primary header CTA — not a nav destination.

        if (
            Route::has('public.financing')
            && (PublicSurfaceSettings::current()['trust_signals']['financing_available'] ?? false)
        ) {
            $items[] = [
                'label' => 'Financing',
                'href' => route('public.financing'),
                'active' => request()->routeIs('public.financing'),
            ];
        }

        if (Route::has('public.contact')) {
            $items[] = [
                'label' => 'Contact',
                'href' => route('public.contact'),
                'active' => request()->routeIs('public.contact'),
            ];
        }

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
