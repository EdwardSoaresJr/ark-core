<?php

namespace App\Ark\Operations\Realtime;

use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\IncomingCallPayload;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Models\User;

final class SessionEventIngress
{
    public function __construct(
        private readonly IngestCanonicalSessionEventAction $ingest,
    ) {}

    public function supportsProvider(TelephonyProviderType $provider): bool
    {
        return false;
    }

    /**
     * @return array{0: CallSession|null, 1: bool}
     */
    public function ingestFromPayload(IncomingCallPayload $payload, ?int $customerId = null, ?User $actor = null): array
    {
        $canonical = $this->normalizePayload($payload);

        if ($canonical === null) {
            $existing = CallSession::query()
                ->where('provider', $payload->provider)
                ->where('provider_call_sid', $payload->providerCallSid)
                ->first();

            return [$existing, false];
        }

        return $this->ingest->ingest($payload, $canonical, $customerId, $actor);
    }

    /**
     * @param  list<array<string, mixed>>  $rawEvents
     */
    public function ingestRawStream(
        TelephonyProviderType $provider,
        array $rawEvents,
        ?int $customerId = null,
    ): CallSession {
        $session = null;

        foreach ($rawEvents as $raw) {
            $canonical = $this->normalizeRaw($provider, $raw);

            if ($canonical === null) {
                continue;
            }

            $payload = $this->payloadFromRaw($provider, $raw);
            [$session] = $this->ingest->ingest($payload, $canonical, $customerId);
        }

        if ($session === null) {
            throw new \RuntimeException('Provider stream produced no session.');
        }

        return $session->fresh(['sessionEvents']);
    }

    /**
     * @param  list<array<string, mixed>>  $rawEvents
     */
    public function normalizeRawStream(TelephonyProviderType $provider, array $rawEvents): CanonicalSessionStream
    {
        $events = [];

        foreach ($rawEvents as $raw) {
            $canonical = $this->normalizeRaw($provider, $raw);

            if ($canonical !== null) {
                $events[] = $canonical;
            }
        }

        return new CanonicalSessionStream($events);
    }

    public function normalizePayload(IncomingCallPayload $payload): ?CanonicalSessionEvent
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function normalizeRaw(TelephonyProviderType $provider, array $raw): ?CanonicalSessionEvent
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function payloadFromRaw(TelephonyProviderType $provider, array $raw): IncomingCallPayload
    {
        $callSid = trim((string) ($raw['CallSid'] ?? $raw['provider_call_sid'] ?? ''));

        return new IncomingCallPayload(
            provider: $provider,
            providerCallSid: $callSid,
            fromNumber: (string) ($raw['From'] ?? $raw['caller_id'] ?? ''),
            toNumber: (string) ($raw['To'] ?? $raw['called_extension'] ?? ''),
            normalizedFrom: (string) ($raw['normalized_from'] ?? ''),
            normalizedTo: null,
            status: \App\Ark\Operations\Telephony\CallSessionStatus::Ringing,
            rawPayload: $raw,
        );
    }
}
