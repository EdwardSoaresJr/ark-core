<?php

namespace App\Ark\Operations\Portal;

use Illuminate\Http\RedirectResponse;

final class PortalShortLinkRedirectController
{
    public function __invoke(string $code): RedirectResponse
    {
        $link = PortalShortLink::query()
            ->where('code', $code)
            ->first();

        if ($link === null || ! $link->isActive()) {
            abort(404);
        }

        return redirect()->to($link->destination_url);
    }
}
