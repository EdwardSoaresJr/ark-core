<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\MobileVoice\MobileVoiceEndpointRegistrar;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class TelephonyRingGroup
{
    public function __construct(
        private readonly StaffCallPresence $presence,
        private readonly MobileVoiceEndpointRegistrar $mobileVoiceEndpoints,
    ) {}

    /**
     * @return EloquentCollection<int, TelephonyEndpoint>
     */
    public function enabledEndpoints(): EloquentCollection
    {
        return TelephonyEndpoint::query()
            ->with('user')
            ->where('enabled', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->filter(fn (TelephonyEndpoint $endpoint): bool => $endpoint->dialDestination() !== '')
            ->values();
    }

    /**
     * @return EloquentCollection<int, TelephonyEndpoint>
     */
    public function endpointsForIncomingRing(?ShopSettings $settings = null, ?string $callerNumber = null): EloquentCollection
    {
        $settings ??= ShopSettings::current();
        $flow = TelephonyCallFlowSettings::fromShopSettings($settings);

        if (! $flow->isOpenForCaller($callerNumber)) {
            return TelephonyEndpoint::query()->whereRaw('1 = 0')->get();
        }

        $normalizedCaller = PhoneNumber::normalize($callerNumber);

        return $this->enabledEndpoints()
            ->filter(fn (TelephonyEndpoint $endpoint): bool => $this->shouldRingEndpoint($endpoint, $flow, $settings))
            ->filter(fn (TelephonyEndpoint $endpoint): bool => $this->shouldRingEndpointForCaller($endpoint, $normalizedCaller))
            ->values();
    }

    public function hasRingTargets(?ShopSettings $settings = null): bool
    {
        return $this->enabledEndpoints()->isNotEmpty();
    }

    public function hasIncomingRingTargets(?ShopSettings $settings = null, ?string $callerNumber = null): bool
    {
        return $this->endpointsForIncomingRing($settings, $callerNumber)->isNotEmpty();
    }

    public function usesStaggeredRing(?ShopSettings $settings = null, ?string $callerNumber = null): bool
    {
        return $this->endpointsForIncomingRing($settings, $callerNumber)
            ->contains(fn (TelephonyEndpoint $endpoint): bool => $endpoint->ring_delay_seconds > 0);
    }

    public function incomingRingTargetCount(?ShopSettings $settings = null, ?string $callerNumber = null): int
    {
        return $this->endpointsForIncomingRing($settings, $callerNumber)->count();
    }

    public function buildDialChildrenXml(
        ?ShopSettings $settings = null,
        ?string $parentCallSid = null,
        ?string $callerNumber = null,
    ): string {
        return $this->buildDialChildrenForMaxDelay(0, $settings, $parentCallSid, $callerNumber);
    }

    public function buildDialChildrenForMaxDelay(
        int $maxDelaySeconds,
        ?ShopSettings $settings = null,
        ?string $parentCallSid = null,
        ?string $callerNumber = null,
    ): string {
        $endpoints = $this->endpointsForIncomingRing($settings, $callerNumber)
            ->filter(fn (TelephonyEndpoint $endpoint): bool => $endpoint->ring_delay_seconds <= $maxDelaySeconds);

        $singleSipEndpoint = $this->endpointsForIncomingRing($settings, $callerNumber)->count() === 1
            && $this->endpointsForIncomingRing($settings, $callerNumber)->first()?->type === TelephonyEndpointType::Sip;

        return $endpoints
            ->map(fn (TelephonyEndpoint $endpoint): string => $endpoint->toTwimlChild(
                $this->legStatusCallbackUrl($parentCallSid, $endpoint->id),
                $parentCallSid,
                $endpoint->type === TelephonyEndpointType::Sip && $singleSipEndpoint,
            ))
            ->implode('');
    }

    /**
     * @return Collection<int, int>
     */
    public function staggeredDelayTiers(?ShopSettings $settings = null, ?string $callerNumber = null): Collection
    {
        return $this->endpointsForIncomingRing($settings, $callerNumber)
            ->pluck('ring_delay_seconds')
            ->map(fn (mixed $seconds): int => max(0, (int) $seconds))
            ->filter(fn (int $seconds): bool => $seconds > 0)
            ->unique()
            ->sort()
            ->values();
    }

    public function firstStaggeredDelayTier(?ShopSettings $settings = null, ?string $callerNumber = null): ?int
    {
        $tier = $this->staggeredDelayTiers($settings, $callerNumber)->first();

        return $tier === null ? null : (int) $tier;
    }

    /**
     * @return EloquentCollection<int, TelephonyEndpoint>
     */
    public function endpointsForStaggeredTier(
        int $previousMaxDelaySeconds,
        int $maxDelaySeconds,
        ?ShopSettings $settings = null,
        ?string $callerNumber = null,
    ): EloquentCollection {
        return $this->endpointsForIncomingRing($settings, $callerNumber)
            ->filter(fn (TelephonyEndpoint $endpoint): bool => $endpoint->ring_delay_seconds > $previousMaxDelaySeconds
                && $endpoint->ring_delay_seconds <= $maxDelaySeconds)
            ->values();
    }

    private function legStatusCallbackUrl(?string $parentCallSid, int $endpointId): string
    {
        if ($parentCallSid !== null && $parentCallSid !== '' && $endpointId > 0) {
            return '';
        }

        return '';
    }

    private function shouldRingEndpoint(TelephonyEndpoint $endpoint, TelephonyCallFlowSettings $flow, ?ShopSettings $settings = null): bool
    {
        if ($this->deskOnlyInboundRing($settings) && $endpoint->type !== TelephonyEndpointType::Sip) {
            return false;
        }

        if ($endpoint->type === TelephonyEndpointType::Sip) {
            return true;
        }

        if ($endpoint->type === TelephonyEndpointType::MobileApp) {
            if ($endpoint->user_id === null) {
                return false;
            }

            return $this->mobileVoiceEndpoints->isEndpointVoiceReady(
                $endpoint,
                $endpoint->presenceTimeoutMinutes(),
            );
        }

        if ($endpoint->ring_schedule === TelephonyRingSchedule::Always) {
            return true;
        }

        if ($endpoint->user_id === null) {
            return true;
        }

        return $this->presence->isPresent($endpoint->user_id, $endpoint->presenceTimeoutMinutes());
    }

    private function deskOnlyInboundRing(?ShopSettings $settings = null): bool
    {
        $settings ??= ShopSettings::current();
        $raw = $settings->asterisk_voice;

        if (! is_array($raw)) {
            return false;
        }

        $ingressEnabled = filter_var($raw['ingress_enabled'] ?? false, FILTER_VALIDATE_BOOL);

        if (! $ingressEnabled) {
            return false;
        }

        return filter_var($raw['desk_only_inbound_ring'] ?? false, FILTER_VALIDATE_BOOL);
    }

    private function shouldRingEndpointForCaller(TelephonyEndpoint $endpoint, ?string $normalizedCaller): bool
    {
        if ($normalizedCaller === null || $normalizedCaller === '') {
            return true;
        }

        if ($endpoint->type !== TelephonyEndpointType::Cell) {
            return true;
        }

        $destination = PhoneNumber::normalize($endpoint->dialDestination());

        return $destination === null || $destination !== $normalizedCaller;
    }
}
