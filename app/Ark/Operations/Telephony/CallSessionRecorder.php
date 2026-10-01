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
                'started_at' => $payload->occurredAt ?? now(),
                'raw_payload' => $payload->rawPayload,
                ...$this->initialHostedColumns($payload),
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

    public function noteVoicemailLeft(CallSession $session): void
    {
        if (in_array($session->hosted_outcome, [HostedCallOutcome::Answered, HostedCallOutcome::Completed], true)) {
            return;
        }

        if ($session->hosted_outcome === null && in_array($session->status, [CallSessionStatus::Answered, CallSessionStatus::Completed], true)) {
            return;
        }

        $session->forceFill([
            'hosted_outcome' => HostedCallOutcome::VoicemailLeft,
            'disposition_origin' => 'platform',
            'status' => CallSessionStatus::Missed,
        ])->save();
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
        if ($payload->hostedOutcome !== null) {
            $this->applyHostedOutcome($session, $payload);

            return;
        }

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

    /**
     * @return array<string, mixed>
     */
    private function initialHostedColumns(IncomingCallPayload $payload): array
    {
        if ($payload->hostedOutcome === null) {
            return [];
        }

        $columns = [
            'status' => $payload->hostedOutcome->sessionStatus(),
            'hosted_outcome' => $payload->hostedOutcome,
            'disposition_origin' => 'platform',
        ];

        if ($payload->dialDurationSeconds !== null && $payload->dialDurationSeconds > 0) {
            $columns['dial_duration_seconds'] = $payload->dialDurationSeconds;
        }

        if ($payload->hostedOutcome->wasAnswered() && $payload->answeredAt !== null) {
            $columns['answered_at'] = $payload->answeredAt;
        }

        if ($payload->callEnded) {
            $columns['ended_at'] = $payload->endedAt ?? $payload->occurredAt ?? now();
        }

        return $columns;
    }

    private function applyHostedOutcome(CallSession $session, IncomingCallPayload $payload): void
    {
        $incoming = $payload->hostedOutcome;
        if ($incoming === null) {
            return;
        }

        if ($this->blocksHostedOutcome($session, $incoming)) {
            if ($session->hosted_outcome?->wasAnswered() === true) {
                $this->fillAnsweredFacts($session, $payload);
            }
            if ($payload->callEnded && $session->ended_at === null) {
                $session->ended_at = $payload->endedAt ?? $payload->occurredAt ?? now();
            }
            $session->save();

            return;
        }

        $replacingRecovery = $session->disposition_origin === 'recovery';

        $session->fill([
            'status' => $incoming->sessionStatus(),
            'hosted_outcome' => $incoming,
            'disposition_origin' => 'platform',
            'raw_payload' => $payload->rawPayload,
        ]);

        $this->fillAnsweredFacts($session, $payload);

        if ($replacingRecovery && ! $payload->callEnded) {
            $session->ended_at = null;
        }

        if ($payload->callEnded) {
            if ($session->ended_at === null || $replacingRecovery) {
                $session->ended_at = $payload->endedAt ?? $payload->occurredAt ?? now();
            }
        }

        $session->save();
    }

    private function fillAnsweredFacts(CallSession $session, IncomingCallPayload $payload): void
    {
        if ($session->dial_duration_seconds === null
            && $payload->dialDurationSeconds !== null
            && $payload->dialDurationSeconds > 0) {
            $session->dial_duration_seconds = $payload->dialDurationSeconds;
        }

        if ($session->answered_at === null
            && $payload->hostedOutcome?->wasAnswered()
            && $payload->answeredAt !== null) {
            $session->answered_at = $payload->answeredAt;
        }
    }

    private function blocksHostedOutcome(CallSession $session, HostedCallOutcome $incoming): bool
    {
        $current = $session->hosted_outcome;

        if ($session->disposition_origin !== 'platform' || $current === null) {
            return false;
        }

        if ($incoming === HostedCallOutcome::Ringing && $current !== HostedCallOutcome::Ringing) {
            return true;
        }

        if ($incoming === HostedCallOutcome::Answered && $current !== HostedCallOutcome::Ringing && $current !== HostedCallOutcome::Answered) {
            return true;
        }

        if ($incoming === HostedCallOutcome::Unknown && ! in_array($current, [HostedCallOutcome::Ringing, HostedCallOutcome::Unknown], true)) {
            return true;
        }

        if ($incoming === HostedCallOutcome::VoicemailOffered && in_array($current, [
            HostedCallOutcome::VoicemailLeft,
            HostedCallOutcome::Answered,
            HostedCallOutcome::Completed,
        ], true)) {
            return true;
        }

        if (in_array($incoming, [HostedCallOutcome::Missed, HostedCallOutcome::Failed], true) && in_array($current, [
            HostedCallOutcome::Answered,
            HostedCallOutcome::Completed,
            HostedCallOutcome::VoicemailLeft,
        ], true)) {
            return true;
        }

        return false;
    }
}
