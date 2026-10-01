<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationPosture;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReopenConversationController
{
    public function __invoke(
        Request $request,
        Conversation $conversation,
        ConversationPosture $posture,
    ): RedirectResponse {
        $data = $request->validate([
            'section' => ['nullable', Rule::in(['attention', 'inbox'])],
        ]);

        $posture->reopen($conversation, $request->user());

        return CommunicationsWorkspaceRedirect::forConversation(
            $conversation,
            $data['section'] ?? 'inbox',
            'Conversation reopened.',
        );
    }
}
