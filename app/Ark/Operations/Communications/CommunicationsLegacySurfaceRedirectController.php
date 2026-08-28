<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\Conversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CommunicationsLegacySurfaceRedirectController
{
    public function inbox(Request $request): RedirectResponse
    {
        return redirect()->to(CommunicationsNeedsYou::url($request->query()));
    }

    public function history(Request $request): RedirectResponse
    {
        return redirect()->route(
            'operations.communications.history',
            $request->query(),
        );
    }

    public function workboard(Request $request): RedirectResponse
    {
        return redirect()->to(CommunicationsNeedsYou::url($request->query()));
    }

    public function reply(Request $request, Conversation $conversation): RedirectResponse
    {
        $params = array_filter([
            'conversation' => $conversation->id,
            'compose' => $request->query('compose', 'text'),
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return redirect()
            ->to(CommunicationsNeedsYou::url($params))
            ->withFragment('conversation-composer');
    }
}
