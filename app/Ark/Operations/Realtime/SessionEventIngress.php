<?php

namespace App\Ark\Operations\Realtime;

use App\Ark\Operations\Realtime\Normalizers\TwilioSessionEventNormalizer;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\IncomingCallPayload;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Models\User;

/**
 * Transport ingress: raw provider payload → normalizer → canonical DTO → RecordSessionEventAction.
 */
final class SessionEventIngress
{
    public function __construct(
        private readonly IngestCanonicalSessionEventAction $ingest,
        private readonly TwilioSessionEventNormalizer $twilioNormalizer,
    ) {}

    public function supportsProvider(TelephonyProviderType $provider): bool
    {
        return $provider === TelephonyProviderType::Twilio;
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
        return match ($payload->provider) {
            TelephonyProviderType::Twilio => $this->twilioNormalizer->fromPayload($payload),
            TelephonyProviderType::Fake => null,
        };
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function normalizeRaw(TelephonyProviderType $provider, array $raw): ?CanonicalSessionEvent
    {
        return match ($provider) {
            TelephonyProviderType::Twilio => $this->twilioNormalizer->fromRaw($raw),
            TelephonyProviderType::Fake => null,
        };
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function payloadFromRaw(TelephonyProviderType $provider, array $raw): IncomingCallPayload
    {
        $canonical = $this->normalizeRaw($provider, $raw);

        if ($canonical?->type === SessionEventType::SessionStarted && $canonical->sessionIdentity !== null) {
            $identity = $canonical->sessionIdentity;

            return new IncomingCallPayload(
                provider: $provider,
                providerCallSid: (string) ($identity['provider_call_sid'] ?? ''),
                fromNumber: (string) ($identity['from_number'] ?? ''),
                toNumber: (string) ($identity['to_number'] ?? ''),
                normalizedFrom: (string) ($identity['normalized_from'] ?? ''),
                normalizedTo: isset($identity['normalized_to']) ? (string) $identity['normalized_to'] : null,
                status: \App\Ark\Operations\Telephony\CallSessionStatus::Ringing,
                rawPayload: $raw,
                direction: $identity['direction'] ?? \App\Ark\Operations\Telephony\CallSessionDirection::Inbound,
            );
        }

        $callSid = match ($provider) {
            TelephonyProviderType::Twilio => trim((string) ($raw['CallSid'] ?? '')),
            TelephonyProviderType::Fake => trim((string) ($raw['provider_call_sid'] ?? '')),
        };

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
