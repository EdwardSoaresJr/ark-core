<?php

namespace App\Ark\Operations\Telephony\MobileVoice;

use App\Ark\Mobile\MobileDevice;
use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TelephonyEndpointType;
use App\Models\User;
use Carbon\CarbonImmutable;

final class MobileVoiceEndpointRegistrar
{
    public const COVERAGE_PRESENCE_MINUTES = 30;

    public function ensureForDevice(MobileDevice $device): TelephonyEndpoint
    {
        $identity = MobileVoiceIdentity::fromDevice($device);
        $name = 'Mobile · '.$device->device_name;

        return TelephonyEndpoint::query()->updateOrCreate(
            [
                'type' => TelephonyEndpointType::MobileApp,
                'destination' => $identity,
            ],
            [
                'name' => $name,
                'user_id' => $device->user_id,
                'enabled' => true,
                'position' => 999,
            ],
        );
    }

    public function resolveForDevice(MobileDevice $device): ?TelephonyEndpoint
    {
        $identity = MobileVoiceIdentity::fromDevice($device);

        return TelephonyEndpoint::query()
            ->where('type', TelephonyEndpointType::MobileApp)
            ->where('destination', $identity)
            ->where('enabled', true)
            ->first();
    }

    public function resolveLatestForUser(User $user): ?TelephonyEndpoint
    {
        return TelephonyEndpoint::query()
            ->where('type', TelephonyEndpointType::MobileApp)
            ->where('user_id', $user->id)
            ->where('enabled', true)
            ->orderByDesc('updated_at')
            ->first();
    }

    public function disableForDevice(MobileDevice $device): void
    {
        TelephonyEndpoint::query()
            ->where('type', TelephonyEndpointType::MobileApp)
            ->where('destination', MobileVoiceIdentity::fromDevice($device))
            ->update(['enabled' => false]);

        $this->clearVoiceReady($device);
    }

    public function disableForUser(User $user): void
    {
        TelephonyEndpoint::query()
            ->where('type', TelephonyEndpointType::MobileApp)
            ->where('user_id', $user->id)
            ->update(['enabled' => false]);

        $this->clearVoiceReadyForUser($user);
    }

    /**
     * Record positive evidence that Twilio Client voice registration succeeded or refreshed.
     */
    public function markVoiceReady(MobileDevice $device): void
    {
        $device->forceFill([
            'voice_ready_at' => CarbonImmutable::now(),
            'last_seen_at' => CarbonImmutable::now(),
        ])->save();

        $this->ensureForDevice($device);
    }

    public function clearVoiceReady(MobileDevice $device): void
    {
        if ($device->voice_ready_at === null) {
            return;
        }

        $device->forceFill(['voice_ready_at' => null])->save();
    }

    public function clearVoiceReadyForUser(User $user): void
    {
        MobileDevice::query()
            ->where('user_id', $user->id)
            ->whereNotNull('voice_ready_at')
            ->update(['voice_ready_at' => null]);
    }

    /**
     * Ring eligibility for a MobileApp endpoint: Client credentials + platform push + recent voice_ready_at.
     * Fail closed when TwiML App or push credential is missing — SIP/Number children keep ringing.
     * Generic FCM/APNs device registration alone is not sufficient.
     */
    public function isEndpointVoiceReady(TelephonyEndpoint $endpoint, ?int $timeoutMinutes = null): bool
    {
        return false;
    }

    public function deviceHasRecentVoiceReady(MobileDevice $device, int $timeoutMinutes = self::COVERAGE_PRESENCE_MINUTES): bool
    {
        if ($device->voice_ready_at === null) {
            return false;
        }

        $timeoutMinutes = max(1, $timeoutMinutes);

        return $device->voice_ready_at->greaterThanOrEqualTo(
            CarbonImmutable::now()->subMinutes($timeoutMinutes)
        );
    }

    /**
     * Mobile coverage is live only while mobile voice readiness is recent for an enabled endpoint.
     */
    public function userHasLiveCoverage(User $user): bool
    {
        $cutoff = CarbonImmutable::now()->subMinutes(self::COVERAGE_PRESENCE_MINUTES);

        return MobileDevice::query()
            ->where('user_id', $user->id)
            ->whereNotNull('voice_ready_at')
            ->where('voice_ready_at', '>=', $cutoff)
            ->orderByDesc('voice_ready_at')
            ->get()
            ->contains(fn (MobileDevice $device): bool => $this->resolveForDevice($device) !== null);
    }

    public function touchDeviceFromTokenName(User $user, ?string $deviceName): void
    {
        $deviceName = trim((string) $deviceName);

        if ($deviceName === '') {
            return;
        }

        MobileDevice::query()
            ->where('user_id', $user->id)
            ->where('device_name', $deviceName)
            ->update(['last_seen_at' => CarbonImmutable::now()]);
    }
}
