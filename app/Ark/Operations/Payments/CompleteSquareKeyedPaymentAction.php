<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\Payments\Contracts\SquarePaymentsClient;
use App\Ark\Platform\Payments\ManagedPaymentsGate;
use App\Models\User;
use RuntimeException;

final class CompleteSquareKeyedPaymentAction
{
    public function __construct(
        private readonly SquarePaymentsClient $square,
        private readonly CompleteSquarePaymentAction $completePayment,
        private readonly CompleteSquareDepositAction $completeDeposit,
        private readonly RequestPlatformPaymentCaptureAction $platformCapture,
    ) {}

    public function execute(PaymentGatewayAttempt $attempt, string $sourceId, ?User $actor = null): PaymentGatewayAttempt
    {
        abort_if($sourceId === '', 422, 'Square card token is required.');

        abort_unless(
            in_array($attempt->capture_surface, [
                PaymentCaptureSurface::Keyed,
                PaymentCaptureSurface::Portal,
                PaymentCaptureSurface::PortalEstimateDeposit,
                PaymentCaptureSurface::PortalDepositRequest,
                PaymentCaptureSurface::Email,
            ], true),
            422,
            'This payment attempt does not accept keyed card capture.',
        );

        if ($attempt->status === PaymentGatewayAttemptStatus::Completed) {
            return $attempt;
        }

        if (ManagedPaymentsGate::platformCapture() || $attempt->gateway === PaymentGateway::Managed) {
            return $this->platformCapture->execute($attempt, $sourceId);
        }

        try {
            $payment = $this->square->createKeyedPayment($attempt, $sourceId);
        } catch (RuntimeException $exception) {
            $attempt->forceFill([
                'status' => PaymentGatewayAttemptStatus::Failed,
                'failure_reason' => $exception->getMessage(),
                'completed_at' => now(),
            ])->save();

            throw $exception;
        }

        if (! $payment->isCompleted()) {
            $attempt->forceFill([
                'status' => PaymentGatewayAttemptStatus::Failed,
                'failure_reason' => 'Square payment was not completed.',
                'square_payment_id' => $payment->paymentId,
                'completed_at' => now(),
            ])->save();

            throw new RuntimeException('Square payment was not completed.');
        }

        $completer = $attempt->collectsDeposit()
            ? $this->completeDeposit
            : $this->completePayment;

        return $completer->execute(
            $attempt->fresh(),
            $payment->paymentId,
            $payment->amountCents,
            $payment->processingFeeCents,
            $actor,
        );
    }
}
