<?php

namespace App\Ark\Operations\Learn;

use App\Models\User;

/**
 * Shop-wide ARKademy / Learn training gate - RETIRED.
 *
 * Doctrine:
 * - Learning content is available by default to authorized users.
 * - Training gates are explicit employee-specific requirements (future).
 * - Administrators may later assign: Employee × Learning Module × Operational Gate.
 * - Learning content itself is not globally gated.
 *
 * Future seam (not implemented here):
 * Core/authorization/process systems own who must complete what and which
 * capability is blocked. ARKademy owns modules, lessons, and completion evidence.
 * Do not reintroduce a shop-wide "must finish all required guides before workboard"
 * switch as the product model.
 */
final class LearnArkTrainingGate
{
    /**
     * Historical shop setting still exists for audit; it no longer enforces a gate.
     */
    public static function isShopEnabled(): bool
    {
        return false;
    }

    public static function ownerBypasses(User $user): bool
    {
        return $user->isMasterAdmin();
    }

    /**
     * Global gate is never active. Progress tracking and Learn surfaces remain.
     */
    public static function isActiveFor(User $user): bool
    {
        return false;
    }
}
