<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Attention\AdvisorNudgeResponseKind;
use App\Ark\Operations\Attention\RecordAdvisorNudgeResponseAction;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationPosture;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssignConversationController
{
    public function __invoke(
        Request $request,
        Conversation $conversation,
        ConversationPosture $posture,
        RecordAdvisorNudgeResponseAction $recordNudge,
    ): RedirectResponse {
        $data = $request->validate([
            'assign_to' => ['required', Rule::in(['me', 'user', 'unassign'])],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'section' => ['nullable', Rule::in(['attention', 'inbox'])],
            'nudge_key' => ['nullable', 'string', 'max:64'],
            'entity_key' => ['nullable', 'string', 'max:64'],
        ]);

        $owner = match ($data['assign_to']) {
            'unassign' => null,
            'user' => User::query()->findOrFail((int) $data['user_id']),
            default => $request->user(),
        };

        $posture->assign($conversation, $owner);

        if (filled($data['nudge_key'] ?? null) && filled($data['entity_key'] ?? null)) {
            $recordNudge->execute(
                user: $request->user(),
                entityKey: (string) $data['entity_key'],
                nudgeKey: (string) $data['nudge_key'],
                response: AdvisorNudgeResponseKind::Acted,
            );
        }

        $label = $owner !== null ? "Assigned to {$owner->name}." : 'Conversation unassigned.';

        return CommunicationsWorkspaceRedirect::forConversation(
            $conversation,
            $data['section'] ?? 'inbox',
            $label,
        );
    }
}
