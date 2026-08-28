<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Mobile\Push\NotifyMobileInboundCallAction;
use App\Ark\Operations\Conversations\CustomerCallContext;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;

class ProcessIncomingCallAction
{
    use RecordsCallSessionsViaSessionEvents;

    public function __construct(
        private readonly CustomerCallContextResolver $callContextResolver,
        private readonly IncomingCallContextBroadcaster $broadcaster,
        private readonly CallSessionRepairOrderLinker $repairOrderLinker,
        private readonly NotifyMobileInboundCallAction $notifyMobileInbound,
    ) {}

    /**
     * @return array{session: CallSession, context: ?CustomerCallContext, created: bool}
     */
    public function execute(IncomingCallPayload $payload): array
    {
        if (TelephonyFeatureCodeDial::shouldIgnoreIncoming($payload)
            || TelephonyExtensionLegDial::shouldIgnoreIncoming($payload)) {
            return [
                'session' => null,
                'context' => null,
                'created' => false,
            ];
        }

        $context = $this->callContextResolver->resolve($payload->normalizedFrom);

        [$session, $created] = $this->recordCallSession(
            $payload,
            $context?->customer?->id,
        );

        $this->repairOrderLinker->linkMostRecentOpenRepairOrder($session, $context?->customer);

        if ($created) {
            $this->broadcaster->broadcast($session, $context);
            $this->notifyMobileInbound->execute($session);
        }

        return [
            'session' => $session,
            'context' => $context,
            'created' => $created,
        ];
    }
}
