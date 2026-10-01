<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Platform\Parts\ManagedPartsGate;
use App\Models\User;

final class PartsCatalogConnections
{
    public function __construct(
        private readonly PartsTechCatalogLauncher $partsTech,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function forRepairOrder(RepairOrder $repairOrder, ?User $user = null): array
    {
        return $this->rows($user, $repairOrder);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forAdvisor(?User $user): array
    {
        return $this->rows($user, null);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(?User $user, ?RepairOrder $repairOrder): array
    {
        $shopCustoms = PartsCatalogLinks::customForShop();
        $userCustoms = PartsCatalogLinks::customForUser($user);
        $repairLinkUrl = $this->launchUrl(PartsCatalogProvider::RepairLink, $user);
        $nexpartUrl = $this->launchUrl(PartsCatalogProvider::Nexpart, $user);
        $catalogs = [];
        $platformCatalog = ManagedPartsGate::platformCatalog();
        $include = $this->partsTechIsIntegratedCatalog()
            || (ManagedPartsGate::platformConnected() && ! $platformCatalog)
            || $repairLinkUrl !== null
            || $nexpartUrl !== null
            || $shopCustoms !== []
            || $userCustoms !== [];

        if (! $include) {
            return [];
        }

        if ($this->partsTechIsIntegratedCatalog()) {
            $catalogs[] = $this->partsTechRow($repairOrder, $user);
        } elseif (ManagedPartsGate::platformConnected() && ! $platformCatalog) {
            $fallbackUrl = ShopIntegrationCredentials::normalizedHttpsUrl(
                ShopIntegrationCredentials::forCurrentShop()->partsTechBaseUrl(),
            );

            if ($fallbackUrl !== null) {
                $catalogs[] = $this->externalRow(
                    PartsCatalogProvider::PartsTech->value,
                    PartsCatalogProvider::PartsTech->label(),
                    $fallbackUrl,
                    PartsCatalogLinks::MODE_LINK,
                    $repairOrder,
                    'Add a PartsTech address on Settings → Parts catalogs.',
                    $user,
                );
            }
        }

        if ($repairLinkUrl !== null) {
            $catalogs[] = $this->externalRow(
                PartsCatalogProvider::RepairLink->value,
                PartsCatalogProvider::RepairLink->label(),
                $repairLinkUrl,
                PartsCatalogLinks::MODE_LINK,
                $repairOrder,
                'Add a RepairLink address on Profile → Catalogs or Settings → Parts catalogs.',
                $user,
            );
        }

        if ($nexpartUrl !== null) {
            $catalogs[] = $this->externalRow(
                PartsCatalogProvider::Nexpart->value,
                PartsCatalogProvider::Nexpart->label(),
                $nexpartUrl,
                $platformCatalog ? PartsCatalogLinks::MODE_CATALOG : PartsCatalogLinks::MODE_LINK,
                $repairOrder,
                'Add a Nexpart address on Profile → Catalogs or Settings → Parts catalogs.',
                $user,
            );
        }

        $extras = [];

        foreach ($shopCustoms as $link) {
            $extras[$link['key']] = $link;
        }

        foreach ($userCustoms as $link) {
            $extras[$link['key']] = $link;
        }

        $seen = array_column($catalogs, 'key');

        foreach ($extras as $link) {
            if (in_array($link['key'], $seen, true) || $link['url'] === null) {
                continue;
            }

            $seen[] = $link['key'];
            $catalogs[] = $this->externalRow(
                $link['key'],
                $link['label'],
                $link['url'],
                $link['mode'] ?? PartsCatalogLinks::MODE_LINK,
                $repairOrder,
                'Add an https:// address for '.$link['label'].' on Profile → Catalogs or Settings → Parts catalogs.',
                $user,
                $link['color'] ?? null,
            );
        }

        return $catalogs;
    }

    private function partsTechIsIntegratedCatalog(): bool
    {
        if (ManagedPartsGate::platformCatalog()) {
            return true;
        }

        return ! ManagedPartsGate::platformConnected() && $this->partsTech->configured();
    }

    private function launchUrl(PartsCatalogProvider $provider, ?User $user): ?string
    {
        $override = PartsCatalogLinks::urlForUser($user, $provider->value);

        if ($override !== null) {
            return $override;
        }

        return match ($provider) {
            PartsCatalogProvider::RepairLink => ShopIntegrationCredentials::forCurrentShop()->repairLinkLaunchUrl(),
            PartsCatalogProvider::Nexpart => ShopIntegrationCredentials::forCurrentShop()->nexpartLaunchUrl(),
            PartsCatalogProvider::PartsTech => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function partsTechRow(?RepairOrder $repairOrder, ?User $user): array
    {
        $url = $repairOrder instanceof RepairOrder
            ? $this->partsTech->launchUrl($repairOrder)
            : null;
        $canOpen = $repairOrder instanceof RepairOrder
            ? $url !== null
            : $this->partsTech->configured();
        $color = $this->colorFor(
            PartsCatalogProvider::PartsTech->value,
            PartsCatalogProvider::PartsTech->label(),
            $user,
            null,
        );

        return [
            'key' => PartsCatalogProvider::PartsTech->value,
            'label' => PartsCatalogProvider::PartsTech->label(),
            'open_label' => PartsCatalogProvider::PartsTech->openLabel(),
            'cart_label' => PartsCatalogProvider::PartsTech->cartLabel(),
            'can_open' => $canOpen,
            'can_pull_quote' => $repairOrder instanceof RepairOrder
                ? $this->partsTech->usesRemoteCartPreparation($user)
                : $this->partsTech->configured(),
            'kind' => PartsCatalogProvider::PartsTech->value,
            'mode' => PartsCatalogLinks::MODE_CATALOG,
            'color' => $color,
            'button_class' => PartsCatalogButtonColor::className($color),
            'swatch_class' => 'ops-catalog-swatch ops-catalog-swatch--'.$color,
            'blocked_reason' => ! $canOpen && $repairOrder instanceof RepairOrder
                ? $this->partsTech->blockedReason($repairOrder)
                : (! $canOpen ? 'PartsTech is not connected.' : null),
            'po_number' => $repairOrder instanceof RepairOrder
                ? $this->partsTech->poNumber($repairOrder)
                : null,
            'launch' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function externalRow(
        string $key,
        string $label,
        ?string $url,
        string $mode,
        ?RepairOrder $repairOrder,
        string $missingUrlReason,
        ?User $user = null,
        ?string $storedColor = null,
    ): array {
        $vin = $repairOrder instanceof RepairOrder
            ? $this->partsTech->vinForRepairOrder($repairOrder)
            : null;
        $mode = $mode === PartsCatalogLinks::MODE_CATALOG
            ? PartsCatalogLinks::MODE_CATALOG
            : PartsCatalogLinks::MODE_LINK;
        $color = $this->colorFor($key, $label, $user, $storedColor);

        return [
            'key' => $key,
            'label' => $label,
            'open_label' => 'Open '.$label,
            'cart_label' => null,
            'can_open' => $url !== null,
            'can_pull_quote' => false,
            'kind' => 'external',
            'mode' => $mode,
            'color' => $color,
            'button_class' => PartsCatalogButtonColor::className($color),
            'swatch_class' => 'ops-catalog-swatch ops-catalog-swatch--'.$color,
            'blocked_reason' => $url === null ? $missingUrlReason : null,
            'po_number' => null,
            'launch' => $url !== null ? [
                'url' => $url,
                'vin' => $vin,
                'notice' => $this->handoffNotice($label, $repairOrder, $vin),
                'windowName' => $mode === PartsCatalogLinks::MODE_LINK
                    ? '_blank'
                    : 'ark-parts-'.$key.($repairOrder instanceof RepairOrder ? '-ro-'.$repairOrder->repair_order_id : ''),
            ] : null,
        ];
    }

    private function colorFor(string $key, string $label, ?User $user, ?string $storedColor): string
    {
        $userColor = PartsCatalogLinks::rowForUser($user, $key)['color'] ?? null;

        return PartsCatalogButtonColor::resolve(
            $storedColor ?: $userColor,
            $key,
            $label,
        );
    }

    private function handoffNotice(string $label, ?RepairOrder $repairOrder, ?string $vin): string
    {
        $repairOrder?->loadMissing('vehicle');

        $ymm = trim(implode(' ', array_filter([
            $repairOrder?->vehicle?->year,
            $repairOrder?->vehicle?->make,
            $repairOrder?->vehicle?->model,
        ])));

        $segments = $vin !== null
            ? [
                $label.': VIN '.$vin.' copied to clipboard.',
                'After sign-in, paste the VIN into the catalog vehicle search.',
            ]
            : [
                $label.': no VIN on this repair order.',
                'After sign-in, search by year, make, and model.',
            ];

        $segments[] = $label.' does not load vehicle data from ARK URLs.';

        if ($ymm !== '') {
            $segments[] = 'Vehicle: '.$ymm.'.';
        }

        return implode(' ', $segments);
    }
}
