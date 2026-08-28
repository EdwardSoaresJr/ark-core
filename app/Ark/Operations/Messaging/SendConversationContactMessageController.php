<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Attention\AdvisorNudgeResponseKind;
use App\Ark\Operations\Attention\RecordAdvisorNudgeResponseAction;
use App\Ark\Operations\Conversations\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\File;
use RuntimeException;

class SendConversationContactMessageController
{
    public function __invoke(
        Request $request,
        Conversation $conversation,
        SendOutboundContactMessageAction $sendSms,
        ConversationMessagePresenter $presenter,
        ConversationMessageRenderer $renderer,
        RecordAdvisorNudgeResponseAction $recordNudge,
    ): JsonResponse {
        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:1600'],
            'nudge_key' => ['nullable', 'string', 'max:64'],
            'entity_key' => ['nullable', 'string', 'max:64'],
            'attachment' => [
                'nullable',
                File::types(['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'pdf'])
                    ->max(OutboundAttachmentStore::MAX_BYTES / 1024),
            ],
        ]);

        try {
            $result = $sendSms->execute(
                conversation: $conversation,
                actor: $request->user(),
                body: (string) ($validated['body'] ?? ''),
                attachment: $request->file('attachment'),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $message = $result['message'];

        if (filled($validated['nudge_key'] ?? null) && filled($validated['entity_key'] ?? null)) {
            $recordNudge->execute(
                user: $request->user(),
                entityKey: (string) $validated['entity_key'],
                nudgeKey: (string) $validated['nudge_key'],
                response: AdvisorNudgeResponseKind::Acted,
            );
        }

        return response()->json([
            'message_id' => $message->id,
            'provider_message_sid' => $result['provider_message_sid'],
            'message' => $presenter->present($message),
            'html' => $renderer->render($message, 'border-t border-slate-100'),
        ]);
    }
}
