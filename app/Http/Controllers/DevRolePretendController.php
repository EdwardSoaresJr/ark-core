<?php

namespace App\Http\Controllers;

use App\Ark\Operations\Staff\StaffFrontDoor;
use App\Ark\Runtime\Authorization\DevRolePretend;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DevRolePretendController extends Controller
{
    public function technician(Request $request): RedirectResponse
    {
        abort_unless(DevRolePretend::authorizedUser($request->user()), 403);

        DevRolePretend::activateTechnician();

        if ($user = $request->user()) {
            DevRolePretend::applyEffectiveRoles($user);
        }

        return redirect()
            ->to(StaffFrontDoor::landingUrl($request->user()))
            ->with('status', 'Testing as Technician. Use Admin to restore full access.');
    }

    public function clear(Request $request): RedirectResponse
    {
        abort_unless(DevRolePretend::authorizedUser($request->user()), 403);

        DevRolePretend::clear();

        $user = $request->user()?->fresh();

        return redirect()
            ->to($user !== null ? StaffFrontDoor::landingUrl($user) : route('operations.index'))
            ->with('status', 'Full admin access restored.');
    }
}
