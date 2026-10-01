<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyCallFlowSettings;

class CommsPressureSettings
{
    public function __construct(
        private readonly TelephonyCallFlowSettings $callFlow,
    ) {}

    public static function fromShopSettings(?ShopSettings $settings = null): self
    {
        return new self(TelephonyCallFlowSettings::fromShopSettings($settings));
    }

    public function attentionGateEnabled(): bool
    {
        return $this->callFlow->attentionGateEnabled();
    }

    public function escalationEnabled(): bool
    {
        return $this->callFlow->escalationEnabled();
    }

    public function escalationDelayMinutes(): int
    {
        return $this->callFlow->escalationDelayMinutes();
    }

    public function escalationCooldownMinutes(): int
    {
        return $this->callFlow->escalationCooldownMinutes();
    }

    public function browserNotificationsEnabled(): bool
    {
        return $this->callFlow->browserNotificationsEnabled();
    }

    public function ownedPopupTimeoutSeconds(): int
    {
        return $this->callFlow->ownedPopupTimeoutSeconds();
    }
}
