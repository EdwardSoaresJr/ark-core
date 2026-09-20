<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Attention\AdvisorNudgeResponseKind;
use App\Ark\Operations\Attention\RecordAdvisorNudgeResponseAction;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\ConversationResolver;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StoreCallSessionNoteController
{
    public function __invoke(
        Request $request,
        CallSession $callSession,
        ConversationResolver $resolver,
        ConversationRecorder $recorder,
        CallSessionQueue $queue,
        RecordAdvisorNudgeResponseAction $recordNudge,
    ): RedirectResponse {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'section' => ['nullable', Rule::in(['attention', 'inbox'])],
            'nudge_key' => ['nullable', 'string', 'max:64'],
            'entity_key' => ['nullable', 'string', 'max:64'],
        ]);

        $phone = PhoneNumber::normalize((string) ($callSession->normalized_from ?? $callSession->from_number));

        abort_if($phone === null, 422, 'Call session does not have a usable phone number.');

        $conversation = $resolver->forContactKey(ConversationContactSurface::Phone, $phone);

        $recorder->recordCallNote($conversation, $request->user(), $data['body'], $callSession->id);

        if ($callSession->worked_at === null) {
            $queue->markCallerHandled($callSession);
        }

        $entityKey = filled($data['entity_key'] ?? null)
            ? (string) $data['entity_key']
            : 'call:'.$callSession->id;
        $nudgeKey = filled($data['nudge_key'] ?? null)
            ? (string) $data['nudge_key']
            : 'call.log_note';

        $recordNudge->execute(
            user: $request->user(),
            entityKey: $entityKey,
            nudgeKey: $nudgeKey,
            response: AdvisorNudgeResponseKind::Acted,
        );

        return CommunicationsWorkspaceRedirect::forCallSession(
            $callSession,
            $data['section'] ?? 'inbox',
            'Call note logged.',
        );
    }
}
