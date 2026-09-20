<?php

namespace App\Ark\Mobile\Push;

use App\Ark\Mobile\MobileDevice;
use App\Models\User;

/**
 * Maintains operator continuity across mobile devices.
 *
 * Push notifications are one transport — see {@see PushTransport}. ARK decides
 * who receives which continuity packet; this service resolves device tokens and
 * delegates delivery. It does not own observations, routing, or shop policy.
 */
final class MobilePushService
{
    public function __construct(
        private readonly PushTransport $transport,
    ) {}

    public function isEnabled(): bool
    {
        return $this->transport->isAvailable();
    }

    public function sendToUser(User $user, MobilePushMessage $message): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $tokens = MobileDevice::query()
            ->where('user_id', $user->id)
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->filter(fn (?string $token): bool => filled($token))
            ->unique()
            ->values()
            ->all();

        if ($tokens === []) {
            return 0;
        }

        $sent = 0;

        foreach ($tokens as $token) {
            if ($this->transport->send($token, $message)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * @param  list<User>  $users
     */
    public function sendToUsers(MobilePushMessage $message, array $users): int
    {
        $sent = 0;

        foreach ($users as $user) {
            $sent += $this->sendToUser($user, $message);
        }

        return $sent;
    }
}
