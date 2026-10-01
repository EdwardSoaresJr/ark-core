<?php

namespace App\Ark\Operations\EmailVerification;

use App\Ark\Mail\EmailIntent;
use App\Ark\Platform\Mail\ArkMailClient;
use App\Ark\Platform\Mail\ManagedMailGate;
use App\Support\Mail\ShopMailBranding;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Delivers the booking code. Callers own what happens after verify.
 */
final class EmailVerificationNotification
{
    public function __construct(private readonly ArkMailClient $mail) {}

    public function send(string $email, string $plainCode): void
    {
        if (! ManagedMailGate::platformSend()) {
            throw new RuntimeException("Email isn't configured yet.");
        }

        $ttl = max(1, (int) config('email_verification.code_ttl_minutes', 5));

        $result = $this->mail->sendTransactional(EmailIntent::payload(
            operation: 'booking.identity_code',
            to: $email,
            variables: [
                'shop_name' => ShopMailBranding::shopName(),
                'code' => $plainCode,
                'expires_in' => $ttl.' minutes',
            ],
            idempotencyKey: 'booking-identity-'.Str::uuid(),
        ));

        if (($result['ok'] ?? false) !== true) {
            throw new RuntimeException(
                is_string($result['message'] ?? null) ? $result['message'] : 'Email could not be sent.',
            );
        }
    }
}
