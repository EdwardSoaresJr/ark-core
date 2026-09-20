<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StoreConversationInternalNoteController
{
    public function __invoke(
        Request $request,
        Conversation $conversation,
        ConversationRecorder $recorder,
    ): RedirectResponse {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'section' => ['nullable', Rule::in(['attention', 'inbox'])],
        ]);

        $recorder->recordInternalNote($conversation, $request->user(), $data['body']);

        return CommunicationsWorkspaceRedirect::forConversation(
            $conversation,
            $data['section'] ?? 'inbox',
            'Internal note added.',
        );
    }
}
