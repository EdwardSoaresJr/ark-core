<?php

namespace App\Ark\Operations\Business;

use App\Ark\Operations\ShopExcellence\OwnerWorkspaceAccess;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Models\User;

/**
 * Single policy for Business cockpit route + rail entry.
 *
 * OwnerWorkspaceAccess || financial.view
 */
final class BusinessWorkspaceAccess
{
    public static function allows(?User $user): bool
    {
        if ($user === null || ! $user->isActive()) {
            return false;
        }

        if (OwnerWorkspaceAccess::allows($user)) {
            return true;
        }

        return $user->can(ArkCapability::FinancialView->value);
    }
}
