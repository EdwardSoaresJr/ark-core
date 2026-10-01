<?php

namespace App\Ark\Operations\Communications;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ToggleSmsIntelligenceCoachingFollowUpController
{
    public function __invoke(Request $request, ConversationSmsIntelligenceSlice $slice): RedirectResponse
    {
        if ($slice->coaching_follow_up_at !== null) {
            $slice->forceFill(['coaching_follow_up_at' => null])->save();
            $message = 'SMS thread unpinned from coaching follow-up.';
        } else {
            $slice->forceFill(['coaching_follow_up_at' => now()])->save();
            $message = 'SMS thread pinned for coaching follow-up.';
        }

        return redirect()->back()->with('status', $message);
    }
}
