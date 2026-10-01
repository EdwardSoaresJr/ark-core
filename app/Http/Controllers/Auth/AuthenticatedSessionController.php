<?php

namespace App\Http\Controllers\Auth;

use App\Ark\Operations\Staff\StaffFrontDoor;
use App\Ark\Operations\Workstations\AssignWorkstationOperatorAction;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Operations\Workstations\WorkstationBrowserRoster;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): View
    {
        if ($request->filled('redirect')) {
            $request->session()->put('url.intended', $request->string('redirect')->toString());
        }

        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $binding = WorkstationPresence::resolveBinding($request);

        if ($binding !== null && $request->user() !== null) {
            app(WorkstationBrowserRoster::class)->remember($binding, $request->user());

            $workstation = WorkstationPresence::resolveBoundWorkstation($request);

            if (
                $workstation instanceof Workstation
                && $workstation->current_operator_user_id === null
            ) {
                app(AssignWorkstationOperatorAction::class)->execute(
                    $workstation,
                    $request->user(),
                    $binding,
                );
            }
        }

        return redirect()->to(StaffFrontDoor::postLoginRedirectUrl($request->user()));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
