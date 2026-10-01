<?php

namespace App\Ark\Operations\Payments;

final class CompletePortalKeyedPaymentAction
{
    public function __construct(
        private readonly RequestPlatformPaymentCaptureAction $platformCapture,
    ) {}

    public function execute(PaymentGatewayAttempt $attempt, string $sourceId): PaymentGatewayAttempt
    {
        abort_if($sourceId === '', 422, 'A card token is required.');

        abort_unless(
            in_array($attempt->capture_surface, [
                PaymentCaptureSurface::Portal,
                PaymentCaptureSurface::PortalEstimateDeposit,
                PaymentCaptureSurface::PortalDepositRequest,
            ], true),
            422,
            'This payment attempt does not accept keyed card capture.',
        );

        if ($attempt->status === PaymentGatewayAttemptStatus::Completed) {
            return $attempt;
        }

        return $this->platformCapture->execute($attempt, $sourceId);
    }
}
