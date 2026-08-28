<?php

namespace App\Ark\Operations\Attention;

use App\Ark\Operations\Communications\CommunicationsNeedsYou;
use App\Ark\Operations\Communications\CommunicationsWorkspaceRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class DismissAdvisorNudgeController
{
    public function __invoke(
        Request $request,
        RecordAdvisorNudgeResponseAction $record,
    ): RedirectResponse {
        $data = $request->validate([
            'entity_key' => ['required', 'string', 'max:64'],
            'nudge_key' => ['required', 'string', 'max:64'],
            'section' => ['nullable', Rule::in(['attention', 'inbox'])],
        ]);

        $record->execute(
            user: $request->user(),
            entityKey: $data['entity_key'],
            nudgeKey: $data['nudge_key'],
            response: AdvisorNudgeResponseKind::Dismissed,
        );

        return $this->redirectBack($data['entity_key'], $data['section'] ?? 'attention');
    }

    private function redirectBack(string $entityKey, string $section): RedirectResponse
    {
        [$kind, $id] = explode(':', $entityKey, 2);

        return match ($kind) {
            'conversation' => CommunicationsWorkspaceRedirect::forConversationId(
                (int) $id,
                $section,
                'Nudge dismissed.',
            ),
            'call' => CommunicationsWorkspaceRedirect::forCallSessionId(
                (int) $id,
                $section,
                'Nudge dismissed.',
            ),
            default => redirect()->to(CommunicationsNeedsYou::url())->with('status', 'Nudge dismissed.'),
        };
    }
}
