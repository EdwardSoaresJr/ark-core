<?php

namespace App\Ark\Operations\EmailVerification;

use App\Ark\Installation\Mail\CustomerOutboundMail;
use App\Ark\Mail\TransactionalMailOperation;
use App\Support\Mail\ShopMailBranding;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Delivers the booking code. Callers own what happens after verify.
 */
final class EmailVerificationNotification
{
    public function __construct(private readonly CustomerOutboundMail $mail) {}

    public function available(): bool
    {
        return $this->mail->canSend();
    }

    public function send(string $email, string $plainCode): void
    {
        if (! $this->mail->canSend()) {
            throw new RuntimeException("Email isn't configured yet.");
        }

        $ttl = max(1, (int) config('email_verification.code_ttl_minutes', 5));

        $result = $this->mail->send(
            operation: TransactionalMailOperation::BookingIdentityCode->value,
            recipientEmail: $email,
            variables: [
                'shop_name' => ShopMailBranding::shopName(),
                'code' => $plainCode,
                'expires_in' => $ttl.' minutes',
            ],
            idempotencyKey: 'booking-identity-'.Str::uuid(),
        );

        if (! $result->ok()) {
            throw new RuntimeException(
                is_string($result->message) ? $result->message : 'Email could not be sent.',
            );
        }
    }
}
