<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\PhoneNumber;

class TelephonyAnsweredEndpointResolver
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function resolveFromTwilioStatusPayload(array $payload): ?TelephonyEndpoint
    {
        $candidates = collect([
            $payload['Called'] ?? null,
            $payload['To'] ?? null,
        ])
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => trim($value))
            ->unique()
            ->values();

        if ($candidates->isEmpty()) {
            return null;
        }

        return TelephonyEndpoint::query()
            ->whereNotNull('user_id')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->first(fn (TelephonyEndpoint $endpoint): bool => $candidates->contains(
                fn (string $candidate): bool => $this->matches($endpoint, $candidate),
            ));
    }

    private function matches(TelephonyEndpoint $endpoint, string $candidate): bool
    {
        $dialDestination = $endpoint->dialDestination();

        if ($dialDestination === '') {
            return false;
        }

        if ($endpoint->type === TelephonyEndpointType::Cell) {
            $normalizedCandidate = PhoneNumber::normalize($candidate) ?? PhoneNumber::digits($candidate);

            return $normalizedCandidate !== '' && $normalizedCandidate === $dialDestination;
        }

        $normalizedCandidate = TelephonySipDestination::normalize($candidate);

        return strcasecmp($normalizedCandidate, $dialDestination) === 0
            || strcasecmp($candidate, $endpoint->destination) === 0;
    }
}
