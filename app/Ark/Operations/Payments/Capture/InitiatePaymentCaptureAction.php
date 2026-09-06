<?php

namespace App\Ark\Operations\Payments\Capture;

use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Platform\PlatformConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class InitiatePaymentCaptureAction
{
    public function __construct(
        private readonly ArkPaymentsClient $client,
        private readonly ApplyPaymentCaptureResultAction $applyResult,
        private readonly BalanceDueCalculator $balanceDue,
    ) {}

    /**
     * @param  array{
     *     amount_cents: int,
     *     context_kind: PaymentCaptureContextKind,
     *     capture_method: PaymentCaptureMethod,
     *     device_ref?: ?string,
     *     source_token?: ?string,
     *     stub_scenario?: ?string,
     * }  $input
     * @return array{attempt: PaymentCaptureAttempt, cloud: array<string, mixed>, blocked?: string}
     */
    public function execute(RepairOrder $repairOrder, User $actor, array $input): array
    {
        $amountCents = (int) $input['amount_cents'];
        $contextKind = $input['context_kind'];
        $method = $input['capture_method'];

        $this->assertMayCapture($repairOrder, $amountCents, $contextKind);

        $blocking = $this->blockingAmbiguousAttempt($repairOrder, $amountCents);
        if ($blocking !== null) {
            throw ValidationException::withMessages([
                'capture' => 'A payment capture for this amount needs reconciliation before another card charge for the same amount. '.$blocking->public_id,
            ]);
        }

        if (! PlatformConnection::current()->isConnected()) {
            throw ValidationException::withMessages([
                'capture' => 'Payment capture is unavailable — Platform is not connected. Use Record Payment for external card payments.',
            ]);
        }

        $attempt = DB::transaction(function () use ($repairOrder, $actor, $amountCents, $contextKind, $method, $input) {
            $publicId = (string) Str::uuid();

            return PaymentCaptureAttempt::query()->create([
                'public_id' => $publicId,
                'repair_order_id' => $repairOrder->id,
                'customer_id' => $repairOrder->customer_id,
                'amount_cents' => $amountCents,
                'currency' => 'USD',
                'context_kind' => $contextKind,
                'capture_method' => $method,
                'status' => PaymentCaptureAttemptStatus::Pending,
                'idempotency_key' => 'core-'.$publicId,
                'device_ref' => $input['device_ref'] ?? null,
                'initiated_by' => $actor->id,
                'initiated_at' => now(),
            ]);
        });

        $shopPublicId = (string) (PlatformConnection::current()->shopPublicId() ?? '');

        $body = [
            'idempotency_key' => $attempt->idempotency_key,
            'capture_attempt_public_id' => $attempt->public_id,
            'shop_public_id' => $shopPublicId,
            'amount_cents' => $attempt->amount_cents,
            'currency' => $attempt->currency,
            'capture_method' => $method->value,
            'reference' => $attempt->reference(),
            'context' => [
                'kind' => $contextKind->value,
                'repair_order_id' => (string) $repairOrder->id,
                'customer_id' => $repairOrder->customer_id ? (string) $repairOrder->customer_id : null,
            ],
        ];

        if ($method === PaymentCaptureMethod::Terminal) {
            $body['device_ref'] = (string) ($input['device_ref'] ?? '');
        }
        if ($method === PaymentCaptureMethod::Keyed) {
            $body['source_token'] = (string) ($input['source_token'] ?? '');
        }
        if (! empty($input['stub_scenario'])) {
            $body['stub_scenario'] = (string) $input['stub_scenario'];
        }

        $response = $this->client->createCapture($body);

        if (($response['unavailable'] ?? false) === true) {
            $attempt->status = PaymentCaptureAttemptStatus::Failed;
            $attempt->failure_reason = 'cloud_unavailable';
            $attempt->completed_at = now();
            $attempt->save();

            throw ValidationException::withMessages([
                'capture' => 'Payment capture is unavailable — Platform did not respond. Use Record Payment if the card was taken outside ARK.',
            ]);
        }

        if (($response['reason_code'] ?? null) === 'entitlement_unavailable') {
            $attempt->status = PaymentCaptureAttemptStatus::Failed;
            $attempt->failure_reason = 'entitlement_unavailable';
            $attempt->completed_at = now();
            $attempt->save();

            throw ValidationException::withMessages([
                'capture' => 'Payment capture is not enabled for this shop. Use Record Payment for external payments.',
            ]);
        }

        $payload = $response['body'];
        if (($response['ok'] ?? false) !== true && ! isset($payload['status'])) {
            $attempt->status = PaymentCaptureAttemptStatus::Failed;
            $attempt->failure_reason = (string) ($payload['reason_code'] ?? 'capture_rejected');
            $attempt->completed_at = now();
            $attempt->save();

            throw ValidationException::withMessages([
                'capture' => (string) ($payload['message'] ?? 'Payment capture was rejected.'),
            ]);
        }

        $attempt = $this->applyResult->apply($attempt, $payload);

        return [
            'attempt' => $attempt,
            'cloud' => $payload,
        ];
    }

    public function refreshFromCloud(PaymentCaptureAttempt $attempt): PaymentCaptureAttempt
    {
        $response = $this->client->captureStatus($attempt->idempotency_key);

        if (($response['unavailable'] ?? false) === true || ($response['ok'] ?? false) !== true) {
            return $attempt;
        }

        return $this->applyResult->apply($attempt, $response['body']);
    }

    private function assertMayCapture(RepairOrder $repairOrder, int $amountCents, PaymentCaptureContextKind $kind): void
    {
        abort_if($repairOrder->isTerminal(), 422, 'Cannot capture payment on a closed repair order.');
        abort_if($amountCents < 1, 422, 'Amount must be at least $0.01.');

        $balance = $this->balanceDue->forRepairOrder($repairOrder);

        if ($kind === PaymentCaptureContextKind::Payment) {
            abort_unless($balance->hasIssuedInvoice, 422, 'Generate the final invoice before capturing payment.');
            abort_if($balance->balanceDueCents < 1, 422, 'Nothing is due.');
            abort_if($amountCents > $balance->balanceDueCents, 422, 'Amount exceeds settlement balance.');
        }
    }

    private function blockingAmbiguousAttempt(RepairOrder $repairOrder, int $amountCents): ?PaymentCaptureAttempt
    {
        return PaymentCaptureAttempt::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('amount_cents', $amountCents)
            ->whereIn('status', [
                PaymentCaptureAttemptStatus::ReconciliationRequired->value,
                PaymentCaptureAttemptStatus::Pending->value,
                PaymentCaptureAttemptStatus::Accepted->value,
            ])
            ->whereNull('ledger_entry_id')
            ->latest('id')
            ->first();
    }
}
