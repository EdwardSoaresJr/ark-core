<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Payments\Capture\ApplyPaymentCaptureResultAction;
use App\Ark\Operations\Payments\Capture\ArkPaymentsClient;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttempt;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttemptStatus;
use App\Ark\Operations\Payments\Capture\PaymentCaptureContextKind;
use App\Ark\Operations\Payments\Capture\PaymentCaptureMethod;
use App\Ark\Operations\Payments\Capture\PaymentCaptureReadinessProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Platform\PlatformConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * One ARK Pay card capture whose success posts a multi-invoice customer payment receipt.
 * Reuses the existing Platform capture contract; does not invent a second processor.
 */
final class InitiateAccountsReceivableCaptureAction
{
    public function __construct(
        private readonly ArkPaymentsClient $client,
        private readonly ApplyPaymentCaptureResultAction $applyResult,
        private readonly BalanceDueCalculator $balanceDue,
        private readonly EstimateTotalsCalculator $totals,
    ) {}

    /**
     * @param  list<array{invoice_id: int, amount_cents: int}>  $allocations
     * @param  array{
     *     capture_method: PaymentCaptureMethod,
     *     device_ref?: ?string,
     *     source_token?: ?string,
     *     stub_scenario?: ?string,
     *     paid_on?: ?string,
     *     intent_key: string,
     *     reference?: ?string,
     *     disposition?: ?OverpaymentDisposition,
     * }  $input
     * @return array{attempt: PaymentCaptureAttempt, cloud: array<string, mixed>}
     */
    public function execute(Customer $customer, array $allocations, ?User $actor, array $input): array
    {
        $intentKey = trim((string) ($input['intent_key'] ?? ''));
        if ($intentKey === '' || preg_match('/^[0-9a-fA-F-]{36}$/', $intentKey) !== 1) {
            throw ValidationException::withMessages([
                'intent_key' => 'A payment submission key is required.',
            ]);
        }

        $normalized = $this->normalizeAndValidate($customer, $allocations);
        $amountCents = array_sum(array_column($normalized, 'amount_cents'));
        $method = $input['capture_method'];
        $disposition = $input['disposition'] ?? OverpaymentDisposition::KeepCredit;
        $reference = filled($input['reference'] ?? null) ? trim((string) $input['reference']) : null;
        $requestFingerprint = $this->submissionFingerprint(
            $customer,
            $amountCents,
            $normalized,
            $disposition,
            $reference,
        );

        $existingByIntent = PaymentCaptureAttempt::query()
            ->where('submission_intent_key', $intentKey)
            ->where('context_kind', PaymentCaptureContextKind::AccountsReceivable->value)
            ->latest('id')
            ->first();

        if ($existingByIntent !== null) {
            $existingFingerprint = $this->attemptFingerprint($existingByIntent);
            if ($existingFingerprint !== null && ! hash_equals($existingFingerprint, $requestFingerprint)) {
                throw ValidationException::withMessages([
                    'intent_key' => 'This payment submission key was already used with different card payment details.',
                ]);
            }

            if ($existingByIntent->customer_payment_receipt_id !== null
                || $existingByIntent->status === PaymentCaptureAttemptStatus::Succeeded) {
                return ['attempt' => $existingByIntent, 'cloud' => []];
            }

            if ($existingByIntent->status->blocksSameAmountCapture()
                || $existingByIntent->status === PaymentCaptureAttemptStatus::ReconciliationRequired) {
                throw ValidationException::withMessages([
                    'capture' => 'This card payment already charged or is awaiting allocation reconciliation. '
                        .'Do not charge again. Capture '.$existingByIntent->public_id
                        .' — retry allocation only.',
                ]);
            }
        }

        if ($method === PaymentCaptureMethod::Terminal) {
            $this->assertAvailableReader((string) ($input['device_ref'] ?? ''));
        }

        if ($method === PaymentCaptureMethod::Keyed && blank($input['source_token'] ?? null)) {
            throw ValidationException::withMessages([
                'source_token' => 'Card details are required for a keyed payment.',
            ]);
        }

        $this->assertLiveCaptureInputs($method, $input);

        $anchorInvoice = EstimateDocument::query()->findOrFail($normalized[0]['invoice_id']);
        /** @var RepairOrder $anchorRo */
        $anchorRo = RepairOrder::query()->findOrFail($anchorInvoice->repair_order_id);
        $anchorRo->ensureOpenForSettlement();

        $blocking = PaymentCaptureAttempt::query()
            ->where('customer_id', $customer->id)
            ->where('amount_cents', $amountCents)
            ->where('context_kind', PaymentCaptureContextKind::AccountsReceivable->value)
            ->whereIn('status', [
                PaymentCaptureAttemptStatus::ReconciliationRequired->value,
                PaymentCaptureAttemptStatus::Pending->value,
                PaymentCaptureAttemptStatus::Accepted->value,
            ])
            ->whereNull('ledger_entry_id')
            ->whereNull('customer_payment_receipt_id')
            ->latest('id')
            ->first();

        if ($blocking !== null) {
            throw ValidationException::withMessages([
                'capture' => 'A card capture for this amount needs reconciliation before another charge. '
                    .$blocking->public_id
                    .' — retry allocation only, do not charge again.',
            ]);
        }

        $attempt = DB::transaction(function () use ($customer, $anchorRo, $actor, $amountCents, $method, $input, $normalized, $intentKey, $disposition, $reference, $requestFingerprint) {
            $publicId = (string) Str::uuid();

            return PaymentCaptureAttempt::query()->create([
                'public_id' => $publicId,
                'repair_order_id' => $anchorRo->id,
                'customer_id' => $customer->id,
                'amount_cents' => $amountCents,
                'currency' => 'USD',
                'context_kind' => PaymentCaptureContextKind::AccountsReceivable,
                'capture_method' => $method,
                'status' => PaymentCaptureAttemptStatus::Pending,
                'idempotency_key' => 'core-'.$publicId,
                'submission_intent_key' => $intentKey,
                'device_ref' => $input['device_ref'] ?? null,
                'initiated_by' => $actor?->id,
                'initiated_at' => now(),
                'paid_on' => filled($input['paid_on'] ?? null) ? (string) $input['paid_on'] : null,
                'allocation_plan' => [
                    'intent_key' => $intentKey,
                    'reference' => $reference,
                    'disposition' => $disposition instanceof OverpaymentDisposition
                        ? $disposition->value
                        : (string) $disposition,
                    'allocations' => $normalized,
                    'payload_fingerprint' => $requestFingerprint,
                ],
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
                'kind' => PaymentCaptureContextKind::AccountsReceivable->value,
                'repair_order_id' => (string) $anchorRo->id,
                'customer_id' => (string) $customer->id,
                'invoice_ids' => array_column($normalized, 'invoice_id'),
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
                'capture' => 'Card processing is unavailable. Record an external card payment instead.',
            ]);
        }

        if (($response['reason_code'] ?? null) === 'entitlement_unavailable') {
            $attempt->status = PaymentCaptureAttemptStatus::Failed;
            $attempt->failure_reason = 'entitlement_unavailable';
            $attempt->completed_at = now();
            $attempt->save();

            throw ValidationException::withMessages([
                'capture' => 'Payment capture is not enabled for this shop.',
            ]);
        }

        $payload = $response['body'] ?? [];
        if (($response['ok'] ?? false) !== true && ! isset($payload['status'])) {
            $attempt->status = PaymentCaptureAttemptStatus::Failed;
            $attempt->failure_reason = (string) ($payload['reason_code'] ?? 'capture_rejected');
            $attempt->completed_at = now();
            $attempt->save();

            throw ValidationException::withMessages([
                'capture' => (string) ($payload['message'] ?? 'Payment capture was rejected.'),
            ]);
        }

        $attempt = $this->applyResult->apply($attempt, is_array($payload) ? $payload : []);

        return [
            'attempt' => $attempt,
            'cloud' => is_array($payload) ? $payload : [],
        ];
    }

    /**
     * @param  list<array{invoice_id?: mixed, amount_cents?: mixed, amount?: mixed}>  $allocations
     * @return list<array{invoice_id: int, amount_cents: int}>
     */
    private function normalizeAndValidate(Customer $customer, array $allocations): array
    {
        $rows = [];

        foreach ($allocations as $allocation) {
            if (! is_array($allocation)) {
                continue;
            }

            $invoiceId = (int) ($allocation['invoice_id'] ?? 0);
            $amountCents = isset($allocation['amount_cents'])
                ? (int) $allocation['amount_cents']
                : $this->totals->unitPriceCents((string) ($allocation['amount'] ?? '0'));

            if ($invoiceId <= 0 || $amountCents <= 0) {
                continue;
            }

            if (isset($rows[$invoiceId])) {
                throw ValidationException::withMessages([
                    'allocations' => 'Each invoice may only appear once in a payment allocation.',
                ]);
            }

            $invoice = EstimateDocument::query()->find($invoiceId);
            if ($invoice === null || $invoice->document_type !== FinancialDocumentType::Invoice) {
                throw ValidationException::withMessages([
                    'allocations' => 'Only finalized invoices can receive card payment.',
                ]);
            }

            $repairOrder = RepairOrder::query()->findOrFail($invoice->repair_order_id);
            if ((int) $repairOrder->customer_id !== (int) $customer->id) {
                throw ValidationException::withMessages([
                    'allocations' => 'All invoices must belong to this customer.',
                ]);
            }

            $repairOrder->ensureOpenForSettlement();
            $balance = $this->balanceDue->forRepairOrder($repairOrder);
            if ($amountCents > $balance->balanceDueCents) {
                throw ValidationException::withMessages([
                    'allocations' => 'Allocation exceeds the outstanding balance on '
                        .AccountsReceivableInvoiceLabel::forRepairOrder($repairOrder).'.',
                ]);
            }

            $rows[$invoiceId] = [
                'invoice_id' => $invoiceId,
                'amount_cents' => $amountCents,
            ];
        }

        $normalized = array_values($rows);
        if ($normalized === []) {
            throw ValidationException::withMessages([
                'allocations' => 'Select at least one invoice and allocation amount.',
            ]);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function assertLiveCaptureInputs(PaymentCaptureMethod $method, array $input): void
    {
        if (app()->environment('local', 'testing')) {
            return;
        }

        if (filled($input['stub_scenario'] ?? null)) {
            throw ValidationException::withMessages([
                'capture' => 'Stub card scenarios cannot be used for live payments.',
            ]);
        }

        if ($method !== PaymentCaptureMethod::Keyed) {
            return;
        }

        $token = strtolower(trim((string) ($input['source_token'] ?? '')));
        if ($token === '' || str_starts_with($token, 'tok_stub')) {
            throw ValidationException::withMessages([
                'source_token' => 'Live keyed payments require a Platform Web Payments source token. Test stubs are not accepted.',
            ]);
        }
    }

    private function assertAvailableReader(string $deviceRef): void
    {
        PaymentCaptureReadinessProjection::resetMemo();
        $available = collect(app(PaymentCaptureReadinessProjection::class)->current()['devices'] ?? [])
            ->contains(fn (array $device): bool => $device['device_ref'] === $deviceRef && ($device['ready'] ?? true));

        if ($deviceRef === '' || ! $available) {
            throw ValidationException::withMessages([
                'device_ref' => 'Choose an available card reader.',
            ]);
        }
    }

    /**
     * @param  list<array{invoice_id: int, amount_cents: int}>  $allocations
     */
    private function submissionFingerprint(
        Customer $customer,
        int $amountCents,
        array $allocations,
        OverpaymentDisposition|string $disposition,
        ?string $reference,
    ): string {
        $normalized = $allocations;
        usort($normalized, fn (array $a, array $b): int => $a['invoice_id'] <=> $b['invoice_id']);

        return hash('sha256', json_encode([
            'amount_cents' => $amountCents,
            'allocations' => $normalized,
            'customer_id' => (int) $customer->id,
            'disposition' => $disposition instanceof OverpaymentDisposition
                ? $disposition->value
                : (string) $disposition,
            'reference' => $reference,
        ], JSON_THROW_ON_ERROR));
    }

    private function attemptFingerprint(PaymentCaptureAttempt $attempt): ?string
    {
        $plan = is_array($attempt->allocation_plan) ? $attempt->allocation_plan : [];
        if (is_string($plan['payload_fingerprint'] ?? null) && $plan['payload_fingerprint'] !== '') {
            return (string) $plan['payload_fingerprint'];
        }

        $allocations = is_array($plan['allocations'] ?? null) ? $plan['allocations'] : [];
        if ($allocations === [] || $attempt->customer_id === null) {
            return null;
        }

        return $this->submissionFingerprint(
            Customer::query()->findOrFail($attempt->customer_id),
            (int) $attempt->amount_cents,
            $allocations,
            (string) ($plan['disposition'] ?? OverpaymentDisposition::KeepCredit->value),
            filled($plan['reference'] ?? null) ? (string) $plan['reference'] : null,
        );
    }
}
