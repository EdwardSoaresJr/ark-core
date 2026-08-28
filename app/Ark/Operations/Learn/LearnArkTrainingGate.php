<?php

namespace App\Ark\Operations\Learn;

use App\Ark\Operations\Settings\ShopSettings;
use App\Models\User;

final class LearnArkTrainingGate
{
    public static function isShopEnabled(): bool
    {
        return ShopSettings::current()->learn_training_gate_enabled !== false;
    }

    public static function ownerBypasses(User $user): bool
    {
        return $user->isMasterAdmin();
    }

    public static function isActiveFor(User $user): bool
    {
        if (ArkademyUrls::isCutover()) {
            return false;
        }

        if (! LearnArkCurriculum::appliesTo($user)) {
            return false;
        }

        if (! self::isShopEnabled()) {
            return false;
        }

        if (self::ownerBypasses($user)) {
            return false;
        }

        return true;
    }
}
