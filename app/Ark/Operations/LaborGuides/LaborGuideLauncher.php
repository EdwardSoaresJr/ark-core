<?php

namespace App\Ark\Operations\LaborGuides;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderShopReference;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use Illuminate\Support\Str;

/**
 * Opens AllData or ProDemand in the browser and copies VIN for manual paste.
 *
 * These vendors do not consume ARK query-string vehicle context on generic login URLs.
 */
final class LaborGuideLauncher
{
    public function configured(LaborGuideProvider $provider): bool
    {
        return rtrim($this->baseUrl($provider), '/') !== '';
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

    public function launchUrl(
        RepairOrder $repairOrder,
        LaborGuideProvider $provider,
        ?int $concernId = null,
    ): ?string {
        if (! $this->configured($provider)) {
            return null;
        }

        $target = rtrim($this->baseUrl($provider), '/');
        $path = trim($this->loginPath($provider), '/');

        if ($path !== '') {
            $target .= '/'.$path;
        }

        return $target;
    }

    /**
     * Clipboard payload for labor guide launch — VIN only, never RO context.
     */
    public function clipboardVin(RepairOrder $repairOrder): ?string
    {
        return $this->vinForRepairOrder($repairOrder);
    }

    public function handoffNotice(RepairOrder $repairOrder, LaborGuideProvider $provider, ?int $concernId = null): string
    {
        $vin = $this->vinForRepairOrder($repairOrder);
        $repairOrder->loadMissing('vehicle');

        $ymm = trim(implode(' ', array_filter([
            $repairOrder->vehicle?->year,
            $repairOrder->vehicle?->make,
            $repairOrder->vehicle?->model,
        ])));

        $segments = $vin !== null
            ? [
                $provider->label().': VIN '.$vin.' copied to clipboard.',
                'After sign-in, paste the VIN into the guide vehicle search.',
            ]
            : [
                $provider->label().': no VIN on this repair order.',
                'After sign-in, search by year, make, and model in the guide.',
            ];

        $segments[] = 'These guides do not load vehicle data from ARK URLs yet.';

        if ($ymm !== '') {
            $segments[] = 'Vehicle: '.$ymm.'.';
        }

        if ($concern = $this->concernSummary($repairOrder, $concernId)) {
            $segments[] = 'Scope: '.$concern.'.';
        }

        return implode(' ', $segments);
    }

    /**
     * Advisor-facing scope context for internal tooling — not for clipboard handoff.
     */
    public function clipboardContext(RepairOrder $repairOrder, ?int $concernId = null): string
    {
        $segments = [
            'RO '.RepairOrderShopReference::cartReference($repairOrder),
        ];

        if ($vin = $this->vinForRepairOrder($repairOrder)) {
            $segments[] = 'VIN '.$vin;
        }

        $repairOrder->loadMissing('vehicle');

        $ymm = trim(implode(' ', array_filter([
            $repairOrder->vehicle?->year,
            $repairOrder->vehicle?->make,
            $repairOrder->vehicle?->model,
        ])));

        if ($ymm !== '') {
            $segments[] = $ymm;
        }

        if ($concern = $this->concernSummary($repairOrder, $concernId)) {
            $segments[] = 'Scope: '.$concern;
        }

        return implode(' | ', $segments);
    }

    public function blockedReason(RepairOrder $repairOrder, LaborGuideProvider $provider): string
    {
        if (! $this->configured($provider)) {
            return $provider->label().' launch URL is not configured for this shop.';
        }

        return $provider->label().' labor guide is unavailable.';
    }

    private function concernSummary(RepairOrder $repairOrder, ?int $concernId): ?string
    {
        if ($concernId === null || $concernId <= 0) {
            return null;
        }

        $summary = RepairOrderConcern::query()
            ->where('repair_order_id', $repairOrder->id)
            ->whereKey($concernId)
            ->value('summary');

        if (! filled($summary)) {
            return null;
        }

        return Str::limit(trim((string) $summary), 120, '…');
    }

    private function baseUrl(LaborGuideProvider $provider): string
    {
        return (string) config('services.labor_guides.'.$provider->value.'.base_url', '');
    }

    private function loginPath(LaborGuideProvider $provider): string
    {
        return (string) config('services.labor_guides.'.$provider->value.'.login_path', '');
    }
}
