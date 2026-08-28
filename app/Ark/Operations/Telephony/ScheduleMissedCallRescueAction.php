<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Telephony\Jobs\SendMissedCallRescueSmsJob;
use Illuminate\Support\Facades\Cache;

class ScheduleMissedCallRescueAction
{
    public function execute(CallSession $session): void
    {
        if ($session->direction !== CallSessionDirection::Inbound) {
            return;
        }

        if ($session->status !== CallSessionStatus::Missed) {
            return;
        }

        $flow = TelephonyCallFlowSettings::fromShopSettings();

        if (! $flow->missedCallRescueEnabled()) {
            return;
        }

        $scheduleKey = 'missed_call_rescue:scheduled:'.$session->id;

        if (! Cache::add($scheduleKey, true, now()->addHours(6))) {
            return;
        }

        SendMissedCallRescueSmsJob::dispatch($session->id)
            ->delay(now()->addSeconds($flow->missedCallRescueDelaySeconds()));
    }
}
