<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Documents\HtmlPdfBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Throwable;

class CallSessionCoachingPdfController
{
    public function __invoke(
        CallSession $callSession,
        CallCoachingSheetPresenter $presenter,
        HtmlPdfBuilder $pdf,
    ): Response|RedirectResponse {
        try {
            $sheet = $presenter->for($callSession);
            $html = view('operations.documents.sheets.call-coaching', ['sheet' => $sheet])->render();
            $bytes = $pdf->toPdfBytes($html);
        } catch (Throwable) {
            return redirect()
                ->back()
                ->with('error', 'Coaching handout PDF could not be generated. Check Chromium runtime support.');
        }

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="call-coaching-'.$callSession->id.'.pdf"',
        ]);
    }
}
