<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class RepairOrderPortalSessionController
{
    public const STAFF_ACTOR_SESSION_KEY = 'portal_staff_actor_id';

    public function __invoke(Request $request, RepairOrder $repairOrder): RedirectResponse
    {
        $repairOrder->loadMissing(['customer', 'vehicle']);

        abort_unless($repairOrder->customer !== null, 404);

        $defaultReturn = $repairOrder->vehicle !== null
            ? route('portal.vehicles.show', $repairOrder->vehicle, absolute: false)
            : route('portal.home', absolute: false);

        $requestedReturn = is_string($request->query('return')) ? $request->query('return') : null;

        if ($requestedReturn !== null) {
            $return = PortalIntendedUrl::validate($requestedReturn);
            abort_unless($return !== null, 404);
        } else {
            $return = PortalIntendedUrl::validate($defaultReturn);
            abort_unless($return !== null, 404);
        }

        Auth::guard('portal')->login($repairOrder->customer);
        $request->session()->regenerate();
        PortalObservationSession::start($request);

        if ($request->user() !== null) {
            $request->session()->put(self::STAFF_ACTOR_SESSION_KEY, $request->user()->id);
        }

        return redirect($return);
    }
}
