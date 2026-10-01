<?php

namespace App\Ark\Operations\Attention;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class ActOnAdvisorNudgeController
{
    public function __invoke(
        Request $request,
        RecordAdvisorNudgeResponseAction $record,
    ): RedirectResponse {
        $data = $request->validate([
            'entity_key' => ['required', 'string', 'max:64'],
            'nudge_key' => ['required', 'string', 'max:64'],
            'redirect' => ['required', 'string', 'max:2048'],
            'section' => ['nullable', Rule::in(['attention', 'inbox'])],
        ]);

        $redirect = $this->safeRedirect($data['redirect']);

        $record->execute(
            user: $request->user(),
            entityKey: $data['entity_key'],
            nudgeKey: $data['nudge_key'],
            response: AdvisorNudgeResponseKind::Acted,
            metadata: ['redirect' => $redirect],
        );

        return redirect()->to($redirect);
    }

    private function safeRedirect(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        if (! Str::startsWith($path, '/app/')) {
            abort(422, 'Invalid redirect target.');
        }

        return $url;
    }
}
