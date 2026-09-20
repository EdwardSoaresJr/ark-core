<?php

namespace App\Ark\Operations\Portal;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class PortalAccessVerifyShowController
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $intended = PortalIntendedUrl::validate($request->session()->get(PortalIntendedUrl::SESSION_KEY));
        $bookContinuation = $intended === '/book' || str_starts_with((string) $intended, '/book?');

        if (Auth::guard('portal')->check()) {
            return redirect($intended ?? route('portal.home'));
        }

        return view('portal.verify', [
            'destinationLabel' => $request->session()->get('portal_access_destination_label'),
            'hasPendingChallenge' => $request->session()->has('portal_access_challenge_id'),
            'bookContinuation' => $bookContinuation,
        ]);
    }
}
