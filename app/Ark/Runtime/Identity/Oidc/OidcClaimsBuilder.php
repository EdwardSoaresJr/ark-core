<?php

namespace App\Ark\Runtime\Identity\Oidc;

use App\Ark\Runtime\Preferences\AccentTheme;
use App\Models\User;

final class OidcClaimsBuilder
{
    private const ALLOWED_CLAIMS = [
        'sub',
        'email',
        'name',
        'groups',
        'products',
        'shop_id',
        'email_verified',
        'display_theme',
        'accent_theme',
        'accent_color',
    ];

    public function __construct(
        private readonly OidcProductAccessResolver $productAccess,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user, string $clientId): array
    {
        $claims = [
            'sub' => (string) $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'email_verified' => $user->email_verified_at !== null,
            'groups' => $user->roles->pluck('name')->values()->all(),
            'products' => $this->productAccess->resolve($user),
            'shop_id' => config('oidc.shop_id'),
            'display_theme' => $user->displayTheme()->value,
            'accent_theme' => $user->accentTheme()->value,
        ];

        if ($user->accentTheme() === AccentTheme::Custom && $user->accentColorHex() !== null) {
            $claims['accent_color'] = $user->accentColorHex();
        }

        return $this->filterAllowed($claims);
    }

    /**
     * @param  array<string, mixed>  $claims
     * @return array<string, mixed>
     */
    public function filterAllowed(array $claims): array
    {
        return array_intersect_key($claims, array_flip(self::ALLOWED_CLAIMS));
    }

    /**
     * @return list<string>
     */
    public static function allowedClaimNames(): array
    {
        return self::ALLOWED_CLAIMS;
    }
}
