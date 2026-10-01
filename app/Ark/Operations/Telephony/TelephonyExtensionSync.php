<?php

namespace App\Ark\Operations\Telephony;

use App\Models\User;

class TelephonyExtensionSync
{
    /**
     * @param  array<int, array<string, mixed>>  $extensions
     */
    public function sync(array $extensions): void
    {
        TelephonyExtension::query()->delete();

        foreach (array_values($extensions) as $extension) {
            $number = trim((string) ($extension['extension'] ?? ''));

            if ($number === '') {
                continue;
            }

            $displayName = trim((string) ($extension['display_name'] ?? ''));

            if ($displayName === '') {
                continue;
            }

            $deviceType = TelephonyExtensionDeviceType::tryFrom((string) ($extension['device_type'] ?? ''))
                ?? TelephonyExtensionDeviceType::DeskPhone;

            $userId = filled($extension['user_id'] ?? null) ? (int) $extension['user_id'] : null;

            if ($userId !== null && ! User::query()->whereKey($userId)->exists()) {
                $userId = null;
            }

            TelephonyExtension::query()->create([
                'extension' => $number,
                'display_name' => $displayName,
                'user_id' => $userId,
                'device_type' => $deviceType,
                'enabled' => filter_var($extension['enabled'] ?? true, FILTER_VALIDATE_BOOL),
                'location' => filled($extension['location'] ?? null)
                    ? trim((string) $extension['location'])
                    : null,
                'notes' => filled($extension['notes'] ?? null)
                    ? trim((string) $extension['notes'])
                    : null,
            ]);
        }
    }
}
