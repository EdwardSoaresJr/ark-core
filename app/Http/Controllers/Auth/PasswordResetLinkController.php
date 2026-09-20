<?php

namespace App\Http\Controllers\Auth;

use App\Ark\Auth\StaffRecoveryService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request, StaffRecoveryService $recovery): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $message = $recovery->requestCode($request->string('email')->toString());

        return redirect()
            ->route('password.reset', ['email' => $request->string('email')->toString()])
            ->with('status', $message);
    }
}
