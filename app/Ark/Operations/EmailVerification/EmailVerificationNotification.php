<?php

namespace App\Ark\Operations\EmailVerification;

use App\Mail\BookIdentityCodeMail;
use App\Support\Mail\ShopMailBranding;
use Illuminate\Support\Facades\Mail;

/**
 * Delivers OTP email. Transport only — callers own what happens after verify.
 */
final class EmailVerificationNotification
{
    public function send(string $email, string $plainCode): void
    {
        $ttl = max(1, (int) config('email_verification.code_ttl_minutes', 5));

        Mail::to($email)->send(new BookIdentityCodeMail(
            shopName: ShopMailBranding::shopName(),
            plainCode: $plainCode,
            ttlMinutes: $ttl,
        ));
    }
}
