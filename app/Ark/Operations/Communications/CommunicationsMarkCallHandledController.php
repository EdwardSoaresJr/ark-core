<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Attention\AdvisorNudgeResponseKind;
use App\Ark\Operations\Attention\RecordAdvisorNudgeResponseAction;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CommunicationsMarkCallHandledController
{
    public function __invoke(
        Request $request,
        CallSession $callSession,
        CallSessionQueue $queue,
        RecordAdvisorNudgeResponseAction $record,
    ): RedirectResponse {
        $data = $request->validate([
            'nudge_key' => ['nullable', 'string', 'max:64'],
            'entity_key' => ['nullable', 'string', 'max:64'],
            'section' => ['nullable', Rule::in(['attention', 'inbox'])],
        ]);

        $queue->markCallerHandled($callSession);

        if (filled($data['nudge_key'] ?? null) && filled($data['entity_key'] ?? null)) {
            $record->execute(
                user: $request->user(),
                entityKey: $data['entity_key'],
                nudgeKey: $data['nudge_key'],
                response: AdvisorNudgeResponseKind::Acted,
            );
        }

        // Back to the list unselected — re-selecting the call would synthesize
        // the cleared row straight back into Needs attention.
        return CommunicationsWorkspaceRedirect::toList('Call marked handled.');
    }
}
