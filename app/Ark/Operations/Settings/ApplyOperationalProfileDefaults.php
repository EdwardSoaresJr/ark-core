<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Operations\RepairOrders\RepairOrderVisitMode;

/**
 * Applies operational profile defaults onto existing ShopSettings columns.
 * Does not create stations, users, or parallel authority.
 */
final class ApplyOperationalProfileDefaults
{
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

        return [
            'profile' => $profile,
            'changed' => $changed,
        ];
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
                'learn_training_gate_enabled' => true,
            ],
            OperationalProfile::SoloShop => [
                'appointments_enabled' => false,
                'default_visit_mode' => RepairOrderVisitMode::DropOff->value,
                'qz_printing_enabled' => false,
                'learn_training_gate_enabled' => false,
            ],
            OperationalProfile::MobileMechanic => [
                'appointments_enabled' => true,
                'default_visit_mode' => RepairOrderVisitMode::WaitingHere->value,
                'qz_printing_enabled' => false,
                'learn_training_gate_enabled' => false,
            ],
        };
    }
}
