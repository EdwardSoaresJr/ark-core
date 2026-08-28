<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Leads\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Website leads own a conversation — never strand advisors on a lead-only thread.
 */
final class CommunicationsLeadConversationRedirect
{
    public function forRequest(Request $request, string $routeName): ?RedirectResponse
    {
        if (! $request->filled('lead')) {
            return null;
        }

        $lead = Lead::query()->find($request->integer('lead'));

        if ($lead === null || $lead->conversation_id === null) {
            return null;
        }

        $params = collect($request->query())
            ->except('lead')
            ->put('conversation', $lead->conversation_id)
            ->all();

        return redirect()->route($routeName, $params);
    }
}
