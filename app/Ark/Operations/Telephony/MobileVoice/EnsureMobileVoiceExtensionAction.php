<?php

namespace App\Ark\Operations\Telephony\MobileVoice;

use App\Ark\Mobile\MobileDevice;
use App\Ark\Operations\Telephony\TelephonyExtension;
use App\Ark\Operations\Telephony\TelephonyExtensionDeviceType;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Mobile voice SIP identity — one enabled extension per registered mobile device.
 */
final class EnsureMobileVoiceExtensionAction
{
    private const EXTENSION_BASE = 8100;

    private const EXTENSION_SPAN = 900;

    public function execute(User $user, MobileDevice $device): TelephonyExtension
    {
        $existing = TelephonyExtension::query()
            ->where('mobile_device_id', $device->id)
            ->first();

        if ($existing instanceof TelephonyExtension) {
            return $existing;
        }

        $extensionNumber = $this->allocateExtensionNumber($device);

        return TelephonyExtension::query()->create([
            'extension' => $extensionNumber,
            'display_name' => 'Mobile · '.$device->device_name,
            'user_id' => $user->id,
            'mobile_device_id' => $device->id,
            'device_type' => TelephonyExtensionDeviceType::MobileApp,
            'enabled' => true,
            'location' => 'Mobile',
            'notes' => MobileVoiceIdentity::fromDevice($device),
            'secret' => Str::password(20, letters: true, numbers: true, symbols: false),
        ]);
    }

    public function forDevice(MobileDevice $device): ?TelephonyExtension
    {
        return TelephonyExtension::query()
            ->where('mobile_device_id', $device->id)
            ->where('enabled', true)
            ->first();
    }

    private function allocateExtensionNumber(MobileDevice $device): string
    {
        for ($offset = 0; $offset < self::EXTENSION_SPAN; $offset++) {
            $candidate = (string) (self::EXTENSION_BASE + (($device->id + $offset) % self::EXTENSION_SPAN));

            if (! TelephonyExtension::queryForExtensionNumber($candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException('No mobile voice extension numbers remain available.');
    }
}
