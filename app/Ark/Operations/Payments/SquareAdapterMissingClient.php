<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\Payments\Contracts\SquarePaymentsClient;
use RuntimeException;

/**
 * Bound when Square credentials/settings look enabled but the optional adapter is not installed.
 */
final class SquareAdapterMissingClient implements SquarePaymentsClient
{
    private function fail(): never
    {
        throw new RuntimeException(
            'Square payments adapter is not installed. '.SquareSdk::adapterPackageHint()
            .' (pulls square/square; optional — not part of default ARK core).'
        );
    }

    public function createTerminalCheckout(PaymentGatewayAttempt $attempt): SquareTerminalCheckoutResult
    {
        $this->fail();
    }

    public function getTerminalCheckout(string $checkoutId): SquareTerminalCheckoutResult
    {
        $this->fail();
    }

    public function cancelTerminalCheckout(string $checkoutId): SquareTerminalCheckoutResult
    {
        $this->fail();
    }

    public function createKeyedPayment(PaymentGatewayAttempt $attempt, string $sourceId): SquarePaymentResult
    {
        $this->fail();
    }

    public function getPayment(string $paymentId): SquarePaymentResult
    {
        $this->fail();
    }

    public function createTerminalDeviceCode(?string $name = null): SquareTerminalDeviceCodeResult
    {
        $this->fail();
    }

    public function getTerminalDeviceCode(string $deviceCodeId): SquareTerminalDeviceCodeResult
    {
        $this->fail();
    }
}
