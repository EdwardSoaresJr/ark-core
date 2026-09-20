<?php

namespace App\Ark\Runtime\Ecosystem;

use App\Ark\Operations\Learn\ArkademyUrls;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Identity\Oidc\OidcProduct;
use App\Ark\Runtime\Identity\Oidc\OidcProductAccessResolver;
use App\Models\User;

final class EcosystemSwitcherProjection
{
    public function __construct(
        private readonly OidcProductAccessResolver $productAccess,
    ) {}

    /**
     * @return list<array{
     *     id: string,
     *     label: string,
     *     url: string,
     *     external: bool,
     *     current: bool
     * }>
     */
    public function forUser(?User $user, EcosystemProduct $currentSurface = EcosystemProduct::Operations): array
    {
        if ($user === null) {
            return [];
        }

        $items = [];

        if ($this->canAccessOperations($user)) {
            $items[] = $this->item(
                EcosystemProduct::Operations,
                config('ark-ecosystem.operations_url'),
                $currentSurface === EcosystemProduct::Operations,
            );
        }

        if ($this->canAccessArkademy($user)) {
            $items[] = $this->item(
                EcosystemProduct::Arkademy,
                $this->arkademyHomeUrl(),
                $currentSurface === EcosystemProduct::Arkademy,
            );
        }

        if ($this->canAccessPlatform($user)) {
            $items[] = $this->item(
                EcosystemProduct::Platform,
                config('ark-ecosystem.platform_url'),
                $currentSurface === EcosystemProduct::Platform,
            );
        }

        return $items;
    }

    public function shouldRender(?User $user, EcosystemProduct $currentSurface = EcosystemProduct::Operations): bool
    {
        return count($this->forUser($user, $currentSurface)) > 1;
    }

    private function canAccessOperations(User $user): bool
    {
        return $user->can(ArkCapability::ProductionAccess->value)
            || $user->can(ArkCapability::OperationsAccess->value)
            || $this->productAccess->canAccessProduct($user, OidcProduct::ArkSms->value);
    }

    private function canAccessArkademy(User $user): bool
    {
        return $this->productAccess->canAccessProduct($user, 'arkademy');
    }

    private function canAccessPlatform(User $user): bool
    {
        return $user->isMasterAdmin() || $user->hasRole('admin');
    }

  /**
     * @return array{id: string, label: string, url: string, external: bool, current: bool}
     */
    private function item(EcosystemProduct $product, string $url, bool $current): array
    {
        return [
            'id' => $product->value,
            'label' => $product->label(),
            'url' => $url,
            'external' => ! $current,
            'current' => $current,
        ];
    }

    private function arkademyHomeUrl(): string
    {
        if (ArkademyUrls::isCutover()) {
            return ArkademyUrls::homeUrl();
        }

        $base = rtrim((string) config('ark-ecosystem.arkademy_url'), '/');
        $slug = (string) config('ark-ecosystem.shelf_slug', 'shop-in-a-box');

        return "{$base}/shelves/{$slug}";
    }
}
