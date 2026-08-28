<?php

namespace App\Ark\Operations\Payments;

final class SquareWebhookVerifier
{
    public function __construct(
        private readonly SquareConfiguration $configuration,
    ) {}

    public function isValid(string $notificationUrl, string $rawBody, ?string $signatureHeader): bool
    {
        $signatureKey = $this->configuration->webhookSignatureKey();

        if ($signatureKey === '' || $signatureHeader === null || trim($signatureHeader) === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac(
            'sha256',
            $notificationUrl.$rawBody,
            $signatureKey,
            true,
        ));

        foreach (explode(',', $signatureHeader) as $candidate) {
            $candidate = trim($candidate);

            if ($candidate !== '' && hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
