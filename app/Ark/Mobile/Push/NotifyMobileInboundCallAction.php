<?php

namespace App\Ark\Mobile\Push;

use App\Ark\Operations\Telephony\CallSession;

/**
 * Optional ARK FCM wake for PSTN inbound (non-Voice SDK).
 *
 * Left no-op on purpose for the Twilio Client recovery slice: incoming calls are
 * delivered by Twilio Voice PushKit/FCM → CallKit/ConnectionService. Restoring a
 * parallel ARK data push here would duplicate incoming UI.
 */
final class NotifyMobileInboundCallAction
{
    public function execute(CallSession $session): int
    {
        return 0;
    }
}
