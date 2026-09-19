<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationWork;
use App\Ark\Operations\Conversations\StaleConversationWorkException;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ConversationWorkController
{
    public function __invoke(
        Request $request,
        Conversation $conversation,
        ConversationWork $work,
    ): RedirectResponse {
        $data = $request->validate([
            'action' => ['required', Rule::in(['assign', 'follow_up', 'wait', 'resolve', 'reopen'])],
            'assign_to' => ['nullable', Rule::in(['me', 'user', 'unassign'])],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'filter' => ['nullable', Rule::in(['needs', 'waiting', 'resolved', 'all'])],
            'owner' => ['nullable', Rule::in(['everyone', 'mine', 'unassigned'])],
            'posture_changed_at' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $work->assertCurrent($conversation, $data['posture_changed_at'] ?? null);
        } catch (StaleConversationWorkException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $actor = $request->user();

        match ($data['action']) {
            'assign' => $work->assign($conversation, $this->owner($data, $actor)),
            'follow_up' => $work->followUp(
                $conversation,
                $actor,
                filled($data['due_at'] ?? null)
                    ? ShopDisplayTimezone::parseLocal((string) $data['due_at'])
                    : ShopDisplayTimezone::now()->addDay()->setTime(8, 0),
            ),
            'wait' => $work->wait($conversation, $actor),
            'resolve' => $work->resolve($conversation, $actor),
            'reopen' => $work->markNeedsAttention($conversation),
        };

        $conversation = $conversation->fresh();
        $filter = match ($work->lane($conversation)) {
            'waiting' => 'waiting',
            'resolved' => 'resolved',
            default => 'needs',
        };
        $status = match ($data['action']) {
            'assign' => 'Assigned.',
            'follow_up' => 'Follow-up scheduled.',
            'wait' => 'Marked waiting.',
            'resolve' => 'Conversation resolved.',
            default => 'Conversation reopened.',
        };

        return redirect()
            ->route('operations.communications.inbox', array_filter([
                'filter' => $filter,
                'conversation' => $conversation->id,
                'owner' => ($data['owner'] ?? 'everyone') !== 'everyone' ? $data['owner'] : null,
            ]))
            ->with('status', $status);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function owner(array $data, User $actor): ?User
    {
        return match ((string) ($data['assign_to'] ?? 'me')) {
            'unassign' => null,
            'user' => User::query()->findOrFail((int) $data['user_id']),
            default => $actor,
        };
    }
}
