<?php

namespace App\Ark\Operations\PhoneVerification;

use App\Ark\Operations\Messaging\TwilioMessagingSender;
use App\Ark\Operations\Settings\ShopSettings;

/**
 * Delivers OTP SMS. Transport only — Twilio today; swap sender later without changing callers.
 */
final class PhoneVerificationNotification
{
    public function __construct(
        private readonly TwilioMessagingSender $sender,
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

        $this->sender->send($phoneE164, $body);
    }
}
