<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Platform\Payments\ArkPaymentsClient;
use App\Ark\Platform\PlatformConnection;
use RuntimeException;

final class RequestPlatformPaymentCaptureAction
{
    public function __construct(
        private readonly ArkPaymentsClient $payments,
        private readonly CardPresentCaptureProjection $capture,
        private readonly ApplyPaymentGatewayCaptureResultAction $apply,
    ) {}

    public function execute(PaymentGatewayAttempt $attempt, ?string $sourceToken = null): PaymentGatewayAttempt
    {
        $method = $attempt->capture_surface === PaymentCaptureSurface::Terminal ? 'terminal' : 'keyed';

        if ($method === 'terminal') {
            $deviceRef = $this->capture->readyDevices()[0]['device_ref'] ?? null;
            if ($deviceRef === null) {
                throw new RuntimeException('No card reader is ready.');
            }
        } else {
            $deviceRef = null;
            if ($sourceToken === null || $sourceToken === '') {
                throw new RuntimeException('A card token is required.');
            }
        }

        $shopPublicId = PlatformConnection::current()->shopPublicId() ?? '';
        $kind = $attempt->collectsDeposit() ? 'deposit' : 'payment';

        $payload = [
            'idempotency_key' => $attempt->idempotency_key,
            'capture_attempt_public_id' => $attempt->public_id ?? $attempt->idempotency_key,
            'shop_public_id' => $shopPublicId,
            'amount_cents' => $attempt->amount_cents,
            'currency' => $attempt->currency,
            'capture_method' => $method,
            'reference' => $attempt->repairOrder?->repair_order_id
                ? 'RO '.$attempt->repairOrder->repair_order_id
                : $attempt->referenceId(),
            'device_ref' => $deviceRef,
            'source_token' => $sourceToken,
            'context' => [
                'kind' => $kind,
                'repair_order_id' => $attempt->repair_order_id,
                'customer_id' => $attempt->customer_id,
            ],
        ];

        $result = $this->payments->createCapture($payload);

        if (($result['ok'] ?? false) !== true) {
            $reason = (string) ($result['message'] ?? $result['reason_code'] ?? 'Card capture was rejected.');
            $attempt->forceFill([
                'status' => PaymentGatewayAttemptStatus::Failed,
                'failure_reason' => $reason,
                'completed_at' => now(),
            ])->save();

            throw new RuntimeException($reason);
        }

        $status = strtolower((string) ($result['status'] ?? ''));
        if ($status === 'failed') {
            $applied = $this->apply->apply($attempt->refresh(), $result);
            throw new RuntimeException(
                (string) ($applied->failure_reason ?? $result['message'] ?? 'Card capture failed.')
            );
        }

        if (in_array($status, ['cancelled', 'canceled'], true)) {
            return $this->apply->apply($attempt->refresh(), $result);
        }

        if ($status === 'succeeded') {
            return $this->apply->apply($attempt->refresh(), $result);
        }

        if ($status === 'reconciliation_required') {
            throw new RuntimeException(
                (string) ($result['message'] ?? 'Card capture needs to be checked before retrying.')
            );
        }

        $refs = is_array($result['provider_refs'] ?? null) ? $result['provider_refs'] : [];
        $checkoutId = isset($refs['terminal_checkout_id']) ? (string) $refs['terminal_checkout_id'] : null;
        if ($checkoutId !== null && $checkoutId !== '') {
            $attempt->forceFill(['square_checkout_id' => $checkoutId])->save();
        }

        return $attempt->refresh();
    }
}
