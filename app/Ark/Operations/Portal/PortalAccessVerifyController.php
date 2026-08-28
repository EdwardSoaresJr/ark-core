<?php

namespace App\Ark\Operations\Portal;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class PortalAccessVerifyController
{
    public function __construct(
        private readonly VerifyPortalAccessChallengeAction $verifyChallenge,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ]);

        $challengeId = $request->session()->get('portal_access_challenge_id');

        if ($challengeId === null) {
            return redirect()
                ->route('portal.access')
                ->withErrors(['contact' => 'Start again with your email or mobile number.']);
        }

        $challenge = PortalAccessChallenge::query()
            ->with('customer')
            ->find($challengeId);

        if ($challenge === null) {
            $request->session()->forget([
                'portal_access_challenge_id',
                'portal_access_destination_label',
            ]);

            return redirect()
                ->route('portal.access')
                ->withErrors(['contact' => 'Start again with your email or mobile number.']);
        }

        if (! $this->verifyChallenge->execute($challenge, $data['code'])) {
            return back()->withErrors([
                'code' => 'That code is invalid or expired.',
            ]);
        }

        Auth::guard('portal')->login($challenge->customer);
        $request->session()->regenerate();
        PortalObservationSession::start($request);
        $request->session()->forget([
            'portal_access_challenge_id',
            'portal_access_destination_label',
        ]);

        $intended = PortalIntendedUrl::pull($request);

        return redirect($intended ?? route('portal.home'));
    }
}
