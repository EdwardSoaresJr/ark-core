<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationPosture;
use App\Ark\Operations\Conversations\ConversationReadTracker;
use App\Ark\Operations\Telephony\CallSessionQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * One-click clear for a Needs attention row.
 *
 * Handled means the shop covered this relationship right now: the conversation
 * resolves, the viewer's read state catches up, and unhandled calls from the
 * same contact clear. A new inbound message reopens the conversation.
 */
final class CommunicationsMarkConversationHandledController
{
    public function __invoke(
        Request $request,
        Conversation $conversation,
        ConversationPosture $posture,
        ConversationReadTracker $readTracker,
        CallSessionQueue $callQueue,
    ): RedirectResponse {
        $posture->resolve($conversation, $request->user());

        $through = $conversation->messages()->max('occurred_at');

        $readTracker->markRead(
            $conversation,
            $request->user(),
            $through ? Carbon::parse($through) : now(),
        );

        if ($conversation->contact_surface === ConversationContactSurface::Phone) {
            $callQueue->markCustomerOrPhoneHandled(null, (string) $conversation->contact_address);
        }

        return CommunicationsWorkspaceRedirect::toList('Marked handled.');
    }
}
