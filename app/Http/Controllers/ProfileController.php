<?php

namespace App\Http\Controllers;

use App\Ark\Operations\Workstations\UpdateWorkstationOperatorPinAction;
use App\Ark\Runtime\Preferences\AccentTheme;
use App\Ark\Runtime\Preferences\DisplayTheme;
use App\Ark\Runtime\Preferences\EcosystemDisplayTheme;
use App\Http\Requests\ProfileAppearanceUpdateRequest;
use App\Http\Requests\ProfileIdentityUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'accentThemes' => AccentTheme::cases(),
            'displayThemes' => DisplayTheme::cases(),
            'initialTab' => $this->resolveInitialTab($request),
        ]);
    }

    /**
     * Update the user's profile identity.
     */
    public function update(ProfileIdentityUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return $this->redirectToTab('profile')->with('status', 'profile-updated');
    }

    public function updateAppearance(ProfileAppearanceUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());
        $request->user()->save();

        if ($request->user()->wasChanged('display_theme')) {
            EcosystemDisplayTheme::queueForUser($request->user()->displayTheme());
        }

        return $this->redirectToTab('appearance')->with('status', 'appearance-updated');
    }

    public function updateWorkstationPin(
        Request $request,
        UpdateWorkstationOperatorPinAction $updatePin,
    ): RedirectResponse {
        $data = $request->validateWithBag('updateWorkstationPin', [
            'password' => ['required', 'string'],
            'pin' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
            'pin_confirmation' => ['required', 'same:pin'],
        ]);

        $updatePin->execute($request->user(), $data['password'], $data['pin']);

        return $this->redirectToTab('workstation-pin')->with('status', 'workstation-pin-updated');
    }

    private function redirectToTab(string $tab): RedirectResponse
    {
        return Redirect::route('profile.edit', ['tab' => $tab]);
    }

    private function resolveInitialTab(Request $request): string
    {
        $allowed = ['profile', 'appearance', 'password', 'workstation-pin'];

        $tabFromQuery = $request->query('tab');

        if (is_string($tabFromQuery) && in_array($tabFromQuery, $allowed, true)) {
            return $tabFromQuery;
        }

        $statusTab = match ($request->session()->get('status')) {
            'profile-updated' => 'profile',
            'appearance-updated' => 'appearance',
            'password-updated' => 'password',
            'workstation-pin-updated' => 'workstation-pin',
            default => null,
        };

        if ($statusTab !== null) {
            return $statusTab;
        }

        $errors = $request->session()->get('errors');

        if ($errors?->hasBag('updatePassword') && $errors->getBag('updatePassword')->isNotEmpty()) {
            return 'password';
        }

        if ($errors?->hasBag('updateWorkstationPin') && $errors->getBag('updateWorkstationPin')->isNotEmpty()) {
            return 'workstation-pin';
        }

        if ($errors?->has('accent_theme') || $errors?->has('accent_color') || $errors?->has('display_theme')) {
            return 'appearance';
        }

        if ($errors?->has('name') || $errors?->has('email') || $errors?->has('phone')) {
            return 'profile';
        }

        return 'profile';
    }
}
