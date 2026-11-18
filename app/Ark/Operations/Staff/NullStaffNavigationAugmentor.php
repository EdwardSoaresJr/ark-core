<?php

namespace App\Ark\Operations\Staff;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Default augmentor: return Core sections unchanged.
 */
final class NullStaffNavigationAugmentor implements StaffNavigationAugmentor
{
    public function augment(array $sections, ?User $user, Request $request): array
    {
        return $sections;
    }
}
