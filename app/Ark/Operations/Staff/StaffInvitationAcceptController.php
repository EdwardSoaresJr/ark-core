<?php

namespace App\Ark\Operations\Staff;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StaffInvitationAcceptController
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'This invitation link is invalid or has expired.');
        }

        abort_unless($user->isActive(), 403, 'This account is disabled.');

        if ($user->hasPasswordSet()) {
            return redirect()
                ->route('login')
                ->with('status', 'Your account is already set up. Sign in with your password.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account.setup');
    }
}
