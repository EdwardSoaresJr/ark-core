<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Telephony\CallSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CommunicationsMarkInboxCallHandledController
{
    public function __invoke(Request $request, CallSession $callSession): RedirectResponse
    {
        $data = $request->validate([
            'filter' => ['nullable', 'string', 'max:32'],
            'owner' => ['nullable', 'string', 'max:32'],
            'platform_conversation' => ['nullable', 'string', 'max:80'],
            'conversation' => ['nullable', 'integer'],
        ]);

        // This call only. The Calls & VM action clears every recent call from
        // the number and resyncs turn. A normal save would do the same.
        if ($callSession->worked_at === null) {
            $callSession->worked_at = now();
            $callSession->saveQuietly();
        }

        $filter = in_array($data['filter'] ?? '', ['needs', 'waiting', 'resolved', 'all'], true)
            ? $data['filter']
            : 'needs';
        $owner = (string) ($data['owner'] ?? '');
        $platformConversation = trim((string) ($data['platform_conversation'] ?? ''));
        $conversationId = (int) ($data['conversation'] ?? 0);

        return redirect()
            ->route('operations.communications.inbox', array_filter([
                'filter' => $filter,
                'owner' => $owner !== '' && $owner !== 'everyone' ? $owner : null,
                'platform_conversation' => $platformConversation !== '' ? $platformConversation : null,
                'conversation' => $conversationId > 0 ? $conversationId : null,
            ]))
            ->with('status', 'Call marked handled.');
    }
}
