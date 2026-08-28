<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\Payments\Contracts\SquarePaymentsClient;
use App\Models\User;
use RuntimeException;

final class PollSquareTerminalCheckoutAction
{
    public function __construct(
        private readonly SquarePaymentsClient $square,
        private readonly SquareAttemptCompleter $completeAttempt,
    ) {}

    public function execute(PaymentGatewayAttempt $attempt, ?User $actor = null): PaymentGatewayAttempt
    {
        abort_unless(
            $attempt->capture_surface === PaymentCaptureSurface::Terminal,
            422,
            'Only terminal payment attempts can be polled.',
        );

        if ($attempt->status === PaymentGatewayAttemptStatus::Completed) {
            return $attempt;
        }

        $checkoutId = $attempt->square_checkout_id;

        if ($checkoutId === null) {
            throw new RuntimeException('Square terminal checkout is missing.');
        }

        $checkout = $this->square->getTerminalCheckout($checkoutId);

        if ($checkout->isCompleted()) {
            $paymentId = $checkout->paymentIds[0] ?? null;

            if ($paymentId === null) {
                throw new RuntimeException('Square terminal checkout completed without a payment id.');
            }

            $payment = $this->square->getPayment($paymentId);

            if (! $payment->isCompleted()) {
                // Checkout can flip to COMPLETED before the payment object is COMPLETED.
                // Keep polling instead of throwing — the next poll usually finishes cleanly.
                return $attempt;
            }

            return $this->completeAttempt->execute(
                $attempt->fresh(),
                $payment->paymentId,
                $payment->amountCents,
                $payment->processingFeeCents,
                $actor,
            );
        }

        if ($checkout->isCanceled()) {
            $attempt->forceFill([
                'status' => PaymentGatewayAttemptStatus::Canceled,
                'failure_reason' => $checkout->cancelReason,
                'completed_at' => now(),
            ])->save();

            return $attempt->refresh();
        }

        return $attempt;
    }
}
