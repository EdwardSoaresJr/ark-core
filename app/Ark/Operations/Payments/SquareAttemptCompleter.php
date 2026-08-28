<?php

namespace App\Ark\Operations\Payments;

use App\Models\User;

final class SquareAttemptCompleter
{
    public function __construct(
        private readonly CompleteSquarePaymentAction $completePayment,
        private readonly CompleteSquareDepositAction $completeDeposit,
    ) {}

    public function execute(
        PaymentGatewayAttempt $attempt,
        string $squarePaymentId,
        int $amountCents,
        ?int $processingFeeCents = null,
        ?User $actor = null,
    ): PaymentGatewayAttempt {
        if ($attempt->collectsDeposit()) {
            return $this->completeDeposit->execute(
                $attempt,
                $squarePaymentId,
                $amountCents,
                $processingFeeCents,
                $actor,
            );
        }

        return $this->completePayment->execute(
            $attempt,
            $squarePaymentId,
            $amountCents,
            $processingFeeCents,
            $actor,
        );
    }
}
