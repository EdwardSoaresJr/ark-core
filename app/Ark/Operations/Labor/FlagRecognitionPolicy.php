<?php

namespace App\Ark\Operations\Labor;

/**
 * Shop compensation policy for when flagged production becomes recognized.
 * Not Colorado-law doctrine.
 */
final class FlagRecognitionPolicy
{
    public const KEY = 'concern_production_completed';

    public const VERSION = 1;

    public const TECHNICIAN_ATTRIBUTION_RO_ASSIGNEE = 'repair_order_assigned_technician';

    public static function label(): string
    {
        return 'Concern production completed (v'.self::VERSION.')';
    }
}
