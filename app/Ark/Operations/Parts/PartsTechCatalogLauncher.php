<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Platform\Parts\PartsTechPlatformGateway;
use App\Models\User;

/**
 * Opens PartsTech in the browser. Hosted shops prepare carts through Platform.
 * Self-host still uses Core shop credentials and GraphQL.
 */
final class PartsTechCatalogLauncher
{
    public function __construct(
        private readonly ShopIntegrationCredentials $credentials,
        private readonly PartsTechCredentialsResolver $partsTechCredentials,
        private readonly PartsTechPlatformGateway $platform,
    ) {}

    /** Numeric shop repair order id (ARK display, search, JSON APIs). */
    public function repairOrderNumber(RepairOrder $repairOrder): string
    {
        return PartsTechShopReference::shopNumber($repairOrder);
    }

    /** PartsTech cart repairOrderNumber and URL reference (e.g. R1522). */
    public function partsTechCartReference(RepairOrder $repairOrder): string
    {
        return PartsTechShopReference::cartReference($repairOrder);
    }

    /** PartsTech PO field / purchaseOrderNumber (e.g. R1522). */
    public function poNumber(RepairOrder $repairOrder): string
    {
        return PartsTechShopReference::purchaseOrderNumber($repairOrder);
    }

    public function usesPlatform(): bool
    {
        return $this->platform->usesPlatform();
    }

    public function configured(): bool
    {
        if ($this->platform->usesPlatform()) {
            return $this->platform->isReady();
        }

        return $this->credentials->partsTechCatalogConfigured();
    }

    public function usesRemoteCartPreparation(?User $user = null): bool
    {
        if ($this->platform->usesPlatform()) {
            return $this->platform->isReady();
        }

        return $this->partsTechCredentials->quoteImportConfigured($user ?? auth()->user());
    }

    public function vinForRepairOrder(RepairOrder $repairOrder): ?string
    {
        $repairOrder->loadMissing('vehicle');

        $vin = $repairOrder->vehicle?->normalized_vin
            ?? $repairOrder->vehicle?->vin;

        if (! filled($vin)) {
            return null;
        }

        return strtoupper(trim((string) $vin));
    }

    /**
     * @return array{year: int, make: string, model: string, trim: ?string, engine: ?string}|null
     */
    public function ymmForRepairOrder(RepairOrder $repairOrder): ?array
    {
        $repairOrder->loadMissing('vehicle');

        $vehicle = $repairOrder->vehicle;

        if ($vehicle === null) {
            return null;
        }

        $year = (int) ($vehicle->year ?? 0);
        $make = trim((string) ($vehicle->make ?? ''));
        $model = trim((string) ($vehicle->model ?? ''));

        if ($year < 1900 || $make === '' || $model === '') {
            return null;
        }

        $trim = trim((string) ($vehicle->trim ?? ''));
        $engine = trim((string) ($vehicle->engine_display ?? $vehicle->engine ?? ''));

        return [
            'year' => $year,
            'make' => $make,
            'model' => $model,
            'trim' => $trim !== '' ? $trim : null,
            'engine' => $engine !== '' ? $engine : null,
        ];
    }

    public function ymmSearchPhrase(RepairOrder $repairOrder): ?string
    {
        $ymm = $this->ymmForRepairOrder($repairOrder);

        if ($ymm === null) {
            return null;
        }

        return trim(implode(' ', array_filter([
            (string) $ymm['year'],
            $ymm['make'],
            $ymm['model'],
            $ymm['trim'],
        ])));
    }

    public function hasVehicleIdentity(RepairOrder $repairOrder): bool
    {
        return $this->vinForRepairOrder($repairOrder) !== null
            || $this->ymmForRepairOrder($repairOrder) !== null;
    }

    public function launchUrl(RepairOrder $repairOrder, ?int $concernId = null): ?string
    {
        if (! $this->configured()) {
            return null;
        }

        $ymm = $this->ymmForRepairOrder($repairOrder) ?? [];
        $partsTechReference = $this->partsTechCartReference($repairOrder);
        $poNumber = $this->poNumber($repairOrder);

        $query = array_filter([
            'vin' => $this->vinForRepairOrder($repairOrder),
            'year' => $ymm['year'] ?? null,
            'make' => $ymm['make'] ?? null,
            'model' => $ymm['model'] ?? null,
            'poNumber' => $poNumber,
            'purchaseOrderNumber' => $poNumber,
            'repairOrderNumber' => $partsTechReference,
            'concern_id' => $concernId,
            'ark_launch' => (string) now()->timestamp,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        if ($this->platform->usesPlatform()) {
            return 'https://app.partstech.com?'.http_build_query($query);
        }

        $baseUrl = $this->credentials->partsTechBaseUrl();
        $catalogPath = $this->credentials->partsTechCatalogPath();
        $target = $catalogPath !== '' ? $baseUrl.'/'.$catalogPath : $baseUrl;

        return $target.'?'.http_build_query($query);
    }

    public function blockedReason(RepairOrder $repairOrder): string
    {
        if ($this->platform->usesPlatform()) {
            return $this->platform->blockedReason();
        }

        if (! $this->configured()) {
            return 'PartsTech credentials are not configured for this shop.';
        }

        return 'PartsTech catalog is unavailable.';
    }

    public function catalogWarning(RepairOrder $repairOrder): ?string
    {
        if ($this->hasVehicleIdentity($repairOrder)) {
            return null;
        }

        return 'No VIN or year/make/model on this RO — pick the vehicle manually in PartsTech. ARK still tags the cart with this RO number.';
    }
}
