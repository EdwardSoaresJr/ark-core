<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\PhoneNumber;
use App\Models\User;

class TelephonyEndpointSync
{
    /**
     * @param  array<int, array<string, mixed>>  $endpoints
     */
    public function sync(array $endpoints): void
    {
        TelephonyEndpoint::query()
            ->where('type', '!=', TelephonyEndpointType::MobileApp->value)
            ->delete();

        foreach (array_values($endpoints) as $position => $endpoint) {
            $name = trim((string) ($endpoint['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $type = TelephonyEndpointType::tryFrom((string) ($endpoint['type'] ?? ''));

            if ($type === null || $type === TelephonyEndpointType::MobileApp) {
                continue;
            }

            $userId = filled($endpoint['user_id'] ?? null) ? (int) $endpoint['user_id'] : null;
            $ringSchedule = TelephonyRingSchedule::tryFrom((string) ($endpoint['ring_schedule'] ?? ''))
                ?? TelephonyRingSchedule::Always;

            if ($type === TelephonyEndpointType::Cell) {
                if ($userId === null) {
                    continue;
                }

                $user = User::query()->find($userId);
                $destination = PhoneNumber::normalize($user?->phone) ?? '';
            } else {
                $destination = trim((string) ($endpoint['destination'] ?? ''));

                if ($destination === '') {
                    continue;
                }

                $destination = TelephonySipDestination::normalize($destination);
            }

            $ringDelaySeconds = max(0, min(60, (int) ($endpoint['ring_delay_seconds'] ?? 0)));
            $presenceTimeoutMinutes = max(5, min(240, (int) ($endpoint['presence_timeout_minutes'] ?? 30)));

            TelephonyEndpoint::query()->create([
                'name' => $name,
                'type' => $type,
                'destination' => $destination,
                'user_id' => $userId,
                'ring_schedule' => $ringSchedule,
                'ring_delay_seconds' => $ringDelaySeconds,
                'presence_timeout_minutes' => $presenceTimeoutMinutes,
                'enabled' => filter_var($endpoint['enabled'] ?? true, FILTER_VALIDATE_BOOL),
                'position' => $position,
            ]);
        }
    }
}
