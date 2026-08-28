<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Documents\HtmlPdfBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

final class PortalInspectionPdfController
{
    public function __invoke(
        string $token,
        Request $request,
        ResolveInspectionAccessTokenAction $resolve,
        PortalInspectionPage $page,
        HtmlPdfBuilder $pdf,
    ): Response {
        $accessToken = $resolve->execute($token, touchViewed: false);
        abort_unless($accessToken !== null, 404);

        $repairOrder = $accessToken->repairOrder()->firstOrFail();
        $mode = $page->normalizeMode($request->query('view'));
        $share = $page->durableShareForPrint($accessToken, $token);
        $html = $page->printHtml($repairOrder, $mode, $share['url'], $share['plain_token']);

        try {
            $bytes = $pdf->toPdfBytes($html);
        } catch (Throwable) {
            abort(503, 'Inspection PDF could not be generated.');
        }

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="inspection-ro-'.$repairOrder->repair_order_id.'.pdf"',
        ]);
    }
}
