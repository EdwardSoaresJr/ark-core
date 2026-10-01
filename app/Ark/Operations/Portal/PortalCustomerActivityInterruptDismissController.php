<?php

namespace App\Ark\Operations\Portal;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PortalCustomerActivityInterruptDismissController
{
    public function __invoke(
        Request $request,
        PortalCustomerActivityInterruptDismissal $dismissal,
        PortalCustomerActivityBroadcaster $broadcaster,
    ): Response {
        $portalInterruptKey = trim((string) $request->input('portal_interrupt_key', ''));
        $viewer = $request->user();

        if ($viewer !== null && $portalInterruptKey !== '') {
            $dismissal->dismiss($viewer->id, $portalInterruptKey);
        }

        if ($portalInterruptKey !== '') {
            $broadcaster->clear($portalInterruptKey);
        }

        return response()->noContent();
    }
}
