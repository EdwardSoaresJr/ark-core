<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\TelephonyExtension;
use App\Ark\Operations\Telephony\TelephonyExtensionDeviceType;
use App\Ark\Operations\Telephony\TelephonyExtensionLegDial;
use App\Models\User;

/**
 * Active inbound call targeting this user's mobile voice extension.
 */
final class MobileInboundCallProjection
{
    /**
     * @return array{
     *     call_session_id: int,
     *     caller_label: string,
     *     called_extension: string,
     * }|null
     */
    public function activeForUser(User $user): ?array
    {
        if (! $this->userHasMobileVoice($user)) {
            return null;
        }

        $session = CallSession::query()
            ->where('status', CallSessionStatus::Ringing)
            ->where('direction', CallSessionDirection::Inbound)
            ->latest('id')
            ->get()
            ->first(static fn (CallSession $session): bool => ! TelephonyExtensionLegDial::isInboxGhostSession($session));

        if (! $session instanceof CallSession) {
            return null;
        }

        $calledExtension = trim((string) data_get($session->raw_payload, 'asterisk_called_extension', ''));

        return [
            'call_session_id' => $session->id,
            'caller_label' => $this->callerLabel($session),
            'called_extension' => $calledExtension,
        ];
    }

    private function userHasMobileVoice(User $user): bool
    {
        return TelephonyExtension::query()
            ->where('user_id', $user->id)
            ->where('device_type', TelephonyExtensionDeviceType::MobileApp)
            ->where('enabled', true)
            ->whereNotNull('mobile_device_id')
            ->exists();
    }

    private function callerLabel(CallSession $session): string
    {
        $normalized = trim((string) ($session->normalized_from ?? ''));
        if ($normalized !== '') {
            return $normalized;
        }

        $from = trim((string) ($session->from_number ?? ''));
        if ($from !== '') {
            return $from;
        }

        return 'Incoming call';
    }
}
