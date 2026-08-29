<?php

namespace App\Ark\Platform\Cloud;

use App\Ark\Runtime\Surfaces\SurfaceRouting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * ARK Cloud Funnel — the product journey on autorepairkeeper.com.
 *
 * M1: Account is real (User).
 * M2: Shop is real (platform Shop owned by User). Provisioning remains funnel UI.
 *
 * @see docs/platform/cloud-funnel-v1.md
 * @see docs/platform/cloud-saas-critical-path-v1.md
 */
final class CloudExperienceController
{
    /** @var list<string> */
    private const MISSION_LABELS = [
        'Add your first customer',
        'Add your first vehicle',
        'Create your first repair order',
    ];

    public function __construct(
        private readonly CloudAccount $accounts,
    ) {}

    public function home(): View
    {
        return view('cloud.home');
    }

    public function features(): View
    {
        return view('cloud.features');
    }

    public function pricing(): View|RedirectResponse
    {
        if (! CloudPublicPosture::pricingPublic()) {
            return redirect()->to(CloudUrls::route('hosted'));
        }

        return view('cloud.pricing');
    }

    public function resources(): View
    {
        return view('cloud.resources');
    }

    public function demo(): View|RedirectResponse
    {
        if (! CloudPublicPosture::signupsOpen()) {
            return redirect()->to(CloudUrls::route('hosted'));
        }

        return view('cloud.demo');
    }

    public function hosted(): View
    {
        return view('cloud.hosted', [
            'interestMailto' => CloudPublicPosture::interestMailto(),
            'interestEmail' => CloudPublicPosture::interestEmail(),
        ]);
    }

    public function login(): View
    {
        return view('cloud.login', [
            'forgotPasswordUrl' => $this->forgotPasswordUrl(),
        ]);
    }

    public function storeLogin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:1'],
        ]);

        try {
            $this->accounts->login($validated['email'], $validated['password']);
        } catch (ValidationException $e) {
            throw $e;
        }

        $trial = $this->accounts->session();

        if (blank($trial['shop_name'] ?? null)) {
            return redirect()->to(
                CloudPublicPosture::signupsOpen()
                    ? CloudUrls::route('trial.shop')
                    : CloudUrls::route('hosted')
            );
        }

        return redirect()->to(CloudUrls::route('dashboard'));
    }

    public function trialShop(): View|RedirectResponse
    {
        if ($redirect = $this->redirectWhenSignupsClosed()) {
            return $redirect;
        }

        $trial = $this->accounts->session();
        CloudFunnelAnalytics::track(CloudFunnelAnalytics::TRIAL_STARTED, [
            'has_existing_session' => filled($trial['shop_name'] ?? null),
        ]);

        return view('cloud.trial.shop', [
            'shopName' => $trial['shop_name'] ?? '',
            'step' => 1,
        ]);
    }

    public function storeTrialShop(Request $request): RedirectResponse
    {
        if ($redirect = $this->redirectWhenSignupsClosed()) {
            return $redirect;
        }

        $validated = $request->validate([
            'shop_name' => ['required', 'string', 'max:120'],
        ]);

        $shopName = trim($validated['shop_name']);
        $slug = $this->slugFromName($shopName);

        $this->accounts->mergeSession([
            'shop_name' => $shopName,
            'slug' => $slug,
        ]);

        CloudFunnelAnalytics::track(CloudFunnelAnalytics::SHOP_COMPLETED, [
            'shop_name' => $shopName,
            'slug' => $slug,
        ]);

        return redirect()->to(CloudUrls::route('trial.workspace'));
    }

    public function trialWorkspace(): View|RedirectResponse
    {
        if ($redirect = $this->redirectWhenSignupsClosed()) {
            return $redirect;
        }

        $trial = $this->accounts->session();
        if (blank($trial['shop_name'] ?? null)) {
            return redirect()->to(CloudUrls::route('trial.shop'));
        }

        return view('cloud.trial.workspace', [
            'shopName' => $trial['shop_name'],
            'slug' => $trial['slug'],
            'step' => 2,
        ]);
    }

    public function storeTrialWorkspace(Request $request): RedirectResponse
    {
        if ($redirect = $this->redirectWhenSignupsClosed()) {
            return $redirect;
        }

        $ignoreShopId = Auth::user()?->ownedShop?->id;

        $validated = $request->validate([
            'slug' => [
                'required',
                'string',
                'max:63',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('platform_shops', 'slug')->ignore($ignoreShopId),
            ],
        ]);

        $this->accounts->mergeSession([
            'slug' => $validated['slug'],
        ]);

        CloudFunnelAnalytics::track(CloudFunnelAnalytics::WORKSPACE_COMPLETED, [
            'slug' => $validated['slug'],
        ]);

        return redirect()->to(CloudUrls::route('trial.account'));
    }

    public function trialAccount(): View|RedirectResponse
    {
        if ($redirect = $this->redirectWhenSignupsClosed()) {
            return $redirect;
        }

        $trial = $this->accounts->session();
        if (blank($trial['shop_name'] ?? null) || blank($trial['slug'] ?? null)) {
            return redirect()->to(CloudUrls::route('trial.shop'));
        }

        return view('cloud.trial.account', [
            'shopName' => $trial['shop_name'],
            'slug' => $trial['slug'],
            'ownerName' => $trial['owner_name'] ?? 'Edward',
            'email' => $trial['email'] ?? '',
            'step' => 3,
        ]);
    }

    public function storeTrialAccount(Request $request): RedirectResponse
    {
        if ($redirect = $this->redirectWhenSignupsClosed()) {
            return $redirect;
        }

        $validated = $request->validate([
            'owner_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $draft = $this->accounts->session();

        $this->accounts->register(
            $validated['owner_name'],
            $validated['email'],
            $validated['password'],
            $draft,
        );

        CloudFunnelAnalytics::track(CloudFunnelAnalytics::ACCOUNT_COMPLETED, [
            'email' => strtolower(trim($validated['email'])),
        ]);

        return redirect()->to(CloudUrls::route('trial.provisioning'));
    }

    public function provisioning(): View|RedirectResponse
    {
        if ($redirect = $this->redirectWhenSignupsClosed()) {
            return $redirect;
        }

        $trial = $this->accounts->session();
        if (blank($trial['shop_name'] ?? null) || blank($trial['owner_name'] ?? null)) {
            return redirect()->to(CloudUrls::route('trial.shop'));
        }

        return view('cloud.trial.provisioning', [
            'shopName' => $trial['shop_name'],
            'slug' => $trial['slug'],
            'ownerName' => $trial['owner_name'],
            'step' => 4,
        ]);
    }

    private function redirectWhenSignupsClosed(): ?RedirectResponse
    {
        if (CloudPublicPosture::signupsOpen()) {
            return null;
        }

        return redirect()->to(CloudUrls::route('hosted'));
    }

    public function welcome(): View|RedirectResponse
    {
        $trial = $this->accounts->session();
        if (blank($trial['shop_name'] ?? null)) {
            return redirect()->to(CloudUrls::route('home'));
        }

        $this->accounts->mergeSession(['provisioned' => true]);
        $trial = $this->accounts->session();

        CloudFunnelAnalytics::track(CloudFunnelAnalytics::FUNNEL_COMPLETED, [
            'shop_name' => $trial['shop_name'] ?? null,
            'slug' => $trial['slug'] ?? null,
        ]);

        return view('cloud.welcome', array_merge([
            'shopName' => $trial['shop_name'],
            'slug' => $trial['slug'],
            'ownerName' => $trial['owner_name'] ?? 'there',
        ], $this->missionProjection($trial)));
    }

    public function dashboard(): View|RedirectResponse
    {
        $trial = $this->accounts->session();
        if (blank($trial['shop_name'] ?? null)) {
            return redirect()->to(CloudUrls::route('login'));
        }

        $hour = (int) now()->format('G');
        $greeting = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };

        return view('cloud.dashboard', array_merge([
            'shopName' => $trial['shop_name'],
            'slug' => $trial['slug'],
            'ownerName' => $trial['owner_name'] ?? 'Edward',
            'greeting' => $greeting,
            'workspaceHost' => ($trial['slug'] ?? 'shop').'.arksms.com',
        ], $this->missionProjection($trial)));
    }

    public function openWorkspace(): RedirectResponse
    {
        $trial = $this->accounts->session();
        CloudFunnelAnalytics::track(CloudFunnelAnalytics::OPEN_WORKSPACE, [
            'slug' => $trial['slug'] ?? null,
            'authenticated' => auth()->check(),
        ]);

        if (auth()->check()) {
            if (SurfaceRouting::enabled()) {
                return redirect()->away(
                    SurfaceRouting::urlForHost(SurfaceRouting::appHost(), '/app'),
                );
            }

            return redirect('/app');
        }

        if (SurfaceRouting::enabled()) {
            return redirect()->away(
                SurfaceRouting::urlForHost(SurfaceRouting::appHost(), '/app/login'),
            );
        }

        return redirect()->route('login');
    }

    /**
     * @param  array{shop_name?: string, slug?: string, owner_name?: string, email?: string, provisioned?: bool, mission?: array<int, bool>}  $trial
     * @return array{missionSteps: list<array{label: string, done: bool}>, missionComplete: int, missionTotal: int}
     */
    private function missionProjection(array $trial): array
    {
        $done = is_array($trial['mission'] ?? null) ? $trial['mission'] : [];

        $steps = [];
        foreach (self::MISSION_LABELS as $index => $label) {
            $steps[] = [
                'label' => $label,
                'done' => (bool) ($done[$index] ?? false),
            ];
        }

        $complete = count(array_filter($steps, static fn (array $step): bool => $step['done']));

        return [
            'missionSteps' => $steps,
            'missionComplete' => $complete,
            'missionTotal' => count($steps),
        ];
    }

    private function slugFromName(string $shopName): string
    {
        $slug = Str::slug($shopName);
        $slug = preg_replace('/^the-/', '', $slug) ?? $slug;
        $slug = str_replace(['-automotive', '-auto-repair', '-auto', '-llc', '-inc'], '', $slug);

        if ($slug === '' || $slug === '-') {
            $slug = 'my-shop';
        }

        return Str::limit($slug, 40, '');
    }

    private function forgotPasswordUrl(): string
    {
        if (SurfaceRouting::enabled()) {
            return SurfaceRouting::urlForHost(SurfaceRouting::appHost(), '/app/forgot-password');
        }

        return url('/app/forgot-password');
    }
}
