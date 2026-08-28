<?php

namespace App\Http\Middleware;

use App\Ark\Operations\Communications\AdvisorCommsPressure;
use App\Ark\Operations\Communications\CommunicationsNeedsYou;
use App\Ark\Operations\Communications\CommsPressureSettings;
use App\Ark\Operations\Workboard\WorkboardLens;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdvisorCommsCleared
{
    public function __construct(
        private readonly AdvisorCommsPressure $pressure,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->can(ArkCapability::OperationsAccess->value)) {
            return $next($request);
        }

        $settings = CommsPressureSettings::fromShopSettings();

        if (! $settings->attentionGateEnabled() || ! $request->isMethod('GET')) {
            return $next($request);
        }

        if (WorkboardLens::forUser($user) === WorkboardLens::TECHNICIAN) {
            return $next($request);
        }

        if ($this->isExempt($request) || $this->isReplyDestination($request)) {
            return $next($request);
        }

        if (! $this->pressure->hasUnresolvedPressure($user)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Clear customer comms on Work before continuing.',
                'comms_pressure_required' => true,
                'count' => $this->pressure->unresolvedCount($user),
                'attention_url' => CommunicationsNeedsYou::url(),
            ], 423);
        }

        return redirect()
            ->to(CommunicationsNeedsYou::url())
            ->with('comms_gate', [
                'count' => $this->pressure->unresolvedCount($user),
            ]);
    }

    private function isExempt(Request $request): bool
    {
        if ($request->routeIs(
            'operations.today',
            'operations.business',
            'operations.index',
            'operations.communications.index',
            'operations.communications.attention',
            'operations.communications.inbox',
            'operations.communications.history',
            'operations.communications.calls',
            'operations.communications.internal',
            'operations.communications.internal.channel',
            'operations.communications.workboard',
            'operations.communications.attention-queue',
            'operations.communications.queue',
            // Working the pressure IS these surfaces — gating them locks the
            // advisor out of the workspace they must use to clear the gate.
            'operations.communications.workspace.fragment',
            'operations.communications.recent-activity.fragment',
            'operations.communications.workboard.fragment',
            'operations.owner.call-intelligence*',
            'operations.comms.interrupts',
            'operations.telephony.*',
            'operations.communications.queue.api',
            'operations.conversations.read',
            'operations.conversations.resolve',
            'operations.conversations.link-customer',
            'operations.conversations.messages.store',
            'operations.customers.conversation-messages.store',
            'operations.repair-orders.conversation-actions.*',
            'operations.conversation-attachments.show',
            'profile.*',
            'logout',
            'operations.learn.*',
            'operations.leads.*',
            'operations.portal.customer-activity-interrupt.dismiss',
            'operations.intake.*',
            'operations.shop.*',
            'dev.*',
        )) {
            return true;
        }

        if ($request->routeIs('operations.work.queue')
            && $request->route('queue') === 'comms') {
            return true;
        }

        return $request->is(
            'app/api/*',
            'broadcasting/auth',
        );
    }

    private function isReplyDestination(Request $request): bool
    {
        if ($request->routeIs('operations.customers.show')
            && in_array($request->query('compose'), ['text', 'messenger'], true)) {
            return true;
        }

        if ($request->routeIs('operations.conversations.reply')) {
            return true;
        }

        return $request->routeIs('operations.repair-orders.show')
            && $request->query('compose') === 'text';
    }
}
