<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Mobile\Push\NotifyMobileLifecyclePushAction;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;

class ProcessCallStatusAction
{
    use RecordsCallSessionsViaSessionEvents;

    public function __construct(
        private readonly CustomerCallContextResolver $callContextResolver,
        private readonly IncomingCallContextBroadcaster $broadcaster,
        private readonly CallSessionOwnershipAssigner $ownershipAssigner,
    ) {}

    public function execute(IncomingCallPayload $payload): ?CallSession
    {
        $session = $this->updateCallSessionStatus($payload);

        if ($session === null) {
            return null;
        }

        $this->ownershipAssigner->assignFromStatusCallback($session, $payload->rawPayload);

        $session->load(['customer', 'owner']);

        $lookupPhone = $session->direction === CallSessionDirection::Outbound
            ? ($session->normalized_to ?? $session->normalized_from)
            : (app(InboundCallerDisplayPhone::class)->normalizedForSession($session) ?? $session->normalized_from);

        $context = $session->customer_id !== null
            ? $this->callContextResolver->resolveForCustomer($session->customer)
            : $this->callContextResolver->resolve($lookupPhone);

        $this->broadcaster->broadcastUpdate($session, $context);

        if (
            $session->provider === TelephonyProviderType::Twilio
            && $session->direction === CallSessionDirection::Inbound
            && in_array($session->status, [CallSessionStatus::Completed, CallSessionStatus::Missed, CallSessionStatus::Failed], true)
        ) {
            app(TelephonyRingLegCanceler::class)->cancelAllOutboundLegs($session->provider_call_sid);
        }

        if ($session->direction === CallSessionDirection::Inbound && $session->status === CallSessionStatus::Missed) {
            app(NotifyMobileLifecyclePushAction::class)->forMissedCall($session);
            app(ScheduleMissedCallRescueAction::class)->execute($session);
        }

        return $session;
    }
}
