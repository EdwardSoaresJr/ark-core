<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Operations\RepairOrders\RepairOrderVisitMode;
use App\Ark\Operations\Workstations\Workstation;

/**
 * Applies operational profile defaults onto existing ShopSettings columns.
 * Does not create stations, users, or parallel authority.
 */
final class ApplyOperationalProfileDefaults
{
    public const MOBILE_WORKSTATION_NAME = 'Mobile unit';

    public const DEFAULT_SHOP_WORKSTATION_NAME = 'Main Shop';

    /**
     * @return array{profile: OperationalProfile, changed: list<string>}
     */
    public function apply(OperationalProfile $profile, ?ShopSettings $settings = null): array
    {
        $settings ??= ShopSettings::current();
        $payload = $this->defaultsFor($profile);
        $changed = [];

        foreach ($payload as $column => $value) {
            if ($settings->{$column} != $value) {
                $changed[] = $column;
            }
        }

        $settings->update([
            ...$payload,
            'operational_profile' => $profile->value,
        ]);

        if ($this->alignDefaultWorkstation($profile)) {
            $changed[] = 'workstation_name';
        }

        return [
            'profile' => $profile,
            'changed' => $changed,
        ];
    }

    /**
     * Keep conventional shops on "Main Shop"; mobile profiles rename that stock label only.
     */
    private function alignDefaultWorkstation(OperationalProfile $profile): bool
    {
        $station = Workstation::query()->orderBy('id')->first();
        if ($station === null) {
            return false;
        }

        if ($profile === OperationalProfile::MobileMechanic
            && $station->name === self::DEFAULT_SHOP_WORKSTATION_NAME) {
            $station->forceFill(['name' => self::MOBILE_WORKSTATION_NAME])->save();

            return true;
        }

        if ($profile !== OperationalProfile::MobileMechanic
            && $station->name === self::MOBILE_WORKSTATION_NAME) {
            $station->forceFill(['name' => self::DEFAULT_SHOP_WORKSTATION_NAME])->save();

            return true;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultsFor(OperationalProfile $profile): array
    {
        return match ($profile) {
            OperationalProfile::RepairShop => [
                'appointments_enabled' => true,
                'default_visit_mode' => RepairOrderVisitMode::DropOff->value,
                'qz_printing_enabled' => true,
                'learn_training_gate_enabled' => false,
            ],
            OperationalProfile::SoloShop => [
                'appointments_enabled' => false,
                'default_visit_mode' => RepairOrderVisitMode::DropOff->value,
                'qz_printing_enabled' => false,
                'learn_training_gate_enabled' => false,
            ],
            OperationalProfile::MobileMechanic => [
                'appointments_enabled' => true,
                'default_visit_mode' => RepairOrderVisitMode::OnSite->value,
                'qz_printing_enabled' => false,
                'learn_training_gate_enabled' => false,
            ],
        };
    }
}
