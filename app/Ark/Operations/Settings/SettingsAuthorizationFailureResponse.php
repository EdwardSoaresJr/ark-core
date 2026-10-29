<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Operations\Staff\StaffFrontDoor;
use App\Ark\Runtime\Authorization\DevRolePretend;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SettingsAuthorizationFailureResponse
{
    public static function for(Request $request): JsonResponse|RedirectResponse|null
    {
        if (! $request->routeIs('operations.settings.*')) {
            return null;
        }

        if (DevRolePretend::isActive()) {
            $message = 'Choose "Return to admin access" in the account menu before using shop settings.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            return redirect()
                ->to(StaffFrontDoor::landingUrl($request->user()))
                ->with('status', $message);
        }

        if (! $request->expectsJson()) {
            return redirect()
                ->route('operations.index')
                ->withErrors([
                    'settings' => 'Shop settings require the Admin role. Assign Admin under Settings → Staff, or sign in as an admin account.',
                ]);
        }

        return null;
    }
}
