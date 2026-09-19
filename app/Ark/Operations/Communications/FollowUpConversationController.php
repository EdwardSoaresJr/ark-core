<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationWork;
use App\Ark\Operations\Conversations\StaleConversationWorkException;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FollowUpConversationController
{
    public function __invoke(
        Request $request,
        Conversation $conversation,
        ConversationWork $work,
    ): RedirectResponse {
        $data = $request->validate([
            'due_at' => ['nullable', 'date'],
            'section' => ['nullable', Rule::in(['attention', 'inbox'])],
            'posture_changed_at' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $work->assertCurrent($conversation, $data['posture_changed_at'] ?? null);
        } catch (StaleConversationWorkException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $dueAt = filled($data['due_at'] ?? null)
            ? ShopDisplayTimezone::parseLocal((string) $data['due_at'])
            : ShopDisplayTimezone::now()->addDay()->setTime(8, 0);

        $work->followUp($conversation, $request->user(), $dueAt);

        return CommunicationsWorkspaceRedirect::forConversation(
            $conversation,
            $data['section'] ?? 'inbox',
            'Follow-up scheduled.',
        );
    }
}
