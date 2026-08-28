<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Realtime\SessionEventIngress;

trait RecordsCallSessionsViaSessionEvents
{
    /**
     * @return array{0: CallSession, 1: bool}
     */
    protected function recordCallSession(
        IncomingCallPayload $payload,
        ?int $customerId = null,
    ): array {
        $ingress = app(SessionEventIngress::class);

        if ($ingress->supportsProvider($payload->provider)) {
            return $ingress->ingestFromPayload($payload, $customerId);
        }

        return app(CallSessionRecorder::class)->record($payload, $customerId);
    }

    protected function updateCallSessionStatus(IncomingCallPayload $payload): ?CallSession
    {
        $ingress = app(SessionEventIngress::class);

        if ($ingress->supportsProvider($payload->provider)) {
            [$session] = $ingress->ingestFromPayload($payload);

            return $session;
        }

        $result = app(CallSessionRecorder::class)->updateStatus($payload);

        if ($result === null) {
            return null;
        }

        return $result[0];
    }
}
