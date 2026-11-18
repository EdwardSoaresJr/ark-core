<?php

namespace App\Ark\Operations\Staff;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Optional host-side extension point for staff sidebar / rail destinations.
 *
 * Standalone Core binds a no-op. Hosted SaaS may bind a request-scoped
 * implementation. Core must not learn SaaS route names here.
 *
 * @phpstan-type NavItem array{key: string, label: string, url: string, icon: string, active: bool, live_count: bool}
 * @phpstan-type NavSection array{label: ?string, items: list<NavItem>}
 */
interface StaffNavigationAugmentor
{
    /**
     * @param  list<NavSection>  $sections
     * @return list<NavSection>
     */
    public function augment(array $sections, ?User $user, Request $request): array;
}
