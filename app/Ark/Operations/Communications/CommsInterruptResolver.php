<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\Leads\WebsiteLeadInterruptBroadcaster;
use App\Ark\Operations\Leads\WebsiteLeadInterruptDismissal;
use App\Ark\Operations\Portal\PortalCustomerActivityBroadcaster;
use App\Ark\Operations\Portal\PortalCustomerActivityInterruptDismissal;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\IncomingCallContextBroadcaster;
use App\Ark\Operations\Telephony\IncomingCallContextPresenter;
use App\Ark\Operations\Telephony\IncomingCallPopupDismissal;
use App\Ark\Operations\Telephony\TelephonyFeatureCodeDial;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Live interrupt projection for the topbar popup engine.
 *
 * Composes authoritative call + message stores. Not queue authority.
 */
class CommsInterruptResolver
{
    public function __construct(
        private readonly CommunicationsQueueResolver $communicationsQueueResolver,
        private readonly IncomingCallContextPresenter $incomingCallContextPresenter,
        private readonly CustomerCallContextResolver $callContextResolver,
        private readonly IncomingCallPopupDismissal $incomingCallPopupDismissal,
        private readonly WebsiteLeadInterruptDismissal $websiteLeadInterruptDismissal,
        private readonly PortalCustomerActivityInterruptDismissal $portalInterruptDismissal,
    ) {}

    /**
     * @return array{
     *     call: array<string, mixed>|null,
     *     messages: array<int, array<string, mixed>>,
     *     summary: array<string, mixed>,
     * }
     */
    public function resolve(?User $viewer = null): array
    {
        $queue = $this->communicationsQueueResolver->resolveAttention($viewer);
        $cachedCall = Cache::get(IncomingCallContextBroadcaster::cacheKey());
        $call = $this->liveCallInterrupt(is_array($cachedCall) ? $cachedCall : null)
            ?? $this->liveCallFromQueue($queue['calls'] ?? []);

        if ($call !== null && $viewer !== null) {
            $callSessionId = (int) ($call['call_session_id'] ?? 0);

            if ($this->incomingCallPopupDismissal->isDismissed($viewer->id, $callSessionId)) {
                $call = null;
            } elseif ($this->isOwnedByAnotherAdvisor($call, $viewer)) {
                $call = null;
            }
        }

        $messages = $queue['messages'];
        $portal = Cache::get(PortalCustomerActivityBroadcaster::cacheKey());

        if (is_array($portal) && ($portal['state'] ?? null) === 'unread' && ! $this->isPortalInterruptDismissed($portal, $viewer)) {
            array_unshift($messages, $portal);
        }

        $websiteLead = $this->liveWebsiteLeadInterrupt($viewer);

        if ($websiteLead !== null) {
            array_unshift($messages, $websiteLead);
        }

        return [
            'call' => $call,
            'messages' => $messages,
            'summary' => $queue['summary'],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $cachedCall
     * @return array<string, mixed>|null
     */
    private function liveCallInterrupt(?array $cachedCall): ?array
    {
        if ($cachedCall === null) {
            return null;
        }

        $callSessionId = (int) ($cachedCall['call_session_id'] ?? 0);

        if ($callSessionId === 0) {
            return null;
        }

        $session = CallSession::query()
            ->with(['customer', 'owner'])
            ->find($callSessionId);

        if ($session === null || ! $session->isActivelyLive()) {
            return null;
        }

        if (TelephonyFeatureCodeDial::isFeatureCodeSession($session)) {
            return null;
        }

        if ($session->direction !== CallSessionDirection::Inbound) {
            return null;
        }

        $context = $session->customer_id !== null
            ? $this->callContextResolver->resolveForCustomer($session->customer)
            : $this->callContextResolver->resolve($session->normalized_from);

        return array_merge(
            $this->incomingCallContextPresenter->present($session, $context),
            ['kind' => 'call'],
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $calls
     * @return array<string, mixed>|null
     */
    private function liveCallFromQueue(array $calls): ?array
    {
        foreach ($calls as $call) {
            if (($call['is_actively_live'] ?? false) !== true) {
                continue;
            }

            if (($call['direction'] ?? '') !== CallSessionDirection::Inbound->value) {
                continue;
            }

            return array_merge($call, ['kind' => 'call']);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $call
     */
    private function isOwnedByAnotherAdvisor(array $call, User $viewer): bool
    {
        $ownerId = (int) ($call['owned_by_user_id'] ?? 0);

        return $ownerId > 0 && $ownerId !== $viewer->id;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function liveWebsiteLeadInterrupt(?User $viewer): ?array
    {
        $cached = Cache::get(WebsiteLeadInterruptBroadcaster::cacheKey());

        if (! is_array($cached) || ($cached['kind'] ?? null) !== 'website_lead') {
            return null;
        }

        $leadId = (int) ($cached['lead_id'] ?? 0);

        if ($leadId === 0) {
            return null;
        }

        if ($viewer !== null && $this->websiteLeadInterruptDismissal->isDismissed($viewer->id, $leadId)) {
            return null;
        }

        $lead = Lead::query()->find($leadId);

        if ($lead === null || ! $lead->isNotContacted() || $lead->state === LeadState::Spam) {
            Cache::forget(WebsiteLeadInterruptBroadcaster::cacheKey());

            return null;
        }

        return $cached;
    }

    /**
     * @param  array<string, mixed>  $portal
     */
    private function isPortalInterruptDismissed(array $portal, ?User $viewer): bool
    {
        if ($viewer === null) {
            return false;
        }

        $portalInterruptKey = (string) ($portal['portal_interrupt_key'] ?? '');

        return $portalInterruptKey !== ''
            && $this->portalInterruptDismissal->isDismissed($viewer->id, $portalInterruptKey);
    }
}
