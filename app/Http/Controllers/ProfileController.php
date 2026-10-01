<?php

namespace App\Http\Controllers;

use App\Ark\Operations\Parts\PartsCatalogButtonColor;
use App\Ark\Operations\Parts\PartsCatalogConnections;
use App\Ark\Operations\Parts\PartsCatalogLinks;
use App\Ark\Operations\Parts\PartsCatalogProvider;
use App\Ark\Operations\Parts\UserPartsTechCredentials;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Workstations\UpdateWorkstationOperatorPinAction;
use App\Ark\Runtime\Preferences\AccentTheme;
use App\Ark\Runtime\Preferences\DisplayTheme;
use App\Ark\Runtime\Preferences\EcosystemDisplayTheme;
use App\Ark\Runtime\Preferences\EstimateToolbarPreference;
use App\Http\Requests\ProfileAppearanceUpdateRequest;
use App\Http\Requests\ProfileIdentityUpdateRequest;
use App\Http\Requests\ProfilePartsTechUpdateRequest;
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
            'partsCatalogs' => app(PartsCatalogConnections::class)->forAdvisor($request->user()),
            'partsCatalogLinks' => PartsCatalogLinks::customForUser($request->user()),
            'shopRepairLinkUrl' => ShopIntegrationCredentials::forCurrentShop()->repairLinkLaunchUrl(),
            'shopNexpartUrl' => ShopIntegrationCredentials::forCurrentShop()->nexpartLaunchUrl(),
            'userRepairLinkUrl' => PartsCatalogLinks::urlForUser($request->user(), PartsCatalogProvider::RepairLink->value),
            'userNexpartUrl' => PartsCatalogLinks::urlForUser($request->user(), PartsCatalogProvider::Nexpart->value),
            'userRepairLinkColor' => PartsCatalogLinks::rowForUser($request->user(), PartsCatalogProvider::RepairLink->value)['color']
                ?? PartsCatalogButtonColor::resolve(null, PartsCatalogProvider::RepairLink->value),
            'userNexpartColor' => PartsCatalogLinks::rowForUser($request->user(), PartsCatalogProvider::Nexpart->value)['color']
                ?? PartsCatalogButtonColor::resolve(null, PartsCatalogProvider::Nexpart->value),
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

    public function updatePartsTech(ProfilePartsTechUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        UserPartsTechCredentials::guardPasswordRequired(
            $user,
            $data['partstech_username'] ?? null,
            $data['partstech_password'] ?? null,
            route('profile.edit', ['tab' => 'partstech']),
        );

        UserPartsTechCredentials::apply(
            $user,
            $data['partstech_username'] ?? null,
            $data['partstech_password'] ?? null,
        );

        $user->save();

        return $this->redirectToTab('partstech')->with('status', 'partstech-updated');
    }

    public function updateCatalogs(Request $request): RedirectResponse
    {
        $user = $request->user();
        $existing = PartsCatalogLinks::customForUser($user);
        $submitted = $request->input('links', []);
        $create = $request->input('create', []);

        if (! is_array($submitted)) {
            $submitted = [];
        }

        if (is_array($create) && (filled($create['label'] ?? null) || filled($create['url'] ?? null))) {
            $submitted[] = [
                'label' => $create['label'] ?? '',
                'url' => $create['url'] ?? '',
                'mode' => $create['mode'] ?? PartsCatalogLinks::MODE_LINK,
                'color' => $create['color'] ?? null,
            ];
        }

        foreach ($submitted as $row) {
            if (! is_array($row)) {
                continue;
            }

            $delete = $row['delete'] ?? false;

            if ($delete === true || $delete === 1 || $delete === '1' || $delete === 'on') {
                continue;
            }

            $rawUrl = trim((string) ($row['url'] ?? ''));

            if ($rawUrl !== '' && ShopIntegrationCredentials::normalizedHttpsUrl($rawUrl) === null) {
                return $this->redirectToTab('catalogs')
                    ->withInput()
                    ->withErrors([
                        'catalog_links' => 'Catalog links must use https://. HTTP and URLs with usernames or passwords are not allowed.',
                    ]);
            }
        }

        $repairLinkRaw = trim((string) $request->input('repairlink_url'));
        $nexpartRaw = trim((string) $request->input('nexpart_url'));
        $repairLinkUrl = ShopIntegrationCredentials::normalizedHttpsUrl($repairLinkRaw);
        $nexpartUrl = ShopIntegrationCredentials::normalizedHttpsUrl($nexpartRaw);

        if ($repairLinkRaw !== '' && $repairLinkUrl === null) {
            return $this->redirectToTab('catalogs')
                ->withInput()
                ->withErrors([
                    'repairlink_url' => 'Enter an https:// address. HTTP and URLs with usernames or passwords are not allowed.',
                ]);
        }

        if ($nexpartRaw !== '' && $nexpartUrl === null) {
            return $this->redirectToTab('catalogs')
                ->withInput()
                ->withErrors([
                    'nexpart_url' => 'Enter an https:// address. HTTP and URLs with usernames or passwords are not allowed.',
                ]);
        }

        PartsCatalogLinks::persistUser(
            $user,
            PartsCatalogLinks::withReservedOverrides(
                PartsCatalogLinks::fromSubmitted($submitted, $existing),
                $repairLinkUrl,
                $nexpartUrl,
                $request->input('repairlink_color'),
                $request->input('nexpart_color'),
            ),
        );

        $default = trim((string) $request->input('default_parts_catalog'));

        if ($default !== '') {
            EstimateToolbarPreference::persist($user, EstimateToolbarPreference::KIND_PARTS, $default);
        }

        return $this->redirectToTab('catalogs')->with('status', 'catalogs-updated');
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
        $allowed = ['profile', 'appearance', 'password', 'workstation-pin', 'partstech', 'catalogs'];

        $tabFromQuery = $request->query('tab');

        if (is_string($tabFromQuery) && in_array($tabFromQuery, $allowed, true)) {
            return $tabFromQuery;
        }

        $statusTab = match ($request->session()->get('status')) {
            'profile-updated' => 'profile',
            'appearance-updated' => 'appearance',
            'password-updated' => 'password',
            'workstation-pin-updated' => 'workstation-pin',
            'partstech-updated' => 'partstech',
            'catalogs-updated' => 'catalogs',
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

        if ($errors?->has('partstech_username') || $errors?->has('partstech_password')) {
            return 'partstech';
        }

        if ($errors?->has('catalog_links') || $errors?->has('repairlink_url') || $errors?->has('nexpart_url') || $errors?->has('default_parts_catalog')) {
            return 'catalogs';
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
