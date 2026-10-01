<?php

namespace App\Ark\Runtime\Identity\Oidc;

use App\Models\User;

final class OidcProductAccessResolver
{
    /**
     * @return list<string>
     */
    public function resolve(User $user): array
    {
        $products = [];

        foreach ($user->roles->pluck('name') as $roleName) {
            foreach (OidcProduct::staffDefaultsForRole((string) $roleName) as $slug) {
                $products[OidcProduct::normalizeSlug($slug)] = true;
            }
        }

        foreach ($user->productAccessOverrides as $override) {
            $slug = OidcProduct::normalizeSlug($override->product_slug);

            if ($override->granted) {
                $products[$slug] = true;
            } else {
                unset($products[$slug]);
            }
        }

        return array_values(array_keys($products));
    }

    public function canAccessProduct(User $user, string $productSlug): bool
    {
        return in_array(OidcProduct::normalizeSlug($productSlug), $this->resolve($user), true);
    }
}
