<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\Payments\Contracts\SquarePaymentsClient;

final class ProcessSquareWebhookAction
{
    public function __construct(
        private readonly SquarePaymentsClient $square,
        private readonly SquareAttemptCompleter $completeAttempt,
        private readonly ApplySquareTerminalPairingAction $applyTerminalPairing,
    ) {}

    public function execute(array $payload): void
    {
        $type = (string) ($payload['type'] ?? '');

        if ($type === 'device.code.paired') {
            $this->handleDeviceCodePaired($payload);

            return;
        }

        if ($type !== 'payment.updated' && $type !== 'payment.created') {
            return;
        }

        $payment = data_get($payload, 'data.object.payment', []);

        if (! is_array($payment)) {
            return;
        }

        $status = strtoupper((string) ($payment['status'] ?? ''));

        if ($status !== 'COMPLETED') {
            return;
        }

        $paymentId = (string) ($payment['id'] ?? '');
        $referenceId = (string) ($payment['reference_id'] ?? '');
        $amountCents = (int) data_get($payment, 'amount_money.amount', 0);

        if ($paymentId === '' || $amountCents <= 0) {
            return;
        }

        $attempt = PaymentGatewayAttempt::query()
            ->where(function ($query) use ($paymentId, $referenceId): void {
                $query->where('square_payment_id', $paymentId);

                if ($referenceId !== '' && str_starts_with($referenceId, 'ark-pay-')) {
                    $attemptId = (int) str_replace('ark-pay-', '', $referenceId);
                    $query->orWhere('id', $attemptId);
                }
            })
            ->orderByDesc('id')
            ->first();

        if ($attempt === null) {
            return;
        }

        if ($attempt->status === PaymentGatewayAttemptStatus::Completed) {
            return;
        }

        $processingFeeCents = data_get($payment, 'processing_fee.0.amount_money.amount');

        $this->completeAttempt->execute(
            $attempt,
            $paymentId,
            $amountCents,
            is_numeric($processingFeeCents) ? (int) $processingFeeCents : null,
            $attempt->initiatedBy,
        );
    }

    private function handleDeviceCodePaired(array $payload): void
    {
        $deviceId = (string) data_get($payload, 'data.object.device_code.device_id', '');

        $this->applyTerminalPairing->execute($deviceId);
    }
}
