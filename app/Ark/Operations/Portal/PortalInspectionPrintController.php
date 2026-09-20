<?php

namespace App\Ark\Operations\Portal;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PortalInspectionPrintController
{
    public function __invoke(
        string $token,
        Request $request,
        ResolveInspectionAccessTokenAction $resolve,
        PortalInspectionPage $page,
    ): Response {
        $accessToken = $resolve->execute($token, touchViewed: false);
        abort_unless($accessToken !== null, 404);

        $repairOrder = $accessToken->repairOrder()->firstOrFail();
        $mode = $page->normalizeMode($request->query('view'));
        $share = $page->durableShareForPrint($accessToken, $token);
        $html = $page->printHtml($repairOrder, $mode, $share['url'], $share['plain_token']);

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
