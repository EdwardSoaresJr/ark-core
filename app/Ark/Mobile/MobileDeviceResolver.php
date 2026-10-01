<?php

namespace App\Ark\Mobile;

use App\Models\User;
use Illuminate\Http\Request;

final class MobileDeviceResolver
{
    public function resolve(Request $request, ?string $deviceName = null): ?MobileDevice
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        $name = trim((string) ($deviceName ?? $request->input('device_name') ?? $request->user()?->currentAccessToken()?->name ?? ''));

        if ($name === '') {
            return $this->resolveLatestForUser($user);
        }

        return MobileDevice::query()
            ->where('user_id', $user->id)
            ->where('device_name', $name)
            ->first();
    }

    public function resolveLatestForUser(User $user): ?MobileDevice
    {
        return MobileDevice::query()
            ->where('user_id', $user->id)
            ->orderByDesc('last_seen_at')
            ->first();
    }
}
