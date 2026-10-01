<?php

namespace App\Ark\Operations\Telephony\MobileVoice;

use App\Ark\Mobile\MobileDevice;
use App\Models\User;
use RuntimeException;

final class NotConfiguredMobileVoiceTransport implements MobileVoiceTransport
{
    public function transportKey(): string
    {
        return 'none';
    }

    public function isReadyFor(User $user, ?MobileDevice $device = null): bool
    {
        return false;
    }

    public function readinessBlockReason(User $user, ?MobileDevice $device = null): ?string
    {
        return 'Voice telephony is not configured.';
    }

    public function issueSession(User $user, MobileDevice $device): array
    {
        throw new RuntimeException('Voice telephony is not configured.');
    }

    public function issueConnect(
        User $user,
        MobileDevice $device,
        MobileVoiceConnectIntent $intent,
        string $connectToken,
    ): array {
        throw new RuntimeException('Voice telephony is not configured.');
    }
}
