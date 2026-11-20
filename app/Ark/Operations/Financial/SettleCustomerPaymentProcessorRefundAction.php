<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Payments\Capture\ArkPaymentsClient;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttempt;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttemptStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Settles a pending card/processor refund only after Platform reports final successful settlement.
 *
 * Pending, accepted, initiated, or processing refunds stay outstanding. Retries reuse a stable
 * Platform idempotency key and never clear Core liability until a processor refund id is present
 * on a terminal success status. A second settle after success is a no-op.
 */
final class SettleCustomerPaymentProcessorRefundAction
{
    /** @var list<string> */
    private const TERMINAL_SUCCESS = ['succeeded', 'settled', 'completed'];

    /** @var list<string> */
    private const NON_TERMINAL = [
        'pending',
        'accepted',
        'initiated',
        'processing',
        'created',
        'queued',
        'submitted',
    ];

    public function __construct(
        private readonly ArkPaymentsClient $payments,
    ) {}

    /**
     * @return array{receipt: CustomerPaymentReceipt, status: string, provider_refund_id: ?string, replayed: bool}
     */
    public function execute(CustomerPaymentReceipt $receipt, ?User $actor = null): array
    {
        $prepared = DB::transaction(function () use ($receipt): array {
            /** @var CustomerPaymentReceipt $locked */
            $locked = CustomerPaymentReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();

            if ($this->alreadySettled($locked)) {
                return [
                    'mode' => 'already_settled',
                    'receipt' => $locked->fresh(),
                    'status' => 'succeeded',
                    'provider_refund_id' => (string) $locked->refund_evidence_reference,
                    'pending' => 0,
                    'idempotency_key' => null,
                    'attempt' => null,
                ];
            }

            if ($locked->isVoided()) {
                throw ValidationException::withMessages([
                    'receipt' => 'Cannot settle a refund on a voided payment receipt.',
                ]);
            }

            $pending = (int) $locked->refund_pending_cents;
            if ($pending <= 0) {
                throw ValidationException::withMessages([
                    'receipt' => 'This payment has no pending refund/return to settle.',
                ]);
            }

            if (! app(ConfirmCustomerPaymentRefundAction::class)->requiresProcessorConfirmation($locked)) {
                throw ValidationException::withMessages([
                    'receipt' => 'This pending return is a manual tender. Confirm it with typed proof instead.',
                ]);
            }

            $attempt = PaymentCaptureAttempt::query()
                ->where('customer_payment_receipt_id', $locked->id)
                ->where('status', PaymentCaptureAttemptStatus::Succeeded->value)
                ->orderByDesc('id')
                ->first();

            if ($attempt === null || ! filled($attempt->provider_payment_id)) {
                throw ValidationException::withMessages([
                    'receipt' => 'No verified card capture is linked to this receipt, so a processor refund cannot be requested.',
                ]);
            }

            return [
                'mode' => 'request',
                'receipt' => $locked,
                'pending' => $pending,
                // Stable per receipt so retries cannot open a second Platform refund.
                'idempotency_key' => 'ar-refund-'.$locked->id,
                'attempt' => $attempt,
            ];
        });

        if ($prepared['mode'] === 'already_settled') {
            return [
                'receipt' => $prepared['receipt'],
                'status' => (string) $prepared['status'],
                'provider_refund_id' => $prepared['provider_refund_id'],
                'replayed' => true,
            ];
        }

        /** @var PaymentCaptureAttempt $attempt */
        $attempt = $prepared['attempt'];
        $idempotencyKey = (string) $prepared['idempotency_key'];
        $pending = (int) $prepared['pending'];

        $payload = $this->resolvePlatformRefundPayload(
            $idempotencyKey,
            $pending,
            $attempt,
            (int) $prepared['receipt']->id,
        );

        return DB::transaction(function () use ($receipt, $actor, $payload, $pending): array {
            /** @var CustomerPaymentReceipt $locked */
            $locked = CustomerPaymentReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();

            if ($this->alreadySettled($locked)) {
                return [
                    'receipt' => $locked->fresh(),
                    'status' => 'succeeded',
                    'provider_refund_id' => (string) $locked->refund_evidence_reference,
                    'replayed' => true,
                ];
            }

            if ((int) $locked->refund_pending_cents !== $pending) {
                throw ValidationException::withMessages([
                    'receipt' => 'Pending refund amount changed while waiting for the processor. Retry settlement.',
                ]);
            }

            $status = strtolower((string) ($payload['status'] ?? ''));
            $providerRefundId = filled($payload['provider_refund_id'] ?? null)
                ? (string) $payload['provider_refund_id']
                : null;

            if (! $this->isTerminalSuccess($status, $providerRefundId)) {
                throw ValidationException::withMessages([
                    'receipt' => $this->outstandingMessage($status),
                ]);
            }

            $locked->forceFill([
                'refund_pending_cents' => 0,
                'change_cents' => (int) $locked->change_cents + $pending,
                'refund_evidence_kind' => ConfirmCustomerPaymentRefundAction::EVIDENCE_PROCESSOR_SETTLEMENT,
                'refund_evidence_reference' => $providerRefundId,
                'refund_confirmed_at' => now(),
                'refund_confirmed_by' => $actor?->id,
                'notes' => trim(($locked->notes ? $locked->notes.' ' : '')
                    .'Processor refund of $'.number_format($pending / 100, 2)
                    .' settled with refund id "'.$providerRefundId.'"'
                    .($actor?->name ? ' by '.$actor->name : '')
                    .' at '.now()->toDateTimeString().'.'),
            ])->save();

            if ($locked->amount_cents !== $locked->balancedAmountCents()) {
                throw new RuntimeException('Settled processor refund receipt amounts do not balance.');
            }

            return [
                'receipt' => $locked->fresh(),
                'status' => $status,
                'provider_refund_id' => $providerRefundId,
                'replayed' => false,
            ];
        });
    }

    private function alreadySettled(CustomerPaymentReceipt $receipt): bool
    {
        return (int) $receipt->refund_pending_cents === 0
            && $receipt->refund_evidence_kind === ConfirmCustomerPaymentRefundAction::EVIDENCE_PROCESSOR_SETTLEMENT
            && filled($receipt->refund_evidence_reference)
            && $receipt->refund_confirmed_at !== null;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePlatformRefundPayload(
        string $idempotencyKey,
        int $pending,
        PaymentCaptureAttempt $attempt,
        int $receiptId,
    ): array {
        $existing = $this->payments->refundStatus($idempotencyKey);
        if (($existing['unavailable'] ?? false) !== true) {
            $existingBody = $existing['body'] ?? [];
            $existingStatus = strtolower((string) ($existingBody['status'] ?? ''));
            if ($existingStatus !== '' && (int) ($existing['status'] ?? 0) !== 404) {
                if ($this->isNonTerminal($existingStatus)) {
                    throw ValidationException::withMessages([
                        'receipt' => $this->outstandingMessage($existingStatus),
                    ]);
                }
                if ($this->isTerminalSuccess(
                    $existingStatus,
                    filled($existingBody['provider_refund_id'] ?? null) ? (string) $existingBody['provider_refund_id'] : null,
                )) {
                    return $existingBody;
                }
                if (($existing['ok'] ?? false) === true || isset($existingBody['status'])) {
                    throw ValidationException::withMessages([
                        'receipt' => $this->outstandingMessage($existingStatus !== '' ? $existingStatus : 'unknown'),
                    ]);
                }
            }
        }

        $response = $this->payments->createRefund([
            'idempotency_key' => $idempotencyKey,
            'amount_cents' => $pending,
            'currency' => 'USD',
            'capture_idempotency_key' => $attempt->idempotency_key,
            'provider_payment_id' => $attempt->provider_payment_id,
            'cloud_capture_id' => $attempt->cloud_capture_id,
            'customer_payment_receipt_id' => $receiptId,
            'reason' => 'accounts_receivable_overpayment_return',
        ]);

        if (($response['unavailable'] ?? false) === true) {
            throw ValidationException::withMessages([
                'receipt' => 'Card refund processing is unavailable. The pending refund remains open.',
            ]);
        }

        $reason = (string) ($response['reason_code'] ?? '');
        if (in_array($reason, ['not_supported', 'unsupported', 'entitlement_unavailable'], true)
            || (int) ($response['status'] ?? 0) === 404) {
            throw ValidationException::withMessages([
                'receipt' => 'Platform does not yet support verified processor refunds for this shop. '
                    .'The pending amount stays open until processor settlement is available.',
            ]);
        }

        $payload = $response['body'] ?? [];
        if (($response['ok'] ?? false) !== true && ! isset($payload['status'])) {
            throw ValidationException::withMessages([
                'receipt' => is_string($payload['message'] ?? null)
                    ? $payload['message']
                    : 'Processor refund was rejected. The pending amount was not cleared.',
            ]);
        }

        $status = strtolower((string) ($payload['status'] ?? ''));
        if ($this->isNonTerminal($status)
            || ! $this->isTerminalSuccess(
                $status,
                filled($payload['provider_refund_id'] ?? null) ? (string) $payload['provider_refund_id'] : null,
            )) {
            throw ValidationException::withMessages([
                'receipt' => $this->outstandingMessage($status !== '' ? $status : 'unknown'),
            ]);
        }

        return $payload;
    }

    private function isTerminalSuccess(string $status, ?string $providerRefundId): bool
    {
        return in_array($status, self::TERMINAL_SUCCESS, true) && filled($providerRefundId);
    }

    private function isNonTerminal(string $status): bool
    {
        return in_array($status, self::NON_TERMINAL, true);
    }

    private function outstandingMessage(string $status): string
    {
        return 'Processor refund is still '.$status.'. '
            .'Pending refund stays open until Platform reports a final successful refund with a processor refund id. '
            .'Accepted, initiated, or pending processor responses do not clear the liability.';
    }
}
