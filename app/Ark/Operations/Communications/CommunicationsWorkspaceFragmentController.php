<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Platform\Communications\ManagedCommunicationsGate;
use App\Ark\Platform\Communications\PlatformCommunicationsInboxProjection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CommunicationsWorkspaceFragmentController
{
    public function __invoke(
        Request $request,
        CommunicationsWorkspaceProjection $projection,
    ): JsonResponse {
        $section = $request->string('section', 'inbox')->toString();
        if ($section === 'attention') {
            $section = 'inbox';
        }

        $incomingSignature = $request->string('signature')->toString();

        if ($section === 'inbox' && ManagedCommunicationsGate::platformInbox()) {
            $filter = $request->string('filter')->toString();
            if ($filter === '') {
                $filter = 'needs';
            }

            $workspace = app(PlatformCommunicationsInboxProjection::class)->inbox(
                $request->user(),
                $request->string('platform_conversation')->toString() ?: null,
                $filter,
                $request->string('owner')->toString() ?: 'everyone',
            );
            $pollSignature = (string) ($workspace['poll_signature'] ?? '');

            if ($incomingSignature !== '' && $pollSignature !== '' && hash_equals($pollSignature, $incomingSignature)) {
                return response()->json([
                    'signature' => $pollSignature,
                    'unchanged' => true,
                ]);
            }

            return $this->workspacePayload($workspace, $section, (string) ($workspace['list_filter'] ?? $filter));
        }

        $filter = $request->string('filter')->toString();
        $turn = $request->string('turn')->toString();

        if ($filter === '' && $turn !== '') {
            $filter = match ($turn) {
                'shop' => 'needs',
                'customer' => 'waiting',
                default => 'needs',
            };
        }

        if ($filter === '') {
            $filter = 'needs';
        }

        $conversationId = $request->integer('conversation') ?: null;
        $leadId = $request->integer('lead') ?: null;
        $callSessionId = $request->integer('call') ?: null;

        if ($section === 'inbox') {
            $pollSignature = $projection->pollSignature($filter, $conversationId, $leadId, $callSessionId);

            if ($incomingSignature !== '' && hash_equals($pollSignature, $incomingSignature)) {
                return response()->json([
                    'signature' => $pollSignature,
                    'unchanged' => true,
                ]);
            }
        }

        $workspace = match ($section) {
            'inbox' => $projection->inbox(
                $request->user(),
                $conversationId,
                $leadId,
                $callSessionId,
                $turn !== '' ? $turn : null,
                $this->previousLastSeenAt($request),
                $filter,
                $request->string('owner')->toString() ?: 'everyone',
            ),
            'history' => $projection->history(
                $request,
                $request->integer('conversation') ?: null,
                $request->integer('call') ?: null,
            ),
            default => abort(404),
        };

        return $this->workspacePayload($workspace, $section, (string) ($workspace['list_filter'] ?? $filter));
    }

    /**
     * @param  array<string, mixed>  $workspace
     */
    private function workspacePayload(array $workspace, string $section, string $listFilter): JsonResponse
    {
        $listTitle = match (true) {
            $section === 'history' => 'History',
            $listFilter === 'waiting' => 'Waiting',
            $listFilter === 'resolved' => 'Resolved',
            $listFilter === 'all' => 'Inbox',
            default => 'Needs attention',
        };

        return response()->json([
            'signature' => (string) ($workspace['poll_signature'] ?? $this->signature($workspace)),
            'unchanged' => false,
            'list_count' => (int) ($workspace['list_count'] ?? 0),
            'list' => view('operations.communications.workspace.partials.list-panel', [
                'title' => $listTitle,
                'count' => $workspace['list_count'],
                'items' => $workspace['list_items'],
                'selected' => $workspace['selected'],
                'listFilter' => $listFilter,
                'filterCounts' => $workspace['filter_counts'] ?? null,
                'ownerFilter' => $workspace['owner_filter'] ?? 'everyone',
                'ownerCounts' => $workspace['owner_counts'] ?? null,
                'turnFilter' => $workspace['turn_filter'] ?? null,
                'turnCounts' => $workspace['turn_counts'] ?? null,
                'section' => $section,
                'platformBacked' => (bool) ($workspace['platform_backed'] ?? false),
            ])->render(),
            'thread' => view('operations.communications.workspace.partials.thread-panel', [
                'thread' => $workspace['thread'],
                'selected' => $workspace['selected'],
                'section' => $section,
            ])->render(),
            'context' => view('operations.communications.workspace.partials.context-panel', [
                'context' => $workspace['context'],
                'selected' => $workspace['selected'],
                'section' => $section,
                'nextActions' => $workspace['thread']['identity']['actions'] ?? [],
            ])->render(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $workspace
     */
    private function signature(array $workspace): string
    {
        $parts = [
            (string) ($workspace['list_count'] ?? 0),
            (string) ($workspace['list_filter'] ?? ''),
        ];

        foreach ($workspace['list_items'] ?? [] as $item) {
            $parts[] = implode(':', [
                (string) ($item['key'] ?? ''),
                (string) ($item['headline'] ?? ''),
                (string) ($item['phone'] ?? $item['subtitle'] ?? ''),
                (string) ($item['reason'] ?? ''),
                (string) ($item['shop_hint'] ?? ''),
                (string) ($item['assigned_label'] ?? ''),
            ]);
        }

        $thread = is_array($workspace['thread'] ?? null) ? $workspace['thread'] : [];
        $identity = is_array($thread['identity'] ?? null) ? $thread['identity'] : [];
        $parts[] = implode(':', [
            (string) ($identity['name'] ?? $thread['title'] ?? ''),
            (string) ($identity['phone'] ?? ''),
            (string) ($identity['email'] ?? ''),
            (string) ($identity['ro_status'] ?? $thread['status_label'] ?? ''),
            (string) ($thread['assignment_label'] ?? ''),
            (string) count($thread['events'] ?? []),
        ]);

        $context = is_array($workspace['context'] ?? null) ? $workspace['context'] : [];
        $parts[] = json_encode([
            'fields' => $context['fields'] ?? [],
            'link_status' => $context['link_status'] ?? null,
            'primary_ro' => $context['primary_ro'] ?? null,
        ]);

        return md5(implode('|', $parts));
    }

    private function previousLastSeenAt(Request $request): ?Carbon
    {
        $previousLastSeen = $request->attributes->get('operations.previous_last_seen_at');

        if ($previousLastSeen instanceof Carbon) {
            return $previousLastSeen;
        }

        return is_string($previousLastSeen) ? Carbon::parse($previousLastSeen) : null;
    }
}
