<?php

namespace App\Ark\Runtime\Booking;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Safety net only — not marketing CMS. Named route intentionally omitted (no public.book).
 */
final class BookingSurfaceRedirectController
{
    public function __invoke(Request $request): RedirectResponse
    {
        if (! BookingSurface::isConfigured()) {
            throw new NotFoundHttpException;
        }

        $query = $request->getQueryString() ?? '';

        return redirect()->away(BookingSurface::bookUrl($query), 302);
    }
}
