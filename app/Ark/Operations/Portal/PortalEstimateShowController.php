<?php

namespace App\Ark\Operations\Portal;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PortalEstimateShowController
{
    public function __invoke(
        string $token,
        Request $request,
        ResolveEstimateAccessTokenAction $resolve,
        PortalEstimatePage $page,
    ): View {
        $accessToken = $resolve->execute($token, touchViewed: false);

        abort_unless($accessToken !== null, 404);

        return $page->render(
            accessToken: $accessToken,
            plainToken: $token,
            recordCustomerView: $page->shouldRecordCustomerView($request),
        );
    }
}
