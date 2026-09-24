<?php

namespace App\Ark\Operations\Scoreboard;

use App\Ark\Runtime\Authorization\ArkCapability;
use App\Models\User;

/**
 * Scoreboard access is five capabilities, granted by role.
 *
 * Admin and advisor receive all five. The advisor role already holds
 * financial.view, so the service advisor sees the full scoreboard without a
 * person-specific exception. A shop can later remove scoreboard.financial.view
 * from the advisor grant and keep queues, follow-up, and drilldowns.
 * Technician is unchanged and does not receive these capabilities.
 */
final class ShopOperatingScoreboardAccess
{
    /**
     * @return list<ArkCapability>
     */
    public static function capabilities(): array
    {
        return [
            ArkCapability::ScoreboardOperationalView,
            ArkCapability::ScoreboardFinancialView,
            ArkCapability::ScoreboardQueuesView,
            ArkCapability::ScoreboardFollowUpWork,
            ArkCapability::ScoreboardDrilldownView,
        ];
    }

    public static function allows(?User $user): bool
    {
        if ($user === null || ! $user->isActive()) {
            return false;
        }

        foreach (self::capabilities() as $capability) {
            if ($user->can($capability->value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{operational: bool, financial: bool, queues: bool, follow_up: bool, drilldown: bool, repair_orders: bool}
     */
    public static function for(?User $user): array
    {
        return [
            'operational' => self::can($user, ArkCapability::ScoreboardOperationalView),
            'financial' => self::can($user, ArkCapability::ScoreboardFinancialView),
            'queues' => self::can($user, ArkCapability::ScoreboardQueuesView),
            'follow_up' => self::can($user, ArkCapability::ScoreboardFollowUpWork),
            'drilldown' => self::can($user, ArkCapability::ScoreboardDrilldownView),
            'repair_orders' => self::can($user, ArkCapability::RepairOrdersView),
        ];
    }

    public static function canOpenFocus(?User $user, string $focus): bool
    {
        $access = self::for($user);

        if (! $access['drilldown']) {
            return false;
        }

        return match ($focus) {
            'opened', 'cycle', 'aging' => $access['operational'],
            'close', 'hours', 'parts', 'authorized', 'not_authorized', 'lost' => $access['financial'],
            'presentation', 'pickup', 'stalled', 'aging_authorized' => $access['queues'],
            'follow_up' => $access['follow_up'],
            default => false,
        };
    }

    private static function can(?User $user, ArkCapability $capability): bool
    {
        return $user !== null && $user->isActive() && $user->can($capability->value);
    }
}
