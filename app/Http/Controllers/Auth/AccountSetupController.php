<?php

namespace App\Http\Controllers\Auth;

use App\Ark\Operations\Staff\StaffFrontDoor;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class AccountSetupController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasPasswordSet()) {
            return redirect()->to(StaffFrontDoor::landingUrl($request->user()));
        }

        return view('auth.account-setup');
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasPasswordSet()) {
            return redirect()->to(StaffFrontDoor::landingUrl($request->user()));
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($validated['password']),
            'password_set_at' => now(),
        ])->save();

        return redirect()
            ->to(StaffFrontDoor::landingUrl($request->user()))
            ->with('status', 'Your password is set. Welcome to ARK.');
    }
}
