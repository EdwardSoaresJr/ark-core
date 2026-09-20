<?php

namespace App\Http\Controllers\Auth;

use App\Ark\Auth\OfflineRecoveryService;
use App\Ark\Install\InstallationIdentity;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OfflineRecoveryController extends Controller
{
    public function create(OfflineRecoveryService $recovery): View
    {
        $challenge = $recovery->issueChallenge();

        return view('auth.offline-recovery', [
            'installationUuid' => InstallationIdentity::uuid(),
            'challenge' => $challenge->public_id,
            'cloudUrl' => rtrim((string) config('services.ark_cloud.base_url'), '/').'/recovery/offline',
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request, OfflineRecoveryService $recovery): RedirectResponse
    {
        $request->validate([
            'authorization' => ['required', 'string'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $recovery->resetPassword(
            $request->string('authorization')->toString(),
            $request->string('password')->toString(),
        );

        return redirect()->route('login')->with('status', 'Password updated. Sign in with your new password.');
    }
}
