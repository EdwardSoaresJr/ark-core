<?php

namespace App\Ark\Operations\Portal;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class RepairPortalShowController
{
    public function __invoke(
        string $code,
        Request $request,
        ResolveRepairOrderPortalAccessAction $resolve,
        RepairPortalHubProjection $hub,
        PortalEstimatePage $estimatePage,
    ): View {
        $access = $resolve->byPublicCode($code);
        abort_unless($access !== null, 404);

        $recordCustomerView = $estimatePage->shouldRecordCustomerView($request);

        return view('portal.repair-hub', $hub->forAccess($access, $access->public_code, $recordCustomerView));
    }
}
