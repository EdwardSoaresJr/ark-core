<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Attention\AdvisorNudgeResponseKind;
use App\Ark\Operations\Attention\RecordAdvisorNudgeResponseAction;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationReadTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

final class CommunicationsMarkConversationReadController
{
    public function __invoke(
        Request $request,
        Conversation $conversation,
        ConversationReadTracker $readTracker,
        RecordAdvisorNudgeResponseAction $record,
    ): JsonResponse|RedirectResponse {
        $data = $request->validate([
            'nudge_key' => ['nullable', 'string', 'max:64'],
            'entity_key' => ['nullable', 'string', 'max:64'],
            'section' => ['nullable', Rule::in(['attention', 'inbox'])],
        ]);

        $through = $conversation->messages()->max('occurred_at');

        $readTracker->markRead(
            $conversation,
            $request->user(),
            $through ? Carbon::parse($through) : now(),
        );

        if (filled($data['nudge_key'] ?? null) && filled($data['entity_key'] ?? null)) {
            $record->execute(
                user: $request->user(),
                entityKey: $data['entity_key'],
                nudgeKey: $data['nudge_key'],
                response: AdvisorNudgeResponseKind::Acted,
            );
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return CommunicationsWorkspaceRedirect::forConversation(
            $conversation,
            $data['section'] ?? 'attention',
            'Conversation marked read.',
        );
    }
}
