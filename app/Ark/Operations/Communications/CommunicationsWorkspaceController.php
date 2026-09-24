<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Platform\Communications\ManagedCommunicationsGate;
use App\Ark\Platform\Communications\PlatformCommunicationsInboxProjection;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CommunicationsWorkspaceController
{
    public function attention(Request $request): RedirectResponse
    {
        return $this->redirectToNeedsAttention($request);
    }

    public function index(Request $request): RedirectResponse
    {
        return $this->redirectToNeedsAttention($request);
    }

    public function inbox(Request $request, CommunicationsWorkspaceProjection $projection, CommunicationsLeadConversationRedirect $leadRedirect): View|RedirectResponse
    {
        if ($redirect = $leadRedirect->forRequest($request, 'operations.communications.inbox')) {
            return $redirect;
        }

        if (ManagedCommunicationsGate::platformInbox()) {
            $filter = $request->string('filter')->toString();

            if ($filter === '') {
                return redirect()->to(CommunicationsNeedsYou::url($request->query()));
            }

            $workspace = app(PlatformCommunicationsInboxProjection::class)
                ->inbox(
                    $request->user(),
                    $request->string('platform_conversation')->toString() ?: null,
                    $filter,
                    $request->string('owner')->toString() ?: 'everyone',
                    $request->integer('conversation') ?: null,
                    $request->integer('lead') ?: null,
                );

            return view('operations.communications.workspace.inbox', [
                'workspace' => $workspace,
            ]);
        }

        $filter = $request->string('filter')->toString();
        $turn = $request->string('turn')->toString();

        if ($filter === '' && $turn !== '') {
            $mapped = match ($turn) {
                'shop' => 'needs',
                'customer' => 'waiting',
                default => null,
            };

            if ($mapped !== null) {
                $params = $request->except('turn');
                unset($params['filter']);

                return redirect()->route(
                    'operations.communications.inbox',
                    ['filter' => $mapped] + $params,
                );
            }
        }

        if ($filter === '') {
            return redirect()->to(CommunicationsNeedsYou::url($request->query()));
        }

        return view('operations.communications.workspace.inbox', [
            'workspace' => $projection->inbox(
                $request->user(),
                $request->integer('conversation') ?: null,
                $request->integer('lead') ?: null,
                $request->integer('call') ?: null,
                $turn !== '' ? $turn : null,
                $this->previousLastSeenAt($request),
                $filter,
                $request->string('owner')->toString() ?: 'everyone',
            ),
        ]);
    }

    public function history(Request $request, CommunicationsWorkspaceProjection $projection): View
    {
        return view('operations.communications.workspace.history', [
            'workspace' => $projection->history(
                $request,
                $request->integer('conversation') ?: null,
                $request->integer('call') ?: null,
            ),
        ]);
    }

    public function internal(Request $request, CommunicationsWorkspaceProjection $projection): View
    {
        return view('operations.communications.workspace.internal', [
            'workspace' => $projection->internal(),
        ]);
    }

    public function internalChannel(
        InternalChannel $channel,
        Request $request,
        CommunicationsWorkspaceProjection $projection,
    ): View {
        abort_unless(
            $request->user()?->can(ArkCapability::CommunicationsInternalView->value)
                || $request->user()?->can(ArkCapability::OperationsAccess->value),
            403,
        );

        return view('operations.communications.workspace.internal', [
            'workspace' => $projection->internal($channel),
            'activeChannel' => $channel,
        ]);
    }

    private function redirectToNeedsAttention(Request $request): RedirectResponse
    {
        return redirect()->to(CommunicationsNeedsYou::url($request->except('turn')));
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
