<?php

namespace App\Ark\Operations\Portal;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class PortalInspectionShowController
{
    public function __invoke(
        string $token,
        Request $request,
        ResolveInspectionAccessTokenAction $resolve,
        PortalInspectionPage $page,
    ): View {
        $accessToken = $resolve->execute($token, touchViewed: false);
        abort_unless($accessToken !== null, 404);

        return $page->renderToken(
            accessToken: $accessToken,
            plainToken: $token,
            recordCustomerView: $page->shouldRecordCustomerView($request),
            mode: $page->normalizeMode($request->query('view')),
        );
    }
}
