<?php

namespace App\Ark\Operations\Telephony;

use Illuminate\Database\UniqueConstraintViolationException;

class CallSessionRecorder
{
    /**
     * @return array{0: CallSession, 1: bool}
     */
    public function record(IncomingCallPayload $payload, ?int $customerId = null): array
    {
        $existing = $this->findByProviderCallSid($payload);

        if ($existing !== null) {
            if ($customerId !== null) {
                $existing->customer_id = $customerId;
            }

            $this->applyStatus($existing, $payload);

            return [$existing, false];
        }

        try {
            $session = CallSession::query()->create([
                'provider' => $payload->provider,
                'provider_call_sid' => $payload->providerCallSid,
                'direction' => $payload->direction,
                'from_number' => $payload->fromNumber,
                'to_number' => $payload->toNumber,
                'normalized_from' => $payload->normalizedFrom,
                'normalized_to' => $payload->normalizedTo,
                'status' => $payload->status,
                'customer_id' => $customerId,
                'started_at' => now(),
                'raw_payload' => $payload->rawPayload,
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->findByProviderCallSid($payload);

            if ($existing === null) {
                throw $exception;
            }

            if ($customerId !== null) {
                $existing->customer_id = $customerId;
            }

            $this->applyStatus($existing, $payload);

            return [$existing, false];
        }

        return [$session, true];
    }

    /**
     * @return array{0: CallSession, 1: bool}|null
     */
    public function updateStatus(IncomingCallPayload $payload): ?array
    {
        $existing = $this->findByProviderCallSid($payload);

        if ($existing === null) {
            return null;
        }

        $this->applyStatus($existing, $payload);

        return [$existing, false];
    }

    private function findByProviderCallSid(IncomingCallPayload $payload): ?CallSession
    {
        return CallSession::query()
            ->where('provider', $payload->provider)
            ->where('provider_call_sid', $payload->providerCallSid)
            ->first();
    }

    private function applyStatus(CallSession $session, IncomingCallPayload $payload): void
    {
        $session->fill([
            'status' => $payload->status,
            'raw_payload' => $payload->rawPayload,
        ]);

        if ($payload->status === CallSessionStatus::Answered && $session->answered_at === null) {
            $session->answered_at = now();
        }

        if (in_array($payload->status, [CallSessionStatus::Completed, CallSessionStatus::Missed, CallSessionStatus::Failed], true)
            && $session->ended_at === null) {
            $session->ended_at = now();
        }

        $session->save();
    }
}
