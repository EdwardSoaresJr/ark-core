<?php

declare(strict_types=1);

namespace App\Ark\Operations\Printing;

use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Local-only proof that ARK-owned dev certs work with stock QZ Tray + sign-message.
 *
 * @see docs/printing/ark-self-signed-feasibility.md
 */
final class QzPrintingPocController
{
    public function __invoke(): View
    {
        if (! app()->environment('local')) {
            throw new NotFoundHttpException;
        }

        return view('dev.qz-poc', [
            'health' => QzTraySigning::healthSnapshot(),
            'selfTest' => QzTraySigning::selfTestSigningRoundTrip(),
            'certificate' => QzTraySigning::certificateContents(),
            'algorithm' => QzTraySigning::javascriptSignatureAlgorithm(),
        ]);
    }
}
