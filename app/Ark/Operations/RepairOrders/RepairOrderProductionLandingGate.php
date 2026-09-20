<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Workboard\WorkboardLens;
use App\Models\User;

/**
 * Pure technician production RO landing — not Estimate Review.
 * Admin/advisor (including multi-role) keep advisor Estimate Review.
 */
final class RepairOrderProductionLandingGate
{
    public static function applies(?User $user): bool
    {
        return WorkboardLens::forUser($user) === WorkboardLens::TECHNICIAN;
    }
}
