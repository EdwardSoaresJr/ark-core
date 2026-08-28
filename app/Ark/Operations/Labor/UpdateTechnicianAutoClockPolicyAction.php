<?php

namespace App\Ark\Operations\Labor;

use App\Ark\Operations\ShopExcellence\OwnerWorkspaceAccess;
use App\Models\User;
use RuntimeException;

final class UpdateTechnicianAutoClockPolicyAction
{
    public function handle(
        User $target,
        bool $enabled,
        ?int $lunchMinutes,
        User $actor,
    ): User {
        if (! OwnerWorkspaceAccess::allows($actor)) {
            throw new RuntimeException('Only admins may configure auto clock.');
        }

        if (! TechnicianTimeClockProjection::canBeClocked($target)) {
            throw new RuntimeException('Auto clock only applies to technicians, advisors, and admins.');
        }

        if ($lunchMinutes !== null) {
            $lunchMinutes = max(0, min(240, $lunchMinutes));
        }

        $target->forceFill([
            'auto_clock_enabled' => $enabled,
            'auto_lunch_minutes' => $lunchMinutes,
        ])->save();

        return $target->fresh();
    }
}
