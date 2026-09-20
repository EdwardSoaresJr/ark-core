<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Attention\AdvisorNudgeResponseKind;
use App\Ark\Operations\Attention\RecordAdvisorNudgeResponseAction;
use App\Ark\Operations\Conversations\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileAdvisorBriefSuggestionFeedbackController
{
    public function __invoke(
        Request $request,
        Conversation $conversation,
        MobileStaffAccess $access,
        RecordAdvisorNudgeResponseAction $record,
    ): JsonResponse {
        abort_unless($access->canViewConversation($request->user(), $conversation), 403);

        $data = $request->validate([
            'entity_key' => ['required', 'string', 'max:120'],
            'nudge_key' => ['nullable', 'string', 'max:120'],
            'response' => ['required', 'in:used,edited,dismissed'],
            'original_text' => ['nullable', 'string', 'max:1600'],
            'sent_text' => ['nullable', 'string', 'max:1600'],
        ]);

        $kind = match ($data['response']) {
            'dismissed' => AdvisorNudgeResponseKind::Dismissed,
            default => AdvisorNudgeResponseKind::Acted,
        };

        $metadata = array_filter([
            'surface' => 'companion',
            'conversation_id' => $conversation->id,
            'feedback' => $data['response'],
            'was_edited' => $data['response'] === 'edited',
            'original_text' => $data['original_text'] ?? null,
            'sent_text' => $data['sent_text'] ?? null,
        ]);

        $record->execute(
            user: $request->user(),
            entityKey: (string) $data['entity_key'],
            nudgeKey: (string) ($data['nudge_key'] ?? 'companion.suggested_reply'),
            response: $kind,
            metadata: $metadata !== [] ? $metadata : null,
        );

        return response()->json(['ok' => true]);
    }
}
