<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Schema;

/**
 * Canonical inspection obligation for an RO.
 * Coverage / evidence / publication use other projections — do not re-derive policy there.
 */
final class InspectionRequirementResolver
{
    public const SHOP_SETTING_KEY = 'inspection_lifecycle_policy';

    public function forRepairOrder(RepairOrder $repairOrder): InspectionRequirementDecision
    {
        $repairOrder->loadMissing([]);

        if ($this->isWaived($repairOrder)) {
            return new InspectionRequirementDecision(
                requirement: InspectionRequirement::Required,
                source: 'waived',
                waived: true,
                waiveReason: $this->waiveReason($repairOrder),
                detail: 'Required inspection waived for this repair order.',
            );
        }

        $override = $this->repairOrderOverride($repairOrder);
        if ($override instanceof InspectionRequirement) {
            return new InspectionRequirementDecision(
                requirement: $override,
                source: 'repair_order_override',
                detail: 'Set on this repair order.',
            );
        }

        $shop = $this->shopPolicy();

        return new InspectionRequirementDecision(
            requirement: $shop,
            source: $shop === InspectionRequirement::Optional ? 'shop_default' : 'shop_setting',
            detail: $shop === InspectionRequirement::Optional
                ? 'Inspections are optional for this shop.'
                : 'Shop inspection policy: '.$shop->label().'.',
        );
    }

    public function shopPolicy(): InspectionRequirement
    {
        if (! Schema::hasTable('shop_settings')) {
            return InspectionRequirement::Optional;
        }

        if (! Schema::hasColumn('shop_settings', self::SHOP_SETTING_KEY)) {
            return InspectionRequirement::Optional;
        }

        $raw = ShopSettings::current()->{self::SHOP_SETTING_KEY} ?? null;
        if (! is_string($raw) || $raw === '') {
            return InspectionRequirement::Optional;
        }

        return InspectionRequirement::tryFrom($raw) ?? InspectionRequirement::Optional;
    }

    private function repairOrderOverride(RepairOrder $repairOrder): ?InspectionRequirement
    {
        if (! Schema::hasColumn($repairOrder->getTable(), 'inspection_lifecycle_policy')) {
            return null;
        }

        $raw = $repairOrder->getAttribute('inspection_lifecycle_policy');
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        return InspectionRequirement::tryFrom($raw);
    }

    private function isWaived(RepairOrder $repairOrder): bool
    {
        if (! Schema::hasColumn($repairOrder->getTable(), 'inspection_waived_at')) {
            return false;
        }

        return $repairOrder->getAttribute('inspection_waived_at') !== null;
    }

    private function waiveReason(RepairOrder $repairOrder): ?string
    {
        if (! Schema::hasColumn($repairOrder->getTable(), 'inspection_waived_reason')) {
            return null;
        }

        $reason = $repairOrder->getAttribute('inspection_waived_reason');

        return is_string($reason) && $reason !== '' ? $reason : null;
    }
}
