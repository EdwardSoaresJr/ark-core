<?php

namespace App\Ark\Operations\Telephony;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ToggleCallSessionCoachingFollowUpController
{
    public function __invoke(Request $request, CallSession $callSession): RedirectResponse
    {
        if ($callSession->coaching_follow_up_at !== null) {
            $callSession->forceFill(['coaching_follow_up_at' => null])->save();
            $message = 'Call unpinned from coaching follow-up.';
        } else {
            $callSession->forceFill(['coaching_follow_up_at' => now()])->save();
            $message = 'Call pinned for coaching follow-up.';
        }

        return redirect()->back()->with('status', $message);
    }
}
