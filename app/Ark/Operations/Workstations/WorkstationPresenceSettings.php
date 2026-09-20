<?php

namespace App\Ark\Operations\Workstations;

use App\Ark\Operations\Settings\ShopSettings;

final class WorkstationPresenceSettings
{
    public const DEFAULT_IDLE_LOCK_MINUTES = 5;

    public function __construct(
        private readonly ShopSettings $settings,
    ) {}

    public static function fromShopSettings(?ShopSettings $settings = null): self
    {
        return new self($settings ?? ShopSettings::current());
    }

    public function idleLockMinutes(): int
    {
        $minutes = $this->settings->workstation_idle_lock_minutes;

        if ($minutes === null) {
            return self::DEFAULT_IDLE_LOCK_MINUTES;
        }

        return max(0, (int) $minutes);
    }

    public function idleLockEnabled(): bool
    {
        return $this->idleLockMinutes() > 0;
    }
}
