<?php

namespace App\Ark\Operations\Telephony;

use Illuminate\Support\Facades\Cache;

class TelephonyRingState
{
    private const CACHE_PREFIX = 'telephony:ring:';

    /**
     * @param  array<int, string>  $outboundCallSids
     */
    public function initialize(
        string $parentCallSid,
        string $conferenceName,
        string $shopCallerId,
        array $outboundCallSids = [],
        ?string $customerCallerId = null,
    ): void {
        Cache::put($this->cacheKey($parentCallSid), [
            'parent_call_sid' => $parentCallSid,
            'conference_name' => $conferenceName,
            'shop_caller_id' => $shopCallerId,
            'customer_caller_id' => $customerCallerId,
            'outbound_call_sids' => $outboundCallSids,
            'answered' => false,
            'answered_endpoint_id' => null,
            'cell_screening' => false,
            'cell_screening_endpoint_id' => null,
        ], now()->addHour());
    }

    public function initializeParallel(string $parentCallSid, ?string $customerCallerId = null): void
    {
        Cache::put($this->cacheKey($parentCallSid), [
            'parent_call_sid' => $parentCallSid,
            'conference_name' => null,
            'shop_caller_id' => null,
            'customer_caller_id' => $customerCallerId,
            'outbound_call_sids' => [],
            'answered' => false,
            'answered_endpoint_id' => null,
            'cell_screening' => false,
            'cell_screening_endpoint_id' => null,
        ], now()->addHour());
    }

    /**
     * @return array{
     *     parent_call_sid: string,
     *     conference_name: string,
     *     shop_caller_id: ?string,
     *     customer_caller_id: ?string,
     *     conference_name: ?string,
     *     outbound_call_sids: array<int, string>,
     *     answered: bool,
     *     answered_endpoint_id: ?int
     * }|null
     */
    public function get(string $parentCallSid): ?array
    {
        $state = Cache::get($this->cacheKey($parentCallSid));

        return is_array($state) ? $state : null;
    }

    public function rememberOutboundCall(string $parentCallSid, int $endpointId, string $outboundCallSid): void
    {
        $state = $this->get($parentCallSid);

        if ($state === null) {
            return;
        }

        $state['outbound_call_sids'][$endpointId] = $outboundCallSid;

        Cache::put($this->cacheKey($parentCallSid), $state, now()->addHour());
    }

    public function markAnswered(string $parentCallSid, ?int $endpointId = null): void
    {
        $state = $this->get($parentCallSid);

        if ($state === null) {
            return;
        }

        $state['answered'] = true;
        $state['cell_screening'] = false;
        $state['cell_screening_endpoint_id'] = null;

        if ($endpointId !== null) {
            $state['answered_endpoint_id'] = $endpointId;
        }

        Cache::put($this->cacheKey($parentCallSid), $state, now()->addHour());
    }

    public function markCellScreening(string $parentCallSid, int $endpointId): void
    {
        $state = $this->get($parentCallSid);

        if ($state === null || ($state['answered'] ?? false)) {
            return;
        }

        $state['cell_screening'] = true;
        $state['cell_screening_endpoint_id'] = $endpointId;

        Cache::put($this->cacheKey($parentCallSid), $state, now()->addHour());
    }

    public function markExpanded(string $parentCallSid, int $maxDelaySeconds): void
    {
        $state = $this->get($parentCallSid);

        if ($state === null) {
            return;
        }

        $state['expanded_max_delay_seconds'] = max(
            (int) ($state['expanded_max_delay_seconds'] ?? -1),
            $maxDelaySeconds,
        );

        Cache::put($this->cacheKey($parentCallSid), $state, now()->addHour());
    }

    public function clearOutboundLegs(string $parentCallSid): void
    {
        $state = $this->get($parentCallSid);

        if ($state === null) {
            return;
        }

        $state['outbound_call_sids'] = [];

        Cache::put($this->cacheKey($parentCallSid), $state, now()->addHour());
    }

    public function forget(string $parentCallSid): void
    {
        Cache::forget($this->cacheKey($parentCallSid));
    }

    private function cacheKey(string $parentCallSid): string
    {
        return self::CACHE_PREFIX.$parentCallSid;
    }
}
