<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Conversations\CustomerCallContext;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;

class ProcessOutboundCallAction
{
    use RecordsCallSessionsViaSessionEvents;

    public function __construct(
        private readonly CustomerCallContextResolver $callContextResolver,
        private readonly IncomingCallContextBroadcaster $broadcaster,
        private readonly CallSessionRepairOrderLinker $repairOrderLinker,
    ) {}

    /**
     * @return array{session: CallSession, context: ?CustomerCallContext, created: bool}
     */
    public function execute(IncomingCallPayload $payload, ?TelephonyEndpoint $endpoint): array
    {
        $lookupPhone = $payload->normalizedTo ?? $payload->normalizedFrom;
        $context = $lookupPhone !== '' && $lookupPhone !== null
            ? $this->callContextResolver->resolve($lookupPhone)
            : null;

        [$session, $created] = $this->recordCallSession(
            $payload,
            $context?->customer?->id,
        );

        $this->repairOrderLinker->linkMostRecentOpenRepairOrder($session, $context?->customer);

        if ($endpoint?->user_id !== null && $session->owned_by_user_id === null) {
            $session->forceFill([
                'owned_by_user_id' => $endpoint->user_id,
                'owned_at' => now(),
            ])->save();
        }

        if ($created) {
            $this->broadcaster->broadcast($session->fresh(['customer', 'owner']), $context);
        }

        return [
            'session' => $session,
            'context' => $context,
            'created' => $created,
        ];
    }
}
