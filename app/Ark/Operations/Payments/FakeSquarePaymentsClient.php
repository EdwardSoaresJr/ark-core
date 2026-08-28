<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\Payments\Contracts\SquarePaymentsClient;

/**
 * In-memory Square client for automated tests.
 */
final class FakeSquarePaymentsClient implements SquarePaymentsClient
{
    /** @var array<string, SquareTerminalCheckoutResult> */
    private array $checkouts = [];

    /** @var array<string, SquarePaymentResult> */
    private array $payments = [];

    public bool $autoCompleteTerminal = true;

    /** @var array<string, SquareTerminalDeviceCodeResult> */
    private array $deviceCodes = [];

    public function createTerminalCheckout(PaymentGatewayAttempt $attempt): SquareTerminalCheckoutResult
    {
        $checkoutId = 'fake-checkout-'.$attempt->id;
        $paymentId = 'fake-payment-'.$attempt->id;

        $result = new SquareTerminalCheckoutResult(
            checkoutId: $checkoutId,
            status: $this->autoCompleteTerminal ? 'COMPLETED' : 'PENDING',
            paymentIds: $this->autoCompleteTerminal ? [$paymentId] : [],
        );

        $this->checkouts[$checkoutId] = $result;

        if ($this->autoCompleteTerminal) {
            $this->payments[$paymentId] = new SquarePaymentResult(
                paymentId: $paymentId,
                status: 'COMPLETED',
                amountCents: $attempt->amount_cents,
                processingFeeCents: (int) round($attempt->amount_cents * 0.029),
            );
        }

        return $result;
    }

    public function getTerminalCheckout(string $checkoutId): SquareTerminalCheckoutResult
    {
        return $this->checkouts[$checkoutId] ?? new SquareTerminalCheckoutResult(
            checkoutId: $checkoutId,
            status: 'CANCELED',
        );
    }

    public function cancelTerminalCheckout(string $checkoutId): SquareTerminalCheckoutResult
    {
        $result = new SquareTerminalCheckoutResult(
            checkoutId: $checkoutId,
            status: 'CANCELED',
        );
        $this->checkouts[$checkoutId] = $result;

        return $result;
    }

    public function createKeyedPayment(PaymentGatewayAttempt $attempt, string $sourceId): SquarePaymentResult
    {
        $paymentId = 'fake-keyed-'.$attempt->id;
        $result = new SquarePaymentResult(
            paymentId: $paymentId,
            status: 'COMPLETED',
            amountCents: $attempt->amount_cents,
            processingFeeCents: (int) round($attempt->amount_cents * 0.029),
        );
        $this->payments[$paymentId] = $result;

        return $result;
    }

    public function getPayment(string $paymentId): SquarePaymentResult
    {
        return $this->payments[$paymentId] ?? new SquarePaymentResult(
            paymentId: $paymentId,
            status: 'COMPLETED',
            amountCents: 0,
        );
    }

    public function setPaymentStatus(string $paymentId, string $status, ?int $amountCents = null): void
    {
        $existing = $this->payments[$paymentId] ?? null;

        $this->payments[$paymentId] = new SquarePaymentResult(
            paymentId: $paymentId,
            status: $status,
            amountCents: $amountCents ?? $existing?->amountCents ?? 0,
            processingFeeCents: $existing?->processingFeeCents,
        );
    }

    public function createTerminalDeviceCode(?string $name = null): SquareTerminalDeviceCodeResult
    {
        $result = new SquareTerminalDeviceCodeResult(
            id: 'fake-device-code-'.count($this->deviceCodes) + 1,
            code: 'TRXKEB',
            status: 'UNPAIRED',
            pairBy: now()->addMinutes(5)->toIso8601String(),
            deviceId: null,
        );

        $this->deviceCodes[$result->id] = $result;

        return $result;
    }

    public function getTerminalDeviceCode(string $deviceCodeId): SquareTerminalDeviceCodeResult
    {
        return $this->deviceCodes[$deviceCodeId] ?? new SquareTerminalDeviceCodeResult(
            id: $deviceCodeId,
            code: 'TRXKEB',
            status: 'UNPAIRED',
            pairBy: now()->addMinutes(5)->toIso8601String(),
            deviceId: null,
        );
    }

    public function pairTerminalDeviceCode(string $deviceCodeId, string $deviceId = 'fake-terminal-device'): SquareTerminalDeviceCodeResult
    {
        $existing = $this->getTerminalDeviceCode($deviceCodeId);

        $paired = new SquareTerminalDeviceCodeResult(
            id: $existing->id,
            code: $existing->code,
            status: 'PAIRED',
            pairBy: $existing->pairBy,
            deviceId: $deviceId,
        );

        $this->deviceCodes[$deviceCodeId] = $paired;

        return $paired;
    }
}
