<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\Settings\ShopSettings;

final class ApplySquareTerminalPairingAction
{
    public function execute(string $deviceId): void
    {
        $deviceId = trim($deviceId);

        if ($deviceId === '') {
            return;
        }

        ShopSettings::current()->update([
            'square_terminal_device_id' => $deviceId,
            'square_terminal_enabled' => true,
        ]);
    }
}
