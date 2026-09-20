<?php

namespace App\Ark\Operations\Portal;

final class ResolveRepairOrderPortalAccessAction
{
    public function byPublicCode(string $code): ?RepairOrderPortalAccess
    {
        $code = strtolower(trim($code));
        if ($code === '') {
            return null;
        }

        $access = RepairOrderPortalAccess::query()
            ->where('public_code', $code)
            ->first();

        if ($access === null || ! $access->isActive()) {
            return null;
        }

        return $access;
    }
}
