<?php

namespace App\Ark\Platform\Cloud;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * M1 — Real Cloud Accounts.
 * M2 — On register / login, attach or resume owned Shop via CloudShop.
 *
 * Does not create Tenant, ProvisioningRequest, or Stripe.
 */
final class CloudAccount
{
    public const SESSION_KEY = 'ark_cloud_trial';

    public function __construct(
        private readonly CloudShop $shops,
    ) {}

    /**
     * @param  array{shop_name?: string, slug?: string, owner_name?: string, email?: string, provisioned?: bool, mission?: array<int, bool>}  $funnelDraft
     */
    public function register(string $name, string $email, string $password, array $funnelDraft): User
    {
        $email = strtolower(trim($email));

        $user = new User;
        $user->forceFill([
            'name' => trim($name),
            'email' => $email,
            'password' => $password,
            'password_set_at' => now(),
            'is_active' => true,
            'cloud_funnel_draft' => $this->sanitizeDraft(array_merge($funnelDraft, [
                'owner_name' => trim($name),
                'email' => $email,
            ])),
        ]);
        $user->save();

        $shopName = (string) ($funnelDraft['shop_name'] ?? '');
        $slug = (string) ($funnelDraft['slug'] ?? '');
        if ($shopName !== '' && $slug !== '') {
            $shop = $this->shops->claimOrCreate($user, $shopName, $slug);
            $user->forceFill([
                'cloud_funnel_draft' => $this->sanitizeDraft(array_merge(
                    $user->cloud_funnel_draft ?? [],
                    $this->shops->funnelProjection($shop, $user),
                )),
            ])->save();
        }

        event(new Registered($user));

        Auth::login($user);
        $request = request();
        $request?->session()->regenerate();

        $this->writeSession(is_array($user->cloud_funnel_draft) ? $user->cloud_funnel_draft : []);

        return $user;
    }

    /**
     * @throws ValidationException
     */
    public function login(string $email, string $password, bool $remember = false): User
    {
        $email = strtolower(trim($email));
        $throttleKey = Str::transliterate(Str::lower($email).'|'.(request()?->ip() ?? 'cli'));

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        if (! Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->isActive()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'This account has been disabled.',
            ]);
        }

        if ($user->needsPasswordSetup()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Use the setup link from your invitation email to choose your password first.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        request()?->session()->regenerate();

        $this->hydrateSessionFromShop($user);

        return $user;
    }

    /**
     * @param  array{shop_name?: string, slug?: string, owner_name?: string, email?: string, provisioned?: bool, mission?: array<int, bool>, shop_id?: int}  $data
     * @return array{shop_name?: string, slug?: string, owner_name?: string, email?: string, provisioned?: bool, mission?: array<int, bool>, shop_id?: int}
     */
    public function mergeSession(array $data): array
    {
        $merged = array_merge($this->session(), $data);
        $this->writeSession($merged);

        $user = Auth::user();
        if ($user instanceof User) {
            $user->forceFill([
                'cloud_funnel_draft' => $this->sanitizeDraft($merged),
            ])->save();

            $shop = $this->shops->forUser($user);
            if ($shop !== null) {
                if (filled($merged['shop_name'] ?? null)) {
                    $this->shops->updateDisplayName($shop, (string) $merged['shop_name']);
                }
                if (filled($merged['slug'] ?? null)) {
                    $this->shops->updateSlug($shop, (string) $merged['slug']);
                }
            }
        }

        return $merged;
    }

    /**
     * Prefer real Shop when authenticated; otherwise session draft (pre-account steps).
     *
     * @return array{shop_name?: string, slug?: string, owner_name?: string, email?: string, provisioned?: bool, mission?: array<int, bool>, shop_id?: int}
     */
    public function session(): array
    {
        $stored = session(self::SESSION_KEY, []);
        if (! is_array($stored)) {
            $stored = [];
        }

        $user = Auth::user();
        if ($user instanceof User) {
            $shop = $this->shops->forUser($user);
            if ($shop !== null) {
                $draft = is_array($user->cloud_funnel_draft) ? $user->cloud_funnel_draft : [];

                return $this->sanitizeDraft(array_merge(
                    $this->shops->funnelProjection($shop, $user),
                    [
                        'provisioned' => $stored['provisioned'] ?? $draft['provisioned'] ?? false,
                        'mission' => $stored['mission'] ?? $draft['mission'] ?? [],
                    ],
                ));
            }
        }

        return $this->sanitizeDraft($stored);
    }

    /**
     * @param  array{shop_name?: string, slug?: string, owner_name?: string, email?: string, provisioned?: bool, mission?: array<int, bool>, shop_id?: int}  $draft
     */
    public function writeSession(array $draft): void
    {
        session([self::SESSION_KEY => $this->sanitizeDraft($draft)]);
    }

    private function hydrateSessionFromShop(User $user): void
    {
        $shop = $this->shops->forUser($user);
        if ($shop === null) {
            $this->writeSession(is_array($user->cloud_funnel_draft) ? $user->cloud_funnel_draft : []);

            return;
        }

        $draft = is_array($user->cloud_funnel_draft) ? $user->cloud_funnel_draft : [];
        $merged = $this->sanitizeDraft(array_merge(
            $this->shops->funnelProjection($shop, $user),
            [
                'provisioned' => $draft['provisioned'] ?? false,
                'mission' => $draft['mission'] ?? [],
            ],
        ));

        $user->forceFill(['cloud_funnel_draft' => $merged])->save();
        $this->writeSession($merged);
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array{shop_name?: string, slug?: string, owner_name?: string, email?: string, provisioned?: bool, mission?: array<int, bool>, shop_id?: int}
     */
    private function sanitizeDraft(array $draft): array
    {
        $clean = [];

        foreach (['shop_name', 'slug', 'owner_name', 'email'] as $key) {
            if (array_key_exists($key, $draft) && filled($draft[$key])) {
                $clean[$key] = is_string($draft[$key]) ? trim($draft[$key]) : $draft[$key];
            }
        }

        if (isset($draft['shop_id']) && is_numeric($draft['shop_id'])) {
            $clean['shop_id'] = (int) $draft['shop_id'];
        }

        if (array_key_exists('provisioned', $draft)) {
            $clean['provisioned'] = (bool) $draft['provisioned'];
        }

        if (isset($draft['mission']) && is_array($draft['mission'])) {
            $clean['mission'] = $draft['mission'];
        }

        return $clean;
    }
}
