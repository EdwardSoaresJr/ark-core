<?php

namespace App\Ark\Import;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ImportedRepairOrderShopNumberBackfiller
{
    private const STAGING_OFFSET = 1_000_000_000;

    public function __construct(
        private readonly LegacyArkSmsReader $reader,
    ) {}

    /**
     * @return array{
     *     updated: int,
     *     imported: int,
     *     native: int,
     *     skipped: int,
     *     unmatched: int,
     *     dry_run: bool,
     * }
     */
    public function run(bool $dryRun = false): array
    {
        $legacyByFingerprint = [];

        foreach ($this->reader->repairOrders() as $legacy) {
            $fingerprint = $this->legacyFingerprint($legacy);

            if ($fingerprint !== null && ! isset($legacyByFingerprint[$fingerprint])) {
                $legacyByFingerprint[$fingerprint] = $legacy;
            }
        }

        /** @var array<int, int> $updates */
        $updates = [];
        $nativeReassign = [];
        $skipped = 0;
        $unmatched = 0;
        $imported = 0;

        RepairOrder::query()
            ->with(['customer', 'vehicle'])
            ->orderBy('id')
            ->each(function (RepairOrder $repairOrder) use (
                $legacyByFingerprint,
                &$updates,
                &$nativeReassign,
                &$skipped,
                &$unmatched,
                &$imported,
            ): void {
                $targetShopNumber = $this->resolveLegacyShopNumber(
                    $repairOrder,
                    $legacyByFingerprint,
                );

                if ($targetShopNumber !== null) {
                    if ((int) $repairOrder->repair_order_id === $targetShopNumber) {
                        $skipped++;

                        return;
                    }

                    $updates[(int) $repairOrder->id] = $targetShopNumber;
                    $imported++;

                    return;
                }

                if (! $this->needsNativeShopNumber($repairOrder)) {
                    $skipped++;

                    return;
                }

                if ($this->hasLegacyLineage($repairOrder) || $this->hasInterimShopNumber($repairOrder)) {
                    $nativeReassign[] = (int) $repairOrder->id;

                    return;
                }

                $unmatched++;
            });

        $reserved = $this->reservedShopNumbers($updates, $legacyByFingerprint);
        $nextShopNumber = max((int) RepairOrder::query()->max('repair_order_id'), 0);

        foreach ($nativeReassign as $repairOrderPk) {
            do {
                $nextShopNumber++;
            } while ($reserved->contains($nextShopNumber));

            $updates[$repairOrderPk] = $nextShopNumber;
            $reserved->push($nextShopNumber);
        }

        $native = count($nativeReassign);
        $updated = $this->applyUpdates($updates, $dryRun);

        return [
            'updated' => $updated,
            'imported' => $imported,
            'native' => $native,
            'skipped' => $skipped,
            'unmatched' => $unmatched,
            'dry_run' => $dryRun,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $legacyByFingerprint
     */
    private function resolveLegacyShopNumber(
        RepairOrder $repairOrder,
        array $legacyByFingerprint,
    ): ?int {
        $fingerprint = $this->repairOrderFingerprint($repairOrder);

        if ($fingerprint === null) {
            return null;
        }

        $legacy = $legacyByFingerprint[$fingerprint] ?? null;

        if ($legacy === null) {
            return null;
        }

        $shopNumber = LegacyRepairOrderRecord::shopNumber($legacy);

        return $shopNumber > 0 ? $shopNumber : null;
    }

    private function hasLegacyLineage(RepairOrder $repairOrder): bool
    {
        return filled($repairOrder->customer?->legacy_arksms_customer_id)
            && filled($repairOrder->vehicle?->legacy_arksms_vehicle_id);
    }

    private function needsNativeShopNumber(RepairOrder $repairOrder): bool
    {
        if ($repairOrder->repair_order_id === null) {
            return true;
        }

        if ((int) $repairOrder->repair_order_id === (int) $repairOrder->id) {
            return true;
        }

        return $this->hasInterimShopNumber($repairOrder);
    }

    /** RO numbers assigned before legacy shop_number import (internal id era). */
    private function hasInterimShopNumber(RepairOrder $repairOrder): bool
    {
        $shopNumber = (int) $repairOrder->repair_order_id;

        return $shopNumber > 0 && $shopNumber < 1000;
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    private function legacyFingerprint(array $legacy): ?string
    {
        $legacyCustomerId = (int) ($legacy['customer_id'] ?? 0);
        $legacyVehicleId = (int) ($legacy['vehicle_id'] ?? 0);

        if ($legacyCustomerId <= 0 || $legacyVehicleId <= 0) {
            return null;
        }

        $openedAt = LegacyRepairOrderTimeline::openedAt($legacy);

        return $this->fingerprint($legacyCustomerId, $legacyVehicleId, $openedAt);
    }

    private function repairOrderFingerprint(RepairOrder $repairOrder): ?string
    {
        $legacyCustomerId = (int) ($repairOrder->customer?->legacy_arksms_customer_id ?? 0);
        $legacyVehicleId = (int) ($repairOrder->vehicle?->legacy_arksms_vehicle_id ?? 0);

        if ($legacyCustomerId <= 0 || $legacyVehicleId <= 0) {
            return null;
        }

        $openedAt = $repairOrder->opened_at ?? $repairOrder->created_at;

        return $this->fingerprint($legacyCustomerId, $legacyVehicleId, $openedAt);
    }

    private function fingerprint(int $legacyCustomerId, int $legacyVehicleId, ?Carbon $openedAt): string
    {
        return $legacyCustomerId
            .'|'
            .$legacyVehicleId
            .'|'
            .($openedAt?->utc()->toDateTimeString() ?? 'none');
    }

    /**
     * @param  array<int, int>  $updates
     * @param  array<string, array<string, mixed>>  $legacyByFingerprint
     * @return Collection<int, int>
     */
    private function reservedShopNumbers(array $updates, array $legacyByFingerprint): Collection
    {
        return collect($updates)
            ->values()
            ->merge(collect($legacyByFingerprint)->map(
                fn (array $legacy): int => LegacyRepairOrderRecord::shopNumber($legacy),
            ))
            ->merge(RepairOrder::query()->pluck('repair_order_id'))
            ->map(fn (mixed $value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->values();
    }

    /**
     * @param  array<int, int>  $updates
     */
    private function applyUpdates(array $updates, bool $dryRun): int
    {
        if ($updates === []) {
            return 0;
        }

        if ($dryRun) {
            return count($updates);
        }

        DB::transaction(function () use ($updates): void {
            foreach ($updates as $repairOrderPk => $shopNumber) {
                DB::table('repair_orders')
                    ->where('id', $repairOrderPk)
                    ->update(['repair_order_id' => $repairOrderPk + self::STAGING_OFFSET]);
            }

            foreach ($updates as $repairOrderPk => $shopNumber) {
                DB::table('repair_orders')
                    ->where('id', $repairOrderPk)
                    ->update(['repair_order_id' => $shopNumber]);
            }
        });

        return count($updates);
    }
}
