<?php

namespace App\Ark\Platform;

use App\Ark\Operations\Settings\ShopSettings;

/**
 * @deprecated Voice policy is authored on Platform only. Core must not sync telephony configuration.
 */
final class ReportVoiceInboundPolicyAction
{
    public function execute(?ShopSettings $settings = null): bool
    {
        return false;
    }
}
