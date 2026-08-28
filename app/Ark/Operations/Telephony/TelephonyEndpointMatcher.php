<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\PhoneNumber;
use App\Models\User;

class TelephonyEndpointMatcher
{
    public function resolveForUser(User $user): ?TelephonyEndpoint
    {
        return TelephonyEndpoint::query()
            ->where('enabled', true)
            ->where('user_id', $user->id)
            ->where('type', '!=', TelephonyEndpointType::MobileApp->value)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (TelephonyEndpoint $endpoint): int => $endpoint->type === TelephonyEndpointType::Sip ? 0 : 1)
            ->first(fn (TelephonyEndpoint $endpoint): bool => $endpoint->dialDestination() !== '');
    }

    public function callbackEndpointIdFor(User $user): int
    {
        return $this->resolveForUser($user)?->id ?? 0;
    }

    public function callbackDestinationFor(User $user): ?string
    {
        $endpoint = $this->resolveForUser($user);

        if ($endpoint !== null) {
            return $this->twilioAddressFromEndpoint($endpoint);
        }

        return PhoneNumber::toE164($user->phone);
    }

    public function canReceiveCallback(User $user): bool
    {
        return filled($this->callbackDestinationFor($user));
    }

    public function mobileCallbackEndpointIdFor(User $user): int
    {
        return $this->resolveCellEndpointFor($user)?->id ?? 0;
    }

    /**
     * Mobile callbacks must reach the advisor's cell — not a desk SIP endpoint.
     */
    public function mobileCallbackDestinationFor(User $user): ?string
    {
        $cellEndpoint = $this->resolveCellEndpointFor($user);

        if ($cellEndpoint !== null) {
            return PhoneNumber::toE164($cellEndpoint->dialDestination());
        }

        return PhoneNumber::toE164($user->phone);
    }

    public function canReceiveMobileCallback(User $user): bool
    {
        return filled($this->mobileCallbackDestinationFor($user));
    }

    private function resolveCellEndpointFor(User $user): ?TelephonyEndpoint
    {
        return TelephonyEndpoint::query()
            ->where('enabled', true)
            ->where('user_id', $user->id)
            ->where('type', TelephonyEndpointType::Cell->value)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->first(fn (TelephonyEndpoint $endpoint): bool => $endpoint->dialDestination() !== '');
    }

    private function twilioAddressFromEndpoint(TelephonyEndpoint $endpoint): ?string
    {
        if ($endpoint->type === TelephonyEndpointType::Sip) {
            $destination = $endpoint->dialDestination();

            return $destination !== '' ? $destination : null;
        }

        return PhoneNumber::toE164($endpoint->dialDestination());
    }

    public function resolveSipOrigin(string $from): ?TelephonyEndpoint
    {
        if (trim($from) === '') {
            return null;
        }

        $normalizedFrom = TelephonySipUri::normalizeForMatch($from);

        return TelephonyEndpoint::query()
            ->where('type', TelephonyEndpointType::Sip)
            ->where('enabled', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->first(fn (TelephonyEndpoint $endpoint): bool => $this->matchesSipEndpoint($endpoint, $from, $normalizedFrom));
    }

    private function matchesSipEndpoint(TelephonyEndpoint $endpoint, string $from, string $normalizedFrom): bool
    {
        $dialDestination = $endpoint->dialDestination();

        if ($dialDestination === '') {
            return false;
        }

        return strcasecmp($normalizedFrom, $dialDestination) === 0
            || strcasecmp(TelephonySipUri::normalizeForMatch($from), $dialDestination) === 0
            || strcasecmp(trim($from), trim($endpoint->destination)) === 0;
    }
}
