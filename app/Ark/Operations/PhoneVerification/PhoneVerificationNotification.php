<?php

namespace App\Ark\Operations\PhoneVerification;

use App\Ark\Operations\Messaging\OutboundSmsTransport;
use App\Ark\Operations\Settings\ShopSettings;

/**
 * Delivers OTP SMS via the outbound messaging transport.
 */
final class PhoneVerificationNotification
{
    public function __construct(
        private readonly OutboundSmsTransport $transport,
    ) {}

    public function sendSms(string $phoneE164, string $plainCode): void
    {
        $shopName = trim((string) (ShopSettings::current()->shop_name ?? ''));

        if ($shopName === '') {
            $shopName = (string) config('app.name', 'Your shop');
        }

        $ttl = (int) config('phone_verification.code_ttl_minutes', 5);

        $body = implode("\n\n", [
            $shopName,
            'Your verification code is',
            $plainCode,
            "Expires in {$ttl} minutes.",
            'Reply STOP to opt out.',
        ]);

        $this->transport->send($phoneE164, $body);
    }
}
