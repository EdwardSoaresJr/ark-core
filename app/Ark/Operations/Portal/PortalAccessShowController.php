<?php

namespace App\Ark\Operations\Portal;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class PortalAccessShowController
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $intended = PortalIntendedUrl::validate($request->query('return'));

        if (Auth::guard('portal')->check()) {
            return redirect($intended ?? route('portal.home'));
        }

        PortalIntendedUrl::store($request, $intended);

        return view('portal.access', [
            'bookContinuation' => $intended === '/book' || str_starts_with((string) $intended, '/book?'),
        ]);
    }
}
