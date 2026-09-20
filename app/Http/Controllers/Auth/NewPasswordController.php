<?php

namespace App\Http\Controllers\Auth;

use App\Ark\Auth\StaffRecoveryService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.reset-password', [
            'email' => old('email', $request->string('email')->toString()),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request, StaffRecoveryService $recovery): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $recovery->resetPassword(
            $request->string('email')->toString(),
            $request->string('code')->toString(),
            $request->string('password')->toString(),
        );

        return redirect()->route('login')->with('status', 'Password updated. Sign in with your new password.');
    }
}
