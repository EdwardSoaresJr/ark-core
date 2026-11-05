<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationReadTracker;
use App\Ark\Operations\Conversations\ConversationWork;
use App\Ark\Operations\Conversations\StaleConversationWorkException;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ConversationWorkController
{
    public function __invoke(
        Request $request,
        Conversation $conversation,
        ConversationWork $work,
        ConversationReadTracker $reads,
    ): RedirectResponse|JsonResponse {
        $data = $request->validate([
            'action' => ['required', Rule::in(['assign', 'follow_up', 'wait', 'resolve', 'reopen', 'unread'])],
            'assign_to' => ['nullable', Rule::in(['me', 'user', 'unassign'])],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'filter' => ['nullable', Rule::in(['needs', 'waiting', 'resolved', 'all'])],
            'owner' => ['nullable', Rule::in(['everyone', 'mine', 'unassigned'])],
            'posture_changed_at' => ['nullable', 'string', 'max:64'],
        ]);

        // Read marks are each advisor's own, so they are not checked against the shared posture.
        if ($data['action'] === 'unread') {
            $reads->markUnread($conversation, $request->user());

            if ($request->expectsJson()) {
                // The workspace moves on to the next conversation: opening this one again would read it.
                return response()->json(['ok' => true, 'action' => 'unread', 'status' => 'Marked unread.', 'in_list' => true, 'leave' => true]);
            }

            // Back to the list: opening the conversation again would read it.
            return redirect()
                ->route('operations.communications.inbox', array_filter([
                    'filter' => $this->workingFilter($data['filter'] ?? null),
                    'owner' => ($data['owner'] ?? 'everyone') !== 'everyone' ? $data['owner'] : null,
                ]))
                ->with('status', 'Marked unread.');
        }

        try {
            $work->assertCurrent($conversation, $data['posture_changed_at'] ?? null);
        } catch (StaleConversationWorkException $exception) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => 'Someone else just updated this conversation. It now shows the latest; try again if it still needs it.'], 409)
                : back()->with('error', $exception->getMessage());
        }

        $actor = $request->user();

        if ($data['action'] === 'assign' && ($data['assign_to'] ?? 'me') === 'user'
            && ! User::query()->active()->role([ArkRole::Admin->value, ArkRole::Advisor->value])->whereKey((int) ($data['user_id'] ?? 0))->exists()) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => 'Choose an active advisor to assign.'], 422)
                : back()->with('error', 'Choose an active advisor to assign.');
        }

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
        $filter = $data['action'] === 'resolve'
            ? $this->workingFilter($data['filter'] ?? null)
            : match ($work->lane($conversation)) {
                'waiting' => 'waiting',
                'resolved' => 'resolved',
                default => 'needs',
            };
        $status = match ($data['action']) {
            'assign' => $conversation->owned_by_user_id === null ? 'Unassigned.' : 'Assigned.',
            'follow_up' => 'Follow-up scheduled.',
            'wait' => 'Marked waiting.',
            'resolve' => 'Conversation resolved.',
            default => 'Conversation reopened.',
        };

        if ($request->expectsJson()) {
            $listFilter = $this->workingFilter($data['filter'] ?? null);
            $lane = $work->lane($conversation);

            return response()->json([
                'ok' => true,
                'action' => $data['action'],
                'status' => $status,
                'lane' => $lane,
                // Whether it still belongs in the list the advisor is working, so the workspace can move on if not.
                'in_list' => in_array($lane, $listFilter === 'all' ? ['needs', 'waiting'] : [$listFilter], true)
                    && match ($data['owner'] ?? 'everyone') {
                        'mine' => (int) $conversation->owned_by_user_id === (int) $actor->id,
                        'unassigned' => $conversation->owned_by_user_id === null,
                        default => true,
                    },
                'leave' => false,
            ]);
        }

        return redirect()
            ->route('operations.communications.inbox', array_filter([
                'filter' => $filter,
                'conversation' => $conversation->id,
                'owner' => ($data['owner'] ?? 'everyone') !== 'everyone' ? $data['owner'] : null,
            ]))
            ->with('status', $status);
    }

    private function workingFilter(?string $filter): string
    {
        return in_array($filter, ['needs', 'waiting', 'resolved', 'all'], true)
            ? $filter
            : 'needs';
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
