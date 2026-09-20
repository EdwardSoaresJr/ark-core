<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PortalAccessChallengeStoreController
{
    public function __construct(
        private readonly CreatePortalAccessChallengeAction $createChallenge,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'contact' => ['required', 'string', 'max:255'],
        ]);

        $easterEgg = PortalAccessEasterEgg::messageFor($data['contact']);
        if ($easterEgg !== null) {
            $request->session()->forget([
                'portal_access_challenge_id',
                'portal_access_destination_label',
            ]);

            return redirect()
                ->route('portal.access')
                ->withInput()
                ->with('portal_access_easter_egg', $easterEgg);
        }

        $challenge = $this->createChallenge->execute($data['contact']);

        if ($challenge !== null) {
            $request->session()->put('portal_access_challenge_id', $challenge->id);
            $request->session()->put('portal_access_destination_label', $this->destinationLabel($challenge));
        } else {
            $request->session()->forget([
                'portal_access_challenge_id',
                'portal_access_destination_label',
            ]);
        }

        return redirect()
            ->route('portal.access.verify')
            ->with(
                'portal_access_notice',
                'If we found a matching record, we sent a 6-digit code.',
            );
    }

    private function destinationLabel(PortalAccessChallenge $challenge): string
    {
        if ($challenge->channel === PortalAccessChannel::Email) {
            return $this->maskEmail($challenge->destination);
        }

        return PhoneNumber::display($challenge->destination) ?? $challenge->destination;
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($local === '' || $domain === '') {
            return $email;
        }

        $visible = substr($local, 0, 1);
        $maskedLocal = $visible.str_repeat('*', max(1, strlen($local) - 1));

        return $maskedLocal.'@'.$domain;
    }
}
