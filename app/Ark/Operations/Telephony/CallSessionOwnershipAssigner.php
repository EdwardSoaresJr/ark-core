<?php

namespace App\Ark\Operations\Telephony;

class CallSessionOwnershipAssigner
{
    public function __construct(
        private readonly TelephonyAnsweredEndpointResolver $answeredEndpointResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function assignFromStatusCallback(CallSession $session, array $rawPayload): bool
    {
        if ($session->owned_by_user_id !== null) {
            return false;
        }

        if ($session->status !== CallSessionStatus::Answered) {
            return false;
        }

        $endpoint = $this->answeredEndpointResolver->resolveFromTwilioStatusPayload($rawPayload);

        if ($endpoint?->user_id === null) {
            return false;
        }

        $session->forceFill([
            'owned_by_user_id' => $endpoint->user_id,
            'owned_at' => now(),
        ])->save();

        return true;
    }
}
