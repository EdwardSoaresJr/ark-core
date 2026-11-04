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
        $links = $this->siteLinks();
        if ($links !== null) {
            return [...$links, ...$this->appointmentLink()];
        }

        $links = [];

        if (Route::has('public.home')) {
            $links[] = [
                'label' => 'Home',
                'href' => CustomerSurfaceUrls::shopHome(),
                'active' => request()->routeIs('public.home'),
            ];
        }

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

            $active = match ($name) {
                'public.common-problems.index' => request()->routeIs('public.common-problems.index', 'public.common-problems.show'),
                'public.services' => request()->routeIs('public.services', 'public.services.show'),
                default => request()->routeIs($name),
            };

            $links[] = [
                'label' => $label,
                'href' => route($name),
                'active' => $active,
            ];
        }

        return [...$links, ...$this->appointmentLink()];
    }

    /** @return list<array{label: string, href: string, active: bool, appointment: true}> */
    private function appointmentLink(): array
    {
        if (! Route::has('public.book')) {
            return [];
        }

        return [[
            'label' => 'Request an appointment',
            'href' => route('public.book'),
            'active' => request()->routeIs('public.book'),
            'appointment' => true,
        ]];
    }

    /**
     * The host website's own pages (CustomerSiteNavigation), limited to paths on this host.
     *
     * @return list<array{label: string, href: string, active: bool}>|null
     */
    private function siteLinks(): ?array
    {
        if (! app()->bound(CustomerSiteNavigation::class)) {
            return null;
        }

        try {
            $links = app(CustomerSiteNavigation::class)->links();
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
        if ($links === null) {
            return null;
        }

        $safe = [];
        foreach (array_slice($links, 0, 8) as $link) {
            $href = (string) ($link['href'] ?? '');
            $label = trim((string) ($link['label'] ?? ''));
            if ($label === '' || preg_match('#^/(?!/)[^\s\\\\]*$#', $href) !== 1) {
                continue;
            }
            $safe[] = ['label' => mb_substr($label, 0, 40), 'href' => $href, 'active' => (bool) ($link['active'] ?? false)];
        }

        return $safe;
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
        $items = $this->shopLinks();
        $utility = $this->utilityLink();
        if ($utility !== null) {
            $items[] = $utility;
        }

        return $items;
    }
}
