<?php

namespace App\Ark\Operations\Telephony;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TelephonyCallbackStore
{
    private const CACHE_PREFIX = 'telephony:callback:';

    private const TWIML_PREFIX = 'telephony:callback:twiml:';

    private const LOCK_PREFIX = 'telephony:callback:lock:';

    public function issue(TelephonyCallbackIntent $intent): string
    {
        $token = Str::random(40);

        Cache::put($this->cacheKey($token), $this->toCache($intent), now()->addMinutes(10));

        return $token;
    }

    public function find(string $token): ?TelephonyCallbackIntent
    {
        return $this->fromCache(Cache::get($this->cacheKey($token)));
    }

    public function forget(string $token): void
    {
        Cache::forget($this->cacheKey($token));
    }

    public function rememberTwiml(string $token, string $twiml): void
    {
        Cache::put($this->twimlCacheKey($token), $twiml, now()->addMinutes(10));
    }

    public function findTwiml(string $token): ?string
    {
        $twiml = Cache::get($this->twimlCacheKey($token));

        return is_string($twiml) && $twiml !== '' ? $twiml : null;
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function locked(string $token, callable $callback): mixed
    {
        return Cache::lock(self::LOCK_PREFIX.$token, 10)->block(5, $callback);
    }

    /**
     * @return array<string, mixed>
     */
    private function toCache(TelephonyCallbackIntent $intent): array
    {
        return [
            'initiated_by_user_id' => $intent->initiatedByUserId,
            'endpoint_id' => $intent->endpointId,
            'customer_e164' => $intent->customerE164,
            'normalized_customer_phone' => $intent->normalizedCustomerPhone,
            'customer_id' => $intent->customerId,
            'repair_order_id' => $intent->repairOrderId,
        ];
    }

    private function fromCache(mixed $stored): ?TelephonyCallbackIntent
    {
        if ($stored instanceof TelephonyCallbackIntent) {
            return $stored;
        }

        if (! is_array($stored)) {
            return null;
        }

        if (! isset($stored['initiated_by_user_id'], $stored['endpoint_id'], $stored['customer_e164'], $stored['normalized_customer_phone'])) {
            return null;
        }

        return new TelephonyCallbackIntent(
            initiatedByUserId: (int) $stored['initiated_by_user_id'],
            endpointId: (int) $stored['endpoint_id'],
            customerE164: (string) $stored['customer_e164'],
            normalizedCustomerPhone: (string) $stored['normalized_customer_phone'],
            customerId: isset($stored['customer_id']) ? (int) $stored['customer_id'] : null,
            repairOrderId: isset($stored['repair_order_id']) ? (int) $stored['repair_order_id'] : null,
        );
    }

    private function cacheKey(string $token): string
    {
        return self::CACHE_PREFIX.$token;
    }

    private function twimlCacheKey(string $token): string
    {
        return self::TWIML_PREFIX.$token;
    }
}
