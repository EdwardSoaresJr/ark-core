<?php

namespace App\Ark\Operations\Workstations;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BindWorkstationController
{
    public function __invoke(Request $request, BindWorkstationBrowserAction $bind): RedirectResponse
    {
        $data = $request->validate([
            'workstation_id' => ['required', 'integer', 'exists:workstations,id'],
        ]);

        $workstation = Workstation::query()->findOrFail($data['workstation_id']);
        $binding = $bind->execute($workstation, $request->user());

        $request->session()->forget(WorkstationPresence::SESSION_BIND_DISMISSED);
        $request->session()->forget(WorkstationPresence::SESSION_LOCKED);

        return redirect()
            ->back()
            ->with('status', 'This computer is now at '.$workstation->name.'.')
            ->withCookie(WorkstationPresence::bindingCookie($binding))
            ->withCookie(WorkstationPresence::forgetBindDismissedCookie());
    }

    public function dismiss(Request $request): JsonResponse|RedirectResponse|Response
    {
        $request->session()->put(WorkstationPresence::SESSION_BIND_DISMISSED, true);

        $cookie = WorkstationPresence::bindDismissedCookie();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->noContent()->withCookie($cookie);
        }

        return redirect()->back()->withCookie($cookie);
    }
}
