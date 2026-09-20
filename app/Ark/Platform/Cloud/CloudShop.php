<?php

namespace App\Ark\Platform\Cloud;

use App\Ark\Platform\Shop;
use App\Ark\Platform\ShopStatus;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * M2 — Real Shop ownership for Cloud Funnel.
 *
 * Creates / updates platform Shop for one owner. No Tenant, ProvisioningRequest, Stripe, or DNS.
 */
final class CloudShop
{
    public function forUser(User $user): ?Shop
    {
        return $user->ownedShop;
    }

    /**
     * Persist Shop for the owner from funnel draft (display name + slug).
     *
     * @throws ValidationException
     */
    public function claimOrCreate(User $user, string $displayName, string $slug): Shop
    {
        $displayName = trim($displayName);
        $slug = Str::lower(trim($slug));

        if ($displayName === '' || $slug === '') {
            throw ValidationException::withMessages([
                'shop_name' => 'Shop name and workspace slug are required.',
            ]);
        }

        $existing = $this->forUser($user);
        if ($existing !== null) {
            $this->assertSlugAvailable($slug, $existing->id);
            $existing->forceFill([
                'display_name' => $displayName,
                'legal_name' => $existing->legal_name ?: $displayName,
                'slug' => $slug,
                'email' => $user->email,
            ])->save();

            return $existing->fresh();
        }

        $this->assertSlugAvailable($slug);

        $shop = new Shop;
        $shop->forceFill([
            'uuid' => (string) Str::uuid(),
            'owner_user_id' => $user->id,
            'slug' => $slug,
            'legal_name' => $displayName,
            'display_name' => $displayName,
            'email' => $user->email,
            'timezone' => config('app.timezone'),
            'status' => ShopStatus::Prospect,
        ]);
        $shop->save();

        return $shop;
    }

    public function updateSlug(Shop $shop, string $slug): Shop
    {
        $slug = Str::lower(trim($slug));
        $this->assertSlugAvailable($slug, $shop->id);
        $shop->forceFill(['slug' => $slug])->save();

        return $shop->fresh();
    }

    public function updateDisplayName(Shop $shop, string $displayName): Shop
    {
        $displayName = trim($displayName);
        $shop->forceFill([
            'display_name' => $displayName,
            'legal_name' => $shop->legal_name ?: $displayName,
        ])->save();

        return $shop->fresh();
    }

    /**
     * Session / draft shape used by existing funnel views — projected from Shop.
     *
     * @return array{shop_name: string, slug: string, owner_name: string, email: string, shop_id: int}
     */
    public function funnelProjection(Shop $shop, User $owner): array
    {
        return [
            'shop_name' => $shop->display_name,
            'slug' => $shop->slug,
            'owner_name' => $owner->name,
            'email' => $owner->email,
            'shop_id' => $shop->id,
        ];
    }

    private function assertSlugAvailable(string $slug, ?int $ignoreShopId = null): void
    {
        $query = Shop::query()->where('slug', $slug);
        if ($ignoreShopId !== null) {
            $query->where('id', '!=', $ignoreShopId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'slug' => 'That workspace address is already taken.',
            ]);
        }
    }
}
