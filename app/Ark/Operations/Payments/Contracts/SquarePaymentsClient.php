<?php

namespace App\Ark\Operations\Payments\Contracts;

use App\Ark\Operations\Payments\PaymentGatewayAttempt;
use App\Ark\Operations\Payments\SquarePaymentResult;
use App\Ark\Operations\Payments\SquareTerminalCheckoutResult;
use App\Ark\Operations\Payments\SquareTerminalDeviceCodeResult;

interface SquarePaymentsClient
{
    public function createTerminalCheckout(PaymentGatewayAttempt $attempt): SquareTerminalCheckoutResult;

    public function getTerminalCheckout(string $checkoutId): SquareTerminalCheckoutResult;

    public function cancelTerminalCheckout(string $checkoutId): SquareTerminalCheckoutResult;

    public function createKeyedPayment(PaymentGatewayAttempt $attempt, string $sourceId): SquarePaymentResult;

    public function getPayment(string $paymentId): SquarePaymentResult;

    public function createTerminalDeviceCode(?string $name = null): SquareTerminalDeviceCodeResult;

    public function getTerminalDeviceCode(string $deviceCodeId): SquareTerminalDeviceCodeResult;
}
