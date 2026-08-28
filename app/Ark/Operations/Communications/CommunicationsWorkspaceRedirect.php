<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Telephony\CallSession;
use Illuminate\Http\RedirectResponse;

final class CommunicationsWorkspaceRedirect
{
    /**
     * Back to the list with nothing re-selected — a cleared row must visibly leave.
     */
    public static function toList(?string $status = null, string $filter = 'needs'): RedirectResponse
    {
        $redirect = redirect()->route(CommunicationsNeedsYou::routeName(), ['filter' => $filter]);

        return $status !== null ? $redirect->with('status', $status) : $redirect;
    }

    public static function toSelection(string $section, ?string $selectionKey, ?string $status = null): RedirectResponse
    {
        $params = self::selectionParams($selectionKey);

        if ($section === 'history') {
            $redirect = redirect()->route('operations.communications.history', $params);

            return $status !== null ? $redirect->with('status', $status) : $redirect;
        }

        $filter = request()->string('filter')->toString();
        if ($filter === '') {
            $filter = match (request()->string('turn')->toString()) {
                'customer' => 'waiting',
                default => 'needs',
            };
        }

        // Filter first — matches CommunicationsNeedsYou::url() parameter order
        // so redirect targets compare equal across the workspace.
        $params = [
            'filter' => in_array($filter, ['all', 'needs', 'waiting', 'resolved'], true)
                ? $filter
                : 'needs',
        ] + $params;

        $redirect = redirect()->route(CommunicationsNeedsYou::routeName(), $params);

        return $status !== null ? $redirect->with('status', $status) : $redirect;
    }

    /**
     * @return array<string, int>
     */
    public static function selectionParams(?string $selectionKey): array
    {
        if ($selectionKey === null || ! str_contains($selectionKey, ':')) {
            return [];
        }

        [$kind, $id] = explode(':', $selectionKey, 2);

        return match ($kind) {
            'conversation' => ['conversation' => (int) $id],
            'lead' => ['lead' => (int) $id],
            'call' => ['call' => (int) $id],
            default => [],
        };
    }

    public static function forConversation(Conversation $conversation, string $section = 'inbox', ?string $status = null): RedirectResponse
    {
        return self::toSelection($section, 'conversation:'.$conversation->id, $status);
    }

    public static function forConversationId(int $conversationId, string $section = 'inbox', ?string $status = null): RedirectResponse
    {
        return self::toSelection($section, 'conversation:'.$conversationId, $status);
    }

    public static function forCallSession(CallSession $callSession, string $section = 'inbox', ?string $status = null): RedirectResponse
    {
        return self::toSelection($section, 'call:'.$callSession->id, $status);
    }

    public static function forCallSessionId(int $callSessionId, string $section = 'inbox', ?string $status = null): RedirectResponse
    {
        return self::toSelection($section, 'call:'.$callSessionId, $status);
    }
}
