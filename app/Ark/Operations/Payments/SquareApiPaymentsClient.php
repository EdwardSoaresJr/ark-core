<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\Payments\Contracts\SquarePaymentsClient;
use RuntimeException;
use Square\Devices\Codes\Requests\CreateDeviceCodeRequest;
use Square\Devices\Codes\Requests\GetCodesRequest;
use Square\Exceptions\SquareApiException;
use Square\Payments\Requests\CreatePaymentRequest;
use Square\Payments\Requests\GetPaymentsRequest;
use Square\Terminal\Checkouts\Requests\CancelCheckoutsRequest;
use Square\Terminal\Checkouts\Requests\CreateTerminalCheckoutRequest;
use Square\Terminal\Checkouts\Requests\GetCheckoutsRequest;
use Square\Types\Currency;
use Square\Types\DeviceCode;
use Square\Types\DeviceCheckoutOptions;
use Square\Types\Money;
use Square\Types\TerminalCheckout;

final class SquareApiPaymentsClient implements SquarePaymentsClient
{
    public function __construct(
        private readonly SquareConfiguration $configuration,
    ) {}

    public function createTerminalCheckout(PaymentGatewayAttempt $attempt): SquareTerminalCheckoutResult
    {
        $deviceId = $this->configuration->terminalDeviceId();

        if ($deviceId === null) {
            throw new RuntimeException('Square terminal device is not configured.');
        }

        $checkout = new TerminalCheckout([
            'amountMoney' => $this->money($attempt->amount_cents),
            'referenceId' => $attempt->referenceId(),
            'note' => sprintf('RO #%d', $attempt->repairOrder->repair_order_id),
            'deviceOptions' => new DeviceCheckoutOptions([
                'deviceId' => $deviceId,
                'skipReceiptScreen' => false,
            ]),
        ]);

        try {
            $response = $this->configuration->client()->terminal->checkouts->create(
                new CreateTerminalCheckoutRequest([
                    'idempotencyKey' => $attempt->idempotency_key,
                    'checkout' => $checkout,
                ]),
            );
        } catch (SquareApiException $exception) {
            throw new SquarePaymentRequestException($this->formatApiError($exception), previous: $exception);
        }

        $created = $response->getCheckout();

        if ($created === null || $created->getId() === null) {
            throw new RuntimeException('Square did not return a terminal checkout.');
        }

        return $this->mapTerminalCheckout($created);
    }

    public function getTerminalCheckout(string $checkoutId): SquareTerminalCheckoutResult
    {
        try {
            $response = $this->configuration->client()->terminal->checkouts->get(
                new GetCheckoutsRequest(['checkoutId' => $checkoutId]),
            );
        } catch (SquareApiException $exception) {
            throw new SquarePaymentRequestException($this->formatApiError($exception), previous: $exception);
        }

        $checkout = $response->getCheckout();

        if ($checkout === null || $checkout->getId() === null) {
            throw new RuntimeException('Square terminal checkout could not be loaded.');
        }

        return $this->mapTerminalCheckout($checkout);
    }

    public function cancelTerminalCheckout(string $checkoutId): SquareTerminalCheckoutResult
    {
        try {
            $response = $this->configuration->client()->terminal->checkouts->cancel(
                new CancelCheckoutsRequest(['checkoutId' => $checkoutId]),
            );
        } catch (SquareApiException $exception) {
            throw new SquarePaymentRequestException($this->formatApiError($exception), previous: $exception);
        }

        $checkout = $response->getCheckout();

        if ($checkout === null || $checkout->getId() === null) {
            throw new RuntimeException('Square terminal checkout could not be canceled.');
        }

        return $this->mapTerminalCheckout($checkout);
    }

    public function createKeyedPayment(PaymentGatewayAttempt $attempt, string $sourceId): SquarePaymentResult
    {
        try {
            $response = $this->configuration->client()->payments->create(
                new CreatePaymentRequest([
                    'sourceId' => $sourceId,
                    'idempotencyKey' => $attempt->idempotency_key,
                    'amountMoney' => $this->money($attempt->amount_cents),
                    'locationId' => $this->configuration->locationId(),
                    'referenceId' => $attempt->referenceId(),
                    'note' => sprintf('RO #%d', $attempt->repairOrder->repair_order_id),
                ]),
            );
        } catch (SquareApiException $exception) {
            throw new SquarePaymentRequestException($this->formatApiError($exception), previous: $exception);
        }

        $payment = $response->getPayment();

        if ($payment === null || $payment->getId() === null) {
            throw new RuntimeException('Square did not return a payment.');
        }

        return $this->mapPayment($payment);
    }

    public function createTerminalDeviceCode(?string $name = null): SquareTerminalDeviceCodeResult
    {
        $locationId = $this->configuration->locationId();

        if ($locationId === '') {
            throw new RuntimeException('Square location ID is not configured.');
        }

        try {
            $response = $this->configuration->client()->devices->codes->create(
                new CreateDeviceCodeRequest([
                    'idempotencyKey' => (string) str()->uuid(),
                    'deviceCode' => new DeviceCode([
                        'name' => filled($name) ? trim($name) : 'ARK Terminal',
                        'productType' => 'TERMINAL_API',
                        'locationId' => $locationId,
                    ]),
                ]),
            );
        } catch (SquareApiException $exception) {
            throw new SquarePaymentRequestException($this->formatApiError($exception), previous: $exception);
        }

        $deviceCode = $response->getDeviceCode();

        if ($deviceCode === null || $deviceCode->getId() === null || $deviceCode->getCode() === null) {
            throw new RuntimeException('Square did not return a terminal device code.');
        }

        return $this->mapDeviceCode($deviceCode);
    }

    public function getTerminalDeviceCode(string $deviceCodeId): SquareTerminalDeviceCodeResult
    {
        try {
            $response = $this->configuration->client()->devices->codes->get(
                new GetCodesRequest(['id' => $deviceCodeId]),
            );
        } catch (SquareApiException $exception) {
            throw new SquarePaymentRequestException($this->formatApiError($exception), previous: $exception);
        }

        $deviceCode = $response->getDeviceCode();

        if ($deviceCode === null || $deviceCode->getId() === null) {
            throw new RuntimeException('Square device code could not be loaded.');
        }

        return $this->mapDeviceCode($deviceCode);
    }

    public function getPayment(string $paymentId): SquarePaymentResult
    {
        try {
            $response = $this->configuration->client()->payments->get(
                new GetPaymentsRequest(['paymentId' => $paymentId]),
            );
        } catch (SquareApiException $exception) {
            throw new SquarePaymentRequestException($this->formatApiError($exception), previous: $exception);
        }

        $payment = $response->getPayment();

        if ($payment === null || $payment->getId() === null) {
            throw new RuntimeException('Square payment could not be loaded.');
        }

        return $this->mapPayment($payment);
    }

    private function money(int $amountCents): Money
    {
        return new Money([
            'amount' => $amountCents,
            'currency' => Currency::Usd->value,
        ]);
    }

    private function mapTerminalCheckout(TerminalCheckout $checkout): SquareTerminalCheckoutResult
    {
        return new SquareTerminalCheckoutResult(
            checkoutId: (string) $checkout->getId(),
            status: (string) ($checkout->getStatus() ?? 'PENDING'),
            paymentIds: $checkout->getPaymentIds() ?? [],
            cancelReason: $checkout->getCancelReason(),
        );
    }

    private function mapDeviceCode(DeviceCode $deviceCode): SquareTerminalDeviceCodeResult
    {
        return new SquareTerminalDeviceCodeResult(
            id: (string) $deviceCode->getId(),
            code: (string) $deviceCode->getCode(),
            status: (string) ($deviceCode->getStatus() ?? 'UNPAIRED'),
            pairBy: $deviceCode->getPairBy(),
            deviceId: $deviceCode->getDeviceId(),
        );
    }

    private function mapPayment(\Square\Types\Payment $payment): SquarePaymentResult
    {
        $amount = $payment->getAmountMoney()?->getAmount();

        return new SquarePaymentResult(
            paymentId: (string) $payment->getId(),
            status: (string) ($payment->getStatus() ?? 'FAILED'),
            amountCents: is_int($amount) ? $amount : 0,
            processingFeeCents: $payment->getProcessingFee()?->getAmountMoney()?->getAmount(),
        );
    }

    private function formatApiError(SquareApiException $exception): string
    {
        return app(SquareApiErrorPresenter::class)->messageFor($exception);
    }
}
