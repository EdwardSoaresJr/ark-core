<?php

namespace App\Ark\Operations\Telephony\MobileVoice;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class MobileVoiceConnectStore
{
    private const CACHE_PREFIX = 'telephony:mobile-voice:connect:';

    public function issue(MobileVoiceConnectIntent $intent): string
    {
        $token = Str::random(40);

        Cache::put($this->cacheKey($token), [
            'initiated_by_user_id' => $intent->initiatedByUserId,
            'mobile_device_id' => $intent->mobileDeviceId,
            'endpoint_id' => $intent->endpointId,
            'customer_e164' => $intent->customerE164,
            'normalized_customer_phone' => $intent->normalizedCustomerPhone,
            'customer_id' => $intent->customerId,
            'repair_order_id' => $intent->repairOrderId,
        ], now()->addMinutes(10));

        return $token;
    }

    public function find(string $token): ?MobileVoiceConnectIntent
    {
        $stored = Cache::get($this->cacheKey($token));

        if (! is_array($stored)) {
            return null;
        }

        if (! isset($stored['initiated_by_user_id'], $stored['mobile_device_id'], $stored['endpoint_id'], $stored['customer_e164'], $stored['normalized_customer_phone'])) {
            return null;
        }

        return new MobileVoiceConnectIntent(
            initiatedByUserId: (int) $stored['initiated_by_user_id'],
            mobileDeviceId: (int) $stored['mobile_device_id'],
            endpointId: (int) $stored['endpoint_id'],
            customerE164: (string) $stored['customer_e164'],
            normalizedCustomerPhone: (string) $stored['normalized_customer_phone'],
            customerId: isset($stored['customer_id']) ? (int) $stored['customer_id'] : null,
            repairOrderId: isset($stored['repair_order_id']) ? (int) $stored['repair_order_id'] : null,
        );
    }

    public function forget(string $token): void
    {
        Cache::forget($this->cacheKey($token));
    }

    private function cacheKey(string $token): string
    {
        return self::CACHE_PREFIX.$token;
    }
}
